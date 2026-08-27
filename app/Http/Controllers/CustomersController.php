<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customers\QuickCreateCustomerRequest;
use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Http\Requests\Customers\UpdateCustomerRequest;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\EmailActivity;
use App\Models\Invoice;
use App\Models\Jobcard;
use App\Models\Quote;
use App\Models\SMSActivity;
use App\Models\SMSSettings;
use App\Services\BulkSMSService;
use App\Services\CustomerAccountBalanceCalculator;
use App\Services\CustomerStatementService;
use App\Services\CustomerUpsertService;
use App\Support\CompanyMailer;
use App\Support\CompanyScopedRules;
use App\Support\CsvExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomersController extends Controller
{
    public function index(Request $request): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $sortBy = $request->input('sort_by', 'name');
        $sortDir = $request->input('sort_dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $sortableFields = ['name', 'email', 'phone', 'account_code', 'is_default_sales', 'created_at', 'account_balance'];
        if (! in_array($sortBy, $sortableFields, true)) {
            $sortBy = 'name';
        }

        $customersQuery = Customer::where('company_id', $currentCompany->id)
            ->when($request->string('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('account_code', 'like', "%{$search}%");
                });
            });

        if ($sortBy === 'account_balance') {
            CustomerAccountBalanceCalculator::applyAccountBalanceSort($customersQuery, $currentCompany->id, $sortDir);
        } else {
            $customersQuery->orderBy($sortBy, $sortDir);
        }

        $customers = $customersQuery
            ->paginate(10)
            ->withQueryString();

        $balanceMap = CustomerAccountBalanceCalculator::balancesKeyedByCustomerId(
            $customers->getCollection()->pluck('id'),
            $currentCompany->id
        );

        $customers->setCollection(
            $customers->getCollection()->map(function (Customer $customer) use ($balanceMap) {
                $customer->setAttribute(
                    'account_balance',
                    $balanceMap[$customer->id]['account_balance'] ?? 0.0
                );

                return $customer;
            })
        );

        return Inertia::render('customers/Index', [
            'customers' => $customers,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
            'currentCompany' => $currentCompany,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $sortBy = $request->input('sort_by', 'name');
        $sortDir = $request->input('sort_dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $sortableFields = ['name', 'email', 'phone', 'account_code', 'is_default_sales', 'created_at', 'account_balance'];
        if (! in_array($sortBy, $sortableFields, true)) {
            $sortBy = 'name';
        }

        $customersQuery = Customer::where('company_id', $currentCompany->id)
            ->when($request->string('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('account_code', 'like', "%{$search}%");
                });
            });

        if ($sortBy === 'account_balance') {
            CustomerAccountBalanceCalculator::applyAccountBalanceSort($customersQuery, $currentCompany->id, $sortDir);
        } else {
            $customersQuery->orderBy($sortBy, $sortDir);
        }

        $customers = $customersQuery->get();
        $balanceMap = CustomerAccountBalanceCalculator::balancesKeyedByCustomerId(
            $customers->pluck('id'),
            $currentCompany->id
        );

        $rows = $customers->map(function (Customer $customer) use ($balanceMap) {
            return [
                $customer->id,
                $customer->account_code,
                $customer->name,
                $customer->registration_number,
                $customer->email,
                $customer->phone,
                $customer->company_cell,
                $customer->company_tel,
                $customer->contact_first_name,
                $customer->contact_last_name,
                $customer->contact_cell,
                $customer->contact_email,
                $customer->address,
                $customer->city,
                $customer->country,
                $customer->vat_number,
                $customer->terms,
                $customer->is_default_sales,
                $balanceMap[$customer->id]['account_balance'] ?? 0.0,
                $customer->notes,
                optional($customer->created_at)?->format('Y-m-d H:i:s'),
            ];
        });

        return CsvExport::download('customers_'.date('Y-m-d_His').'.csv', [
            'ID',
            'Account Code',
            'Account Name',
            'Registration Number',
            'Email',
            'Phone',
            'Company Cell',
            'Company Tel',
            'Contact First Name',
            'Contact Last Name',
            'Contact Cell',
            'Contact Email',
            'Address',
            'City',
            'Country',
            'VAT Number',
            'Terms',
            'Default Sales',
            'Account Balance',
            'Notes',
            'Created At',
        ], $rows);
    }

    public function create(): Response
    {
        $this->authorize('create', Customer::class);

        return Inertia::render('customers/Create');
    }

    public function store(StoreCustomerRequest $request, CustomerUpsertService $customerUpsertService): RedirectResponse|JsonResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $customer = $customerUpsertService->createForCompany($request->validated(), $currentCompany->id);

        // If this is a non-Inertia JSON request (quick create from jobcard forms), return JSON
        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'success' => true,
                'customer' => $customer,
            ]);
        }

        return redirect()->route('customers.index')->with('success', 'Customer created');
    }

    /**
     * Search customers for autocomplete/search
     */
    public function search(Request $request)
    {
        $currentCompany = auth()->user()->getCurrentCompany();

        $search = $request->string('q', '')->toString();

        $customers = Customer::where('company_id', $currentCompany->id)
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('account_code', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email', 'phone', 'account_code', 'terms']);

        return response()->json($customers);
    }

    /**
     * Quick create customer (for inline creation in forms)
     */
    public function quickCreate(QuickCreateCustomerRequest $request, CustomerUpsertService $customerUpsertService)
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $customer = $customerUpsertService->quickCreateForCompany($request->validated(), $currentCompany->id);

        return response()->json([
            'success' => true,
            'customer' => $customer->only(['id', 'name', 'email', 'phone', 'account_code', 'terms']),
        ]);
    }

    public function edit(Customer $customer): Response
    {
        $this->authorize('update', $customer);

        $currentCompany = auth()->user()->getCurrentCompany();

        return Inertia::render('customers/Edit', [
            'customer' => $customer,
            'accountBalance' => $customer->accountBalanceBreakdownForCompany($currentCompany->id),
        ]);
    }

    public function show(Customer $customer, Request $request): Response
    {
        $this->authorize('view', $customer);

        $user = $request->user();
        $currentCompany = $user->getCurrentCompany();

        // Load contacts with pagination (same gate as contacts index: list)
        $contactsPerPage = max(1, (int) $request->get('contacts_per_page', 5));
        if ($user->hasModulePermission('contacts', 'list')) {
            $contacts = $customer->contacts()
                ->when($request->filled('contact_search'), function ($query) use ($request) {
                    $search = $request->get('contact_search');
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('position', 'like', "%{$search}%");
                    });
                })
                ->orderBy('is_primary', 'desc')
                ->orderBy('name', 'asc')
                ->paginate($contactsPerPage, ['*'], 'contacts_page');
        } else {
            $contacts = new \Illuminate\Pagination\LengthAwarePaginator([], 0, $contactsPerPage, 1, [
                'path' => $request->url(),
                'pageName' => 'contacts_page',
            ]);
        }

        // Load SMS activities with pagination
        $smsPerPage = $request->get('sms_per_page', 5);
        $smsActivities = $customer->smsActivities()
            ->with('user')
            ->when($request->filled('sms_search'), function ($query) use ($request) {
                $search = $request->get('sms_search');
                $query->where(function ($q) use ($search) {
                    $q->where('message', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('sms_status'), function ($query) use ($request) {
                $query->where('status', $request->get('sms_status'));
            })
            ->orderBy('created_at', 'desc')
            ->paginate($smsPerPage, ['*'], 'sms_page');

        $emailPerPage = $request->get('email_per_page', 10);
        $emailActivities = EmailActivity::where('company_id', $currentCompany->id)
            ->where('customer_id', $customer->id)
            ->with('user')
            ->orderByDesc('sent_at')
            ->orderByDesc('created_at')
            ->paginate($emailPerPage, ['*'], 'emails_page');

        $accountHistoryPerPage = (int) $request->get('account_history_per_page', 10);
        if ($accountHistoryPerPage <= 0) {
            $accountHistoryPerPage = 10;
        }

        $jobcardHistory = Jobcard::query()
            ->where('company_id', $currentCompany->id)
            ->where('customer_id', $customer->id)
            ->selectRaw("'jobcard' AS document_type, id AS document_id, job_number AS document_number, status, total, created_at AS document_date");

        $quoteHistory = Quote::query()
            ->where('company_id', $currentCompany->id)
            ->where('customer_id', $customer->id)
            ->selectRaw("'quote' AS document_type, id AS document_id, quote_number AS document_number, status, total, created_at AS document_date");

        $invoiceHistory = Invoice::query()
            ->where('company_id', $currentCompany->id)
            ->where('customer_id', $customer->id)
            ->selectRaw("'invoice' AS document_type, id AS document_id, invoice_number AS document_number, status, total, created_at AS document_date");

        $creditNoteHistory = CreditNote::query()
            ->where('company_id', $currentCompany->id)
            ->where('customer_id', $customer->id)
            ->selectRaw("'credit_note' AS document_type, id AS document_id, credit_note_number AS document_number, status, total, created_at AS document_date");

        $accountUnionParts = [];
        if ($user->hasModulePermission('jobcards', 'list')) {
            $accountUnionParts[] = $jobcardHistory;
        }
        if ($user->hasModulePermission('quotes', 'view')) {
            $accountUnionParts[] = $quoteHistory;
        }
        if ($user->hasModulePermission('invoices', 'view')) {
            $accountUnionParts[] = $invoiceHistory;
        }
        if ($user->hasModulePermission('credit-notes', 'list')) {
            $accountUnionParts[] = $creditNoteHistory;
        }

        if ($accountUnionParts === []) {
            $accountHistory = new \Illuminate\Pagination\LengthAwarePaginator([], 0, $accountHistoryPerPage, 1, [
                'path' => $request->url(),
                'pageName' => 'account_history_page',
            ]);
        } else {
            $accountUnionQuery = array_shift($accountUnionParts);
            foreach ($accountUnionParts as $part) {
                $accountUnionQuery = $accountUnionQuery->unionAll($part);
            }
            $accountHistory = DB::query()
                ->fromSub($accountUnionQuery, 'account_history')
                ->orderByDesc('document_date')
                ->paginate($accountHistoryPerPage, ['*'], 'account_history_page');
        }

        return Inertia::render('customers/Show', [
            'customer' => $customer,
            'accountBalance' => $customer->accountBalanceBreakdownForCompany($currentCompany->id),
            'contacts' => $contacts,
            'smsActivities' => $smsActivities,
            'emailActivities' => $emailActivities,
            'accountHistory' => $accountHistory,
            'filters' => [
                'contact_search' => $request->get('contact_search'),
                'contacts_per_page' => $contactsPerPage,
                'sms_search' => $request->get('sms_search'),
                'sms_status' => $request->get('sms_status'),
                'sms_per_page' => $smsPerPage,
                'email_per_page' => $emailPerPage,
                'account_history_per_page' => $accountHistoryPerPage,
            ],
        ]);
    }

    public function downloadStatement(Customer $customer, Request $request): \Illuminate\Http\Response
    {
        $this->authorize('view', $customer);

        $user = $request->user();
        abort_if(! $user, 403);

        $currentCompany = $user->getCurrentCompany();
        abort_unless((int) $customer->company_id === (int) $currentCompany->id, 404);

        $statementService = new CustomerStatementService;
        ['rows' => $rows, 'creditNoteRows' => $creditNoteRows, 'totals' => $totals] = $statementService->buildAgeingStatement([$customer->id], $currentCompany->id);
        $pdf = $statementService->makeStatementPdf($customer, $currentCompany, $rows, $creditNoteRows, $totals);

        $safeSlug = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $customer->account_code) ?: (string) $customer->id;
        $filename = 'statement-'.$safeSlug.'-'.now()->format('Ymd').'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer, CustomerUpsertService $customerUpsertService): RedirectResponse
    {
        $customerUpsertService->update($customer, $request->validated());

        return redirect()->route('customers.index')->with('success', 'Customer updated');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        $customer->delete();

        return redirect()->route('customers.index')->with('success', 'Customer deleted');
    }

    public function setDefaultSales(Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $currentCompany = auth()->user()->getCurrentCompany();

        Customer::where('company_id', $currentCompany->id)
            ->where('is_default_sales', true)
            ->update(['is_default_sales' => false]);

        $customer->update(['is_default_sales' => true]);

        return redirect()->back()->with('success', 'Default sales customer updated.');
    }

    /**
     * Send SMS to a customer
     */
    public function sendSMS(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('view', $customer);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:160'],
        ]);

        $currentCompany = auth()->user()->getCurrentCompany();

        // Check if customer has a phone number
        $phoneNumber = $customer->smsPhoneNumber();
        if (! $phoneNumber) {
            return redirect()->back()
                ->withErrors(['message' => 'Customer does not have a phone number.']);
        }

        // Get system SMS settings
        $smsSettings = SMSSettings::getActive();
        if (! $smsSettings || ! $smsSettings->is_active) {
            return redirect()->back()
                ->withErrors(['message' => 'SMS functionality is not configured or disabled. Please contact your administrator.']);
        }

        // Create SMS activity record
        $smsActivity = SMSActivity::create([
            'customer_id' => $customer->id,
            'contact_id' => null,
            'user_id' => auth()->id(),
            'company_id' => $currentCompany->id,
            'phone_number' => $phoneNumber,
            'message' => $validated['message'],
            'status' => 'pending',
        ]);

        try {
            // Initialize SMS service
            $smsService = new BulkSMSService(
                $smsSettings->bulksms_username,
                $smsSettings->bulksms_password,
                $smsSettings->bulksms_sender_name
            );

            // Send SMS
            $result = $smsService->sendSMS($phoneNumber, $validated['message']);

            if ($result['success']) {
                // Update activity as successful
                $smsActivity->update([
                    'status' => 'sent',
                    'bulksms_reference' => $result['reference'] ?? null,
                    'bulksms_response' => $result,
                ]);

                return redirect()->back()
                    ->with('success', 'SMS sent successfully to '.$customer->name);
            } else {
                // Update activity as failed
                $smsActivity->update([
                    'status' => 'failed',
                    'error_message' => $result['message'],
                    'bulksms_response' => $result,
                ]);

                return redirect()->back()
                    ->withErrors(['message' => $result['message']]);
            }

        } catch (\Exception $e) {
            // Update activity as failed
            $smsActivity->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->withErrors(['message' => 'Failed to send SMS: '.$e->getMessage()]);
        }
    }

    /**
     * Send email to a customer.
     */
    public function sendEmail(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('view', $customer);

        $currentCompany = auth()->user()->getCurrentCompany();

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        if (empty($customer->email)) {
            return redirect()->back()->withErrors(['message' => 'Customer does not have an email address.']);
        }

        $contact = $customer->contacts()
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->first();

        $context = [
            'company' => $currentCompany->toArray(),
            'customer' => $customer->toArray(),
            'contact' => $contact?->toArray() ?? [],
            'user' => [
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ],
            'date' => [
                'today' => now()->toDateString(),
                'now' => now()->toDateTimeString(),
            ],
        ];

        $subject = $this->renderTemplateString($validated['subject'], $context);
        $renderedBody = $this->renderTemplateString($validated['body'], $context);

        try {
            $mailConfig = CompanyMailer::resolve($currentCompany);
            Mail::mailer($mailConfig['mailer'])->send([], [], function ($message) use ($customer, $subject, $renderedBody, $currentCompany, $mailConfig) {
                $message->to($customer->email, $customer->name)
                    ->subject($subject)
                    ->from($mailConfig['from_address'], $mailConfig['from_name'])
                    ->text($renderedBody);

                if (! empty($currentCompany->email)) {
                    $message->replyTo($currentCompany->email, $currentCompany->name ?? null);
                }
            });

            EmailActivity::create([
                'company_id' => $currentCompany->id,
                'customer_id' => $customer->id,
                'contact_id' => null,
                'user_id' => auth()->id(),
                'email_template_id' => null,
                'recipient_email' => $customer->email,
                'recipient_name' => $customer->name,
                'subject' => $subject,
                'body' => $renderedBody,
                'email_type' => 'direct',
                'related_type' => 'customer',
                'related_id' => $customer->id,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            return redirect()->back()->with('success', 'Email sent successfully to '.$customer->email);
        } catch (\Throwable $e) {
            EmailActivity::create([
                'company_id' => $currentCompany->id,
                'customer_id' => $customer->id,
                'contact_id' => null,
                'user_id' => auth()->id(),
                'email_template_id' => null,
                'recipient_email' => $customer->email,
                'recipient_name' => $customer->name,
                'subject' => $subject,
                'body' => $renderedBody,
                'email_type' => 'direct',
                'related_type' => 'customer',
                'related_id' => $customer->id,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors(['message' => 'Failed to send email: '.$e->getMessage()]);
        }
    }

    private function renderTemplateString(string $template, array $context): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', function ($matches) use ($context) {
            $path = $matches[1] ?? '';
            if ($path === '') {
                return '';
            }

            $value = $this->resolveContextPath($context, $path);
            if (is_bool($value)) {
                return $value ? 'Yes' : 'No';
            }

            return is_scalar($value) ? (string) $value : '';
        }, $template) ?? $template;
    }

    private function resolveContextPath(array $context, string $path): mixed
    {
        $segments = explode('.', $path);
        $current = $context;

        foreach ($segments as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];

                continue;
            }

            return '';
        }

        return $current;
    }
}

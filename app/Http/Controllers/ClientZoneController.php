<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\CustomerUpdateRequest;
use App\Models\Invoice;
use App\Models\Jobcard;
use App\Models\ProductSerialNumber;
use App\Models\Quote;
use App\Models\User;
use App\Notifications\SystemEventNotification;
use App\Services\CustomerAccountBalanceCalculator;
use App\Services\CustomerStatementService;
use App\Services\PdfGenerationService;
use App\Support\CompanyMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ClientZoneController extends Controller
{
    /** @var array<int, Company|null> */
    private array $companyCache = [];

    private function getScopedCustomerIds(Request $request, Customer $customer): \Illuminate\Support\Collection
    {
        return collect([$customer->id]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>  $customerIds
     * @return \Illuminate\Support\Collection<int, array{id:int,name:string}>
     */
    private function getAvailableCompaniesForCustomerIds(\Illuminate\Support\Collection $customerIds): \Illuminate\Support\Collection
    {
        $companyIds = collect()
            ->merge(Jobcard::query()->whereIn('customer_id', $customerIds)->distinct()->pluck('company_id'))
            ->merge(Quote::query()->whereIn('customer_id', $customerIds)->distinct()->pluck('company_id'))
            ->merge(Invoice::query()->whereIn('customer_id', $customerIds)->distinct()->pluck('company_id'))
            ->merge(CreditNote::query()->whereIn('customer_id', $customerIds)->distinct()->pluck('company_id'))
            ->filter()
            ->unique()
            ->values();

        if ($companyIds->isEmpty()) {
            return collect();
        }

        return Company::query()
            ->whereIn('id', $companyIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Company $company) => ['id' => $company->id, 'name' => $company->name])
            ->values();
    }

    private function getCompanyById(?int $companyId): ?Company
    {
        if (! $companyId) {
            return null;
        }

        if (! array_key_exists($companyId, $this->companyCache)) {
            $this->companyCache[$companyId] = Company::query()->find($companyId);
        }

        return $this->companyCache[$companyId];
    }

    private function statusLabel(string $documentType, string $status, ?int $companyId): string
    {
        $company = $this->getCompanyById($companyId);

        if ($company && $documentType === 'jobcard') {
            $map = collect($company->getJobcardStatusOptions())->pluck('label', 'value');

            return $map[$status] ?? ucfirst(str_replace('_', ' ', $status));
        }

        if ($company && $documentType === 'quote') {
            $map = collect($company->getQuoteStatusOptions())->pluck('label', 'value');

            return $map[$status] ?? ucfirst(str_replace('_', ' ', $status));
        }

        return ucfirst(str_replace('_', ' ', $status));
    }

    private function summarizedStatusCounts(\Illuminate\Support\Collection $rows, string $documentType): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $label = $this->statusLabel($documentType, (string) $row['status'], $row['company_id'] ?? null);
            $counts[$label] = ($counts[$label] ?? 0) + (int) $row['count'];
        }

        ksort($counts);

        return $counts;
    }

    private function assertClientOwnsDocument(Request $request, int $customerId): void
    {
        $customer = $this->getClientCustomer($request);
        $allowed = $this->getScopedCustomerIds($request, $customer)
            ->contains(fn ($authorizedCustomerId) => (int) $authorizedCustomerId === $customerId);

        abort_if(! $allowed, 403);
    }

    private function ensureDocumentSigningEnabled(int $companyId): void
    {
        $company = Company::query()->find($companyId);

        abort_if(! $company, 404);
        abort_if(! $company->enable_document_signing, 403);
    }

    private function getClientCustomer(Request $request): Customer
    {
        $customer = $request->user()?->customer;
        abort_if(! $customer, 404);

        return $customer;
    }

    public function showRegistrationForm(): Response
    {
        return Inertia::render('client-zone/Register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $genericRegistrationError = 'We could not complete registration with those details. Please contact the company if you need access.';

        $customer = Customer::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($validated['email'])])
            ->first();

        if (! $customer) {
            return back()->withErrors([
                'email' => $genericRegistrationError,
            ])->withInput($request->except(['password', 'password_confirmation']));
        }

        if (User::whereRaw('LOWER(email) = ?', [strtolower($validated['email'])])->exists()) {
            return back()->withErrors([
                'email' => $genericRegistrationError,
            ])->withInput($request->except(['password', 'password_confirmation']));
        }

        User::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'password' => Hash::make($validated['password']),
            'user_type' => 'client',
            'customer_id' => $customer->id,
            'current_company_id' => $customer->company_id,
            'approval_status' => 'pending',
        ]);

        $this->notifyCompanyApproversOfClientRegistration($customer, $validated['name']);

        return redirect()->route('login')->with('status', 'Registration submitted. Your account will be available once approved by an administrator.');
    }

    public function dashboard(Request $request): Response
    {
        $customer = $this->getClientCustomer($request);
        $customerIds = $this->getScopedCustomerIds($request, $customer);

        $jobcardStatusRows = Jobcard::query()
            ->whereIn('customer_id', $customerIds)
            ->selectRaw('company_id, status, COUNT(*) as count')
            ->groupBy('company_id', 'status')
            ->get();

        $quoteStatusRows = Quote::query()
            ->whereIn('customer_id', $customerIds)
            ->selectRaw('company_id, status, COUNT(*) as count')
            ->groupBy('company_id', 'status')
            ->get();

        $invoiceStatusRows = Invoice::query()
            ->whereIn('customer_id', $customerIds)
            ->selectRaw('company_id, status, COUNT(*) as count')
            ->groupBy('company_id', 'status')
            ->get();

        $creditNoteStatusRows = CreditNote::query()
            ->whereIn('customer_id', $customerIds)
            ->selectRaw('company_id, status, COUNT(*) as count')
            ->groupBy('company_id', 'status')
            ->get();

        $availableCompanies = $this->getAvailableCompaniesForCustomerIds($customerIds);
        $accountBalances = $availableCompanies->map(function (array $row) use ($customerIds) {
            $companyId = (int) $row['id'];
            $breakdown = CustomerAccountBalanceCalculator::forCustomersInCompany($customerIds, $companyId);

            return [
                'company_id' => $companyId,
                'company_name' => $row['name'],
                'outstanding_invoices' => $breakdown['outstanding_invoices'],
                'unapplied_credit' => $breakdown['unapplied_credit'],
                'account_balance' => $breakdown['account_balance'],
            ];
        })->values();

        return Inertia::render('client-zone/Dashboard', [
            'customer' => $customer->only(['id', 'name', 'email', 'phone', 'address', 'city', 'country', 'vat_number', 'notes']),
            'accountBalances' => $accountBalances,
            'summary' => [
                'jobcards' => [
                    'total' => (int) Jobcard::query()->whereIn('customer_id', $customerIds)->count(),
                    'statuses' => $this->summarizedStatusCounts($jobcardStatusRows, 'jobcard'),
                ],
                'quotes' => [
                    'total' => (int) Quote::query()->whereIn('customer_id', $customerIds)->count(),
                    'statuses' => $this->summarizedStatusCounts($quoteStatusRows, 'quote'),
                ],
                'invoices' => [
                    'total' => (int) Invoice::query()->whereIn('customer_id', $customerIds)->count(),
                    'statuses' => $this->summarizedStatusCounts($invoiceStatusRows, 'invoice'),
                ],
                'creditNotes' => [
                    'total' => (int) CreditNote::query()->whereIn('customer_id', $customerIds)->count(),
                    'statuses' => $this->summarizedStatusCounts($creditNoteStatusRows, 'credit-note'),
                ],
            ],
        ]);
    }

    public function documents(Request $request): Response
    {
        $customer = $this->getClientCustomer($request);
        $customerIds = $this->getScopedCustomerIds($request, $customer);
        $availableCompanies = $this->getAvailableCompaniesForCustomerIds($customerIds);
        $selectedCompanyId = (int) $request->integer('company_id');
        $selectedCompanyIsValid = $selectedCompanyId > 0 && $availableCompanies->contains(fn ($company) => $company['id'] === $selectedCompanyId);
        $allowedDocumentTypes = ['invoice', 'quote', 'jobcard', 'credit-note'];
        $selectedDocumentType = strtolower(trim((string) $request->string('document_type')->toString()));
        if (! in_array($selectedDocumentType, $allowedDocumentTypes, true)) {
            $selectedDocumentType = '';
        }

        $jobcards = Jobcard::query()
            ->with('company:id,name')
            ->whereIn('customer_id', $customerIds)
            ->when($selectedCompanyIsValid, fn ($query) => $query->where('company_id', $selectedCompanyId))
            ->latest('created_at')
            ->limit(100)
            ->get(['id', 'job_number', 'status', 'total', 'created_at', 'company_id'])
            ->map(fn (Jobcard $row) => [
                'id' => $row->id,
                'type' => 'Jobcard',
                'type_key' => 'jobcard',
                'number' => $row->job_number,
                'status' => $this->statusLabel('jobcard', (string) $row->status, (int) $row->company_id),
                'total' => $row->total,
                'date' => $row->created_at,
                'company_name' => $row->company?->name,
                'view_url' => route('client-zone.jobcards.show', $row->id),
                'download_pdf_url' => route('client-zone.jobcards.download-pdf', $row->id),
            ]);

        $quotes = Quote::query()
            ->with('company:id,name')
            ->whereIn('customer_id', $customerIds)
            ->when($selectedCompanyIsValid, fn ($query) => $query->where('company_id', $selectedCompanyId))
            ->latest('created_at')
            ->limit(100)
            ->get(['id', 'quote_number', 'status', 'total', 'created_at', 'company_id'])
            ->map(fn (Quote $row) => [
                'id' => $row->id,
                'type' => 'Quote',
                'type_key' => 'quote',
                'number' => $row->quote_number,
                'status' => $this->statusLabel('quote', (string) $row->status, (int) $row->company_id),
                'total' => $row->total,
                'date' => $row->created_at,
                'company_name' => $row->company?->name,
                'view_url' => route('client-zone.quotes.show', $row->id),
                'download_pdf_url' => route('client-zone.quotes.download-pdf', $row->id),
            ]);

        $invoices = Invoice::query()
            ->with('company:id,name')
            ->whereIn('customer_id', $customerIds)
            ->when($selectedCompanyIsValid, fn ($query) => $query->where('company_id', $selectedCompanyId))
            ->latest('invoice_date')
            ->limit(100)
            ->get(['id', 'invoice_number', 'status', 'total', 'invoice_date', 'company_id'])
            ->map(fn (Invoice $row) => [
                'id' => $row->id,
                'type' => 'Invoice',
                'type_key' => 'invoice',
                'number' => $row->invoice_number,
                'status' => $this->statusLabel('invoice', (string) $row->status, (int) $row->company_id),
                'total' => $row->total,
                'date' => $row->invoice_date,
                'company_name' => $row->company?->name,
                'view_url' => route('client-zone.invoices.show', $row->id),
                'download_pdf_url' => route('client-zone.invoices.download-pdf', $row->id),
            ]);

        $creditNotes = CreditNote::query()
            ->with('company:id,name')
            ->whereIn('customer_id', $customerIds)
            ->when($selectedCompanyIsValid, fn ($query) => $query->where('company_id', $selectedCompanyId))
            ->latest('credit_note_date')
            ->limit(100)
            ->get(['id', 'credit_note_number', 'status', 'total', 'credit_note_date', 'company_id'])
            ->map(fn (CreditNote $row) => [
                'id' => $row->id,
                'type' => 'Credit Note',
                'type_key' => 'credit-note',
                'number' => $row->credit_note_number,
                'status' => $this->statusLabel('credit-note', (string) $row->status, (int) $row->company_id),
                'total' => $row->total,
                'date' => $row->credit_note_date,
                'company_name' => $row->company?->name,
            ]);

        $allDocuments = collect()
            ->merge($invoices)
            ->merge($quotes)
            ->merge($jobcards)
            ->merge($creditNotes)
            ->sortByDesc('date')
            ->values();

        if ($selectedDocumentType !== '') {
            $allDocuments = $allDocuments
                ->filter(fn (array $row) => (string) ($row['type_key'] ?? '') === $selectedDocumentType)
                ->values();
        }

        $search = trim((string) $request->string('search')->toString());
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $allDocuments = $allDocuments->filter(function (array $row) use ($needle) {
                $haystack = mb_strtolower(implode(' ', [
                    (string) ($row['type'] ?? ''),
                    (string) ($row['number'] ?? ''),
                    (string) ($row['status'] ?? ''),
                    (string) ($row['company_name'] ?? ''),
                ]));

                return str_contains($haystack, $needle);
            })->values();
        }

        $perPage = 15;
        $page = max(1, (int) $request->integer('page', 1));
        $total = $allDocuments->count();
        $items = $allDocuments->slice(($page - 1) * $perPage, $perPage)->values();
        $documents = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return Inertia::render('client-zone/Documents', [
            'availableCompanies' => $availableCompanies,
            'selectedCompanyId' => $selectedCompanyIsValid ? $selectedCompanyId : null,
            'selectedDocumentType' => $selectedDocumentType !== '' ? $selectedDocumentType : null,
            'statementPdfUrl' => $selectedCompanyIsValid
                ? route('client-zone.statement.download-pdf', ['company_id' => $selectedCompanyId])
                : null,
            'search' => $search,
            'documents' => $documents,
        ]);
    }

    public function requestUpdateForm(Request $request): Response
    {
        $customer = $this->getClientCustomer($request);

        return Inertia::render('client-zone/RequestUpdate', [
            'customer' => $customer->only(['id', 'name', 'email', 'phone', 'address', 'city', 'country', 'vat_number', 'notes']),
        ]);
    }

    public function requestProfileUpdate(Request $request): RedirectResponse
    {
        $user = $request->user();
        $customer = $this->getClientCustomer($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'vat_number' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $updateRequest = CustomerUpdateRequest::create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'requested_changes' => $validated,
            'status' => 'pending',
        ]);

        $this->notifyCompanyOfClientInformationUpdateRequest($customer, $updateRequest, $user);

        return back()->with('success', 'Your update request was submitted and is awaiting approval.');
    }

    public function showInvoice(Request $request, int $invoiceId): Response
    {
        $invoice = Invoice::query()->findOrFail($invoiceId);
        $this->assertClientOwnsDocument($request, (int) $invoice->customer_id);
        $invoice->load(['customer', 'contact', 'lineItems.product', 'lineItems.taxRate', 'lineGroups', 'company', 'signatures.user']);

        return Inertia::render('client-zone/DocumentView', [
            'documentType' => 'invoice',
            'documentTitle' => $invoice->invoice_number,
            'document' => $invoice,
            'statusLabel' => $this->statusLabel('invoice', (string) $invoice->status, (int) $invoice->company_id),
            'downloadPdfUrl' => route('client-zone.invoices.download-pdf', $invoice->id),
            'signUrl' => route('client-zone.invoices.sign', $invoice->id),
            'documentSigningEnabled' => (bool) ($invoice->company?->enable_document_signing ?? false),
        ]);
    }

    public function showQuote(Request $request, int $quoteId): Response
    {
        $quote = Quote::query()->findOrFail($quoteId);
        $this->assertClientOwnsDocument($request, (int) $quote->customer_id);
        $quote->load(['customer', 'contact', 'lineItems.product', 'lineItems.taxRate', 'lineGroups', 'company', 'signatures.user']);

        return Inertia::render('client-zone/DocumentView', [
            'documentType' => 'quote',
            'documentTitle' => $quote->quote_number,
            'document' => $quote,
            'statusLabel' => $this->statusLabel('quote', (string) $quote->status, (int) $quote->company_id),
            'downloadPdfUrl' => route('client-zone.quotes.download-pdf', $quote->id),
            'signUrl' => route('client-zone.quotes.sign', $quote->id),
            'documentSigningEnabled' => (bool) ($quote->company?->enable_document_signing ?? false),
        ]);
    }

    public function showJobcard(Request $request, int $jobcardId): Response
    {
        $jobcard = Jobcard::query()->findOrFail($jobcardId);
        $this->assertClientOwnsDocument($request, (int) $jobcard->customer_id);
        $jobcard->load(['customer', 'contact', 'lineItems.product', 'lineItems.taxRate', 'lineGroups', 'company', 'signatures.user']);

        return Inertia::render('client-zone/DocumentView', [
            'documentType' => 'jobcard',
            'documentTitle' => $jobcard->job_number,
            'document' => $jobcard,
            'statusLabel' => $this->statusLabel('jobcard', (string) $jobcard->status, (int) $jobcard->company_id),
            'downloadPdfUrl' => route('client-zone.jobcards.download-pdf', $jobcard->id),
            'signUrl' => route('client-zone.jobcards.sign', $jobcard->id),
            'documentSigningEnabled' => (bool) ($jobcard->company?->enable_document_signing ?? false),
        ]);
    }

    public function downloadInvoicePdf(Request $request, int $invoiceId): HttpResponse
    {
        $invoice = Invoice::query()->findOrFail($invoiceId);
        $this->assertClientOwnsDocument($request, (int) $invoice->customer_id);

        $invoice->load(['customer', 'contact', 'lineItems.product', 'lineItems.taxRate', 'lineGroups', 'company', 'source', 'source.source', 'signatures']);

        foreach ($invoice->lineItems as $lineItem) {
            $lineItem->serialNumbers = ! empty($lineItem->serial_number_ids)
                ? ProductSerialNumber::whereIn('id', $lineItem->serial_number_ids)->get(['id', 'serial_number', 'status'])
                : collect([]);
        }

        $invoice->setRelation('lineItemsForRoundingTotals', $invoice->lineItems->values());
        $invoice->setRelation('lineItems', $invoice->lineItems->reject(function ($item) {
            return strtolower(trim((string) ($item->description ?? ''))) === 'rounding adjustment';
        })->values());

        $company = $invoice->company;
        $pdfService = new PdfGenerationService;
        $pdf = $pdfService->generatePdf('invoice', compact('invoice', 'company'), $company);
        $filename = "invoice-{$invoice->invoice_number}.pdf";

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function downloadQuotePdf(Request $request, int $quoteId): HttpResponse
    {
        $quote = Quote::query()->findOrFail($quoteId);
        $this->assertClientOwnsDocument($request, (int) $quote->customer_id);

        $quote->load(['customer', 'contact', 'lineItems.product', 'lineItems.taxRate', 'lineGroups', 'company', 'signatures']);
        $company = $quote->company;

        $pdfService = new PdfGenerationService;
        $pdf = $pdfService->generatePdf('quote', compact('quote', 'company'), $company);
        $filename = "quote-{$quote->quote_number}.pdf";

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function downloadJobcardPdf(Request $request, int $jobcardId): HttpResponse
    {
        $jobcard = Jobcard::query()->findOrFail($jobcardId);
        $this->assertClientOwnsDocument($request, (int) $jobcard->customer_id);

        $jobcard->load(['customer', 'contact', 'lineItems.product', 'lineItems.taxRate', 'lineGroups', 'company', 'signatures']);
        $company = $jobcard->company;

        $pdfService = new PdfGenerationService;
        $pdf = $pdfService->generatePdf('jobcard', [
            'jobcard' => $jobcard,
            'company' => $company,
        ], $company);
        $filename = "jobcard-{$jobcard->job_number}.pdf";

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function downloadStatementPdf(Request $request): HttpResponse
    {
        $customer = $this->getClientCustomer($request);
        $customerIds = $this->getScopedCustomerIds($request, $customer);
        $availableCompanies = $this->getAvailableCompaniesForCustomerIds($customerIds);
        $selectedCompanyId = (int) $request->integer('company_id');
        $selectedCompany = $availableCompanies->first(fn (array $company) => $company['id'] === $selectedCompanyId);

        abort_if(! $selectedCompany, 422, 'Please select a valid company before generating a statement.');

        $company = Company::query()->find($selectedCompanyId);
        if (! $company) {
            abort(404);
        }

        $statementService = new CustomerStatementService;
        ['rows' => $rows, 'creditNoteRows' => $creditNoteRows, 'totals' => $totals] = $statementService->buildAgeingStatement($customerIds, $selectedCompanyId);
        $pdf = $statementService->makeStatementPdf($customer, $company, $rows, $creditNoteRows, $totals);

        $filename = 'statement-'.$customer->id.'-'.$selectedCompanyId.'-'.now()->format('Ymd').'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function signInvoice(Request $request, int $invoiceId): RedirectResponse
    {
        $invoice = Invoice::query()->findOrFail($invoiceId);
        $this->assertClientOwnsDocument($request, (int) $invoice->customer_id);

        return $this->handleClientSignature($request, 'invoice', $invoice->id, $invoice->company_id);
    }

    public function signQuote(Request $request, int $quoteId): RedirectResponse
    {
        $quote = Quote::query()->findOrFail($quoteId);
        $this->assertClientOwnsDocument($request, (int) $quote->customer_id);

        return $this->handleClientSignature($request, 'quote', $quote->id, $quote->company_id);
    }

    public function signJobcard(Request $request, int $jobcardId): RedirectResponse
    {
        $jobcard = Jobcard::query()->findOrFail($jobcardId);
        $this->assertClientOwnsDocument($request, (int) $jobcard->customer_id);

        return $this->handleClientSignature($request, 'jobcard', $jobcard->id, $jobcard->company_id);
    }

    private function handleClientSignature(Request $request, string $signableType, int $signableId, int $companyId): RedirectResponse
    {
        $this->ensureDocumentSigningEnabled($companyId);

        $validated = $request->validate([
            'signer_name' => ['required', 'string', 'max:255'],
            'signature_data' => ['required', 'string'],
        ]);

        if (! preg_match('/^data:image\/png;base64,/', $validated['signature_data'])) {
            return back()->withErrors(['signature_data' => 'Invalid signature format.']);
        }

        $raw = substr($validated['signature_data'], strpos($validated['signature_data'], ',') + 1);
        $binary = base64_decode($raw, true);
        if ($binary === false) {
            return back()->withErrors(['signature_data' => 'Invalid signature data.']);
        }

        $path = sprintf(
            'signatures/%d/%s/%d/%s-%s.png',
            $companyId,
            $signableType.'s',
            $signableId,
            now()->format('YmdHis'),
            bin2hex(random_bytes(4))
        );
        Storage::disk('public')->put($path, $binary);

        $signableModel = match ($signableType) {
            'invoice' => Invoice::findOrFail($signableId),
            'quote' => Quote::findOrFail($signableId),
            'jobcard' => Jobcard::findOrFail($signableId),
            default => abort(422),
        };

        $signableModel->signatures()->create([
            'company_id' => $companyId,
            'user_id' => auth()->id(),
            'signer_name' => trim($validated['signer_name']),
            'signature_path' => $path,
            'signed_at' => now(),
        ]);

        $signerName = trim($validated['signer_name']);
        match (true) {
            $signableModel instanceof Quote => $this->notifyQuoteAssigneeOfClientZoneSignature($signableModel, $signerName),
            $signableModel instanceof Invoice => $this->notifyInvoiceAssigneeOfClientZoneSignature($signableModel, $signerName),
            $signableModel instanceof Jobcard => $this->notifyJobcardAssigneeOfClientZoneSignature($signableModel, $signerName),
            default => null,
        };

        return back()->with('success', 'Signature captured successfully.');
    }

    /**
     * Prefer assignee email; if none, use company email (covers older quotes/invoices/jobcards without an assignee).
     *
     * @return array{email: string, name: string|null}|null
     */
    private function resolveClientZoneSignatureRecipient(Company $company, ?User $assignee): ?array
    {
        if ($assignee && filter_var((string) $assignee->email, FILTER_VALIDATE_EMAIL)) {
            return ['email' => $assignee->email, 'name' => $assignee->name];
        }

        if (! empty($company->email) && filter_var((string) $company->email, FILTER_VALIDATE_EMAIL)) {
            return ['email' => $company->email, 'name' => $company->name];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $noRecipientContext
     * @param  array<string, mixed>  $errorContext
     */
    private function sendClientZoneDocumentSignedMail(
        Company $company,
        ?User $assignee,
        string $subject,
        string $bodyHtml,
        string $noRecipientLogMessage,
        array $noRecipientContext,
        string $errorLogMessage,
        array $errorContext,
    ): void {
        $recipient = $this->resolveClientZoneSignatureRecipient($company, $assignee);
        if ($recipient === null) {
            Log::warning($noRecipientLogMessage, $noRecipientContext);

            return;
        }

        try {
            $mailConfig = CompanyMailer::resolve($company);
            Mail::mailer($mailConfig['mailer'])->send([], [], function ($message) use ($recipient, $subject, $bodyHtml, $company, $mailConfig) {
                $message->to($recipient['email'], $recipient['name'])
                    ->subject($subject)
                    ->from($mailConfig['from_address'], $mailConfig['from_name'])
                    ->html($bodyHtml);

                if (! empty($company->email) && filter_var((string) $company->email, FILTER_VALIDATE_EMAIL)) {
                    $message->replyTo($company->email, $company->name ?? null);
                }
            });
        } catch (\Throwable $e) {
            Log::error($errorLogMessage, array_merge($errorContext, [
                'message' => $e->getMessage(),
            ]));
        }
    }

    private function notifyQuoteAssigneeOfClientZoneSignature(Quote $quote, string $signerName): void
    {
        $quote->loadMissing(['company', 'customer', 'salesperson']);
        $company = $quote->company;
        if ($company === null) {
            return;
        }

        $quoteNumber = e($quote->quote_number);
        $customerLabel = e($quote->customer?->name ?? 'Customer');
        $signerLabel = e($signerName);
        $companyLabel = e($company->name ?? 'Company');
        $quoteUrl = e(route('quotes.show', $quote));

        $subject = "Quote {$quote->quote_number} signed in Client Zone";
        $body = <<<HTML
<p>A client signed quote <strong>{$quoteNumber}</strong> in Client Zone.</p>
<p><strong>Customer:</strong> {$customerLabel}<br>
<strong>Signer name:</strong> {$signerLabel}</p>
<p><a href="{$quoteUrl}">Open quote in {$companyLabel}</a></p>
HTML;

        $this->sendClientZoneDocumentSignedMail(
            $company,
            $quote->salesperson,
            $subject,
            $body,
            'Client Zone quote signed: no notification recipient (missing assignee and company email)',
            ['quote_id' => $quote->id, 'company_id' => $quote->company_id],
            'Client Zone quote signed: failed to send notification',
            ['quote_id' => $quote->id],
        );
    }

    private function notifyInvoiceAssigneeOfClientZoneSignature(Invoice $invoice, string $signerName): void
    {
        $invoice->loadMissing(['company', 'customer', 'salesperson']);
        $company = $invoice->company;
        if ($company === null) {
            return;
        }

        $invoiceNumber = e($invoice->invoice_number);
        $customerLabel = e($invoice->customer?->name ?? 'Customer');
        $signerLabel = e($signerName);
        $companyLabel = e($company->name ?? 'Company');
        $invoiceUrl = e(route('invoices.show', $invoice));

        $subject = "Invoice {$invoice->invoice_number} signed in Client Zone";
        $body = <<<HTML
<p>A client signed invoice <strong>{$invoiceNumber}</strong> in Client Zone.</p>
<p><strong>Customer:</strong> {$customerLabel}<br>
<strong>Signer name:</strong> {$signerLabel}</p>
<p><a href="{$invoiceUrl}">Open invoice in {$companyLabel}</a></p>
HTML;

        $this->sendClientZoneDocumentSignedMail(
            $company,
            $invoice->salesperson,
            $subject,
            $body,
            'Client Zone invoice signed: no notification recipient (missing assignee and company email)',
            ['invoice_id' => $invoice->id, 'company_id' => $invoice->company_id],
            'Client Zone invoice signed: failed to send notification',
            ['invoice_id' => $invoice->id],
        );
    }

    private function notifyJobcardAssigneeOfClientZoneSignature(Jobcard $jobcard, string $signerName): void
    {
        $jobcard->loadMissing(['company', 'customer', 'assignedUser']);
        $company = $jobcard->company;
        if ($company === null) {
            return;
        }

        $jobNumber = e($jobcard->job_number);
        $customerLabel = e($jobcard->customer?->name ?? 'Customer');
        $signerLabel = e($signerName);
        $companyLabel = e($company->name ?? 'Company');
        $jobcardUrl = e(route('jobcards.show', $jobcard));

        $subject = "Job card {$jobcard->job_number} signed in Client Zone";
        $body = <<<HTML
<p>A client signed job card <strong>{$jobNumber}</strong> in Client Zone.</p>
<p><strong>Customer:</strong> {$customerLabel}<br>
<strong>Signer name:</strong> {$signerLabel}</p>
<p><a href="{$jobcardUrl}">Open job card in {$companyLabel}</a></p>
HTML;

        $this->sendClientZoneDocumentSignedMail(
            $company,
            $jobcard->assignedUser,
            $subject,
            $body,
            'Client Zone job card signed: no notification recipient (missing assignee and company email)',
            ['jobcard_id' => $jobcard->id, 'company_id' => $jobcard->company_id],
            'Client Zone job card signed: failed to send notification',
            ['jobcard_id' => $jobcard->id],
        );
    }

    private function notifyCompanyOfClientInformationUpdateRequest(Customer $customer, CustomerUpdateRequest $updateRequest, User $clientUser): void
    {
        $reviewUrl = route('registered-users.update-requests.show', $updateRequest);
        $this->companyApprovalUsers((int) $customer->company_id)->each(
            fn (User $approver) => $approver->notify(new SystemEventNotification(
                'Client Zone information update request submitted',
                $reviewUrl,
                'client_info_update'
            ))
        );

        try {
            $customer->loadMissing('company');
            $company = $customer->company;
            if ($company === null || empty($company->email) || ! filter_var((string) $company->email, FILTER_VALIDATE_EMAIL)) {
                Log::warning('Client Zone information update: company has no valid email for notification', [
                    'customer_id' => $customer->id,
                    'company_id' => $customer->company_id,
                    'update_request_id' => $updateRequest->id,
                ]);

                return;
            }

            $customerLabel = e($customer->name);
            $clientLabel = e($clientUser->name);
            $reviewUrl = e($reviewUrl);

            $subject = 'Client Zone: information update request';
            $body = <<<HTML
<p>A client submitted a request to update their information.</p>
<p><strong>Customer:</strong> {$customerLabel}<br>
<strong>Client account:</strong> {$clientLabel}</p>
<p><a href="{$reviewUrl}">Review request</a></p>
HTML;

            $mailConfig = CompanyMailer::resolve($company);
            Mail::mailer($mailConfig['mailer'])->send([], [], function ($message) use ($company, $subject, $body, $mailConfig) {
                $message->to($company->email, $company->name)
                    ->subject($subject)
                    ->from($mailConfig['from_address'], $mailConfig['from_name'])
                    ->html($body);
            });
        } catch (\Throwable $e) {
            Log::error('Client Zone information update: failed to send company notification', [
                'update_request_id' => $updateRequest->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function notifyCompanyApproversOfClientRegistration(Customer $customer, string $clientName): void
    {
        $pendingUsersUrl = route('registered-users.pending.index');
        $title = "New Client Zone registration pending approval: {$clientName}";
        $this->companyApprovalUsers((int) $customer->company_id)->each(
            fn (User $approver) => $approver->notify(new SystemEventNotification($title, $pendingUsersUrl, 'client_registration'))
        );
    }

    private function companyApprovalUsers(int $companyId)
    {
        return User::query()
            ->staffSelectableForCompany($companyId)
            ->get()
            ->filter(fn (User $user) => $user->hasModulePermission('registered-users', 'approve')
                || $user->hasModulePermission('customer-update-requests', 'approve'))
            ->unique('id')
            ->values();
    }
}

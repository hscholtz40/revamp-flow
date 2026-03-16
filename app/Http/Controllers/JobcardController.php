<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Jobcard;
use App\Models\JobcardLineItem;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\ChartOfAccount;
use App\Models\Team;
use App\Models\User;
use App\Services\ReminderService;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Barryvdh\DomPDF\Facade\Pdf;
use Inertia\Inertia;
use Inertia\Response;

class JobcardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';
        
        $query = Jobcard::with(['customer', 'assignedUser', 'assignedTeam'])
            ->where('company_id', $currentCompany->id);

        // Limited users can only see jobcards assigned to them or their teams
        if (auth()->user()->isLimitedUser()) {
            $teamIds = auth()->user()->teams()->pluck('teams.id');
            $query->where(function ($q) use ($teamIds) {
                $q->where('assigned_to_user_id', auth()->id());
                if ($teamIds->isNotEmpty()) {
                    $q->orWhereIn('assigned_to_team_id', $teamIds);
                }
            });
        }

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('assigned_to_user_id')) {
            $query->where('assigned_to_user_id', $request->assigned_to_user_id);
        }

        if ($request->filled('assigned_to_team_id')) {
            $query->where('assigned_to_team_id', $request->assigned_to_team_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('job_number', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($customerQuery) use ($search) {
                      $customerQuery->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $sortableFields = ['job_number', 'title', 'customer_name', 'status', 'due_date', 'total', 'created_at'];
        if (!in_array($sortBy, $sortableFields, true)) {
            $sortBy = 'created_at';
        }

        if ($sortBy === 'customer_name') {
            $query->orderBy(
                Customer::select('name')->whereColumn('customers.id', 'jobcards.customer_id')->limit(1),
                $sortDir
            );
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        $jobcards = $query->paginate(15)->withQueryString();
        $customers = Customer::where('company_id', $currentCompany->id)->orderBy('name')->get(['id', 'name', 'email', 'phone', 'account_code']);
        $users = User::whereHas('companies', function ($q) use ($currentCompany) {
            $q->where('company_id', $currentCompany->id);
        })->orWhereDoesntHave('companies')->orderBy('name')->get(['id', 'name']);
        $teams = Team::where('company_id', $currentCompany->id)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('jobcards/Index', [
            'jobcards' => $jobcards,
            'customers' => $customers,
            'users' => $users,
            'teams' => $teams,
            'filters' => [
                'status' => $request->input('status', ''),
                'customer_id' => $request->input('customer_id', ''),
                'assigned_to_user_id' => $request->input('assigned_to_user_id', ''),
                'assigned_to_team_id' => $request->input('assigned_to_team_id', ''),
                'search' => $request->input('search', ''),
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
            'currentCompany' => $currentCompany,
            'canEditCompleted' => auth()->user()->canEditCompletedJobcards(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $customers = Customer::where('company_id', $currentCompany->id)->orderBy('name')->get(['id', 'name']);
        $products = Product::where('company_id', $currentCompany->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'price', 'type']);
        $users = User::whereHas('companies', function ($q) use ($currentCompany) {
            $q->where('company_id', $currentCompany->id);
        })->orWhereDoesntHave('companies')->orderBy('name')->get(['id', 'name']);
        $teams = Team::where('company_id', $currentCompany->id)->orderBy('name')->get(['id', 'name']);
        $taxRates = TaxRate::where('company_id', $currentCompany->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'rate', 'is_default_sales']);
        $defaultSalesTaxRate = TaxRate::getDefaultSalesForCompany($currentCompany->id);
        $chartOfAccounts = ChartOfAccount::where('company_id', $currentCompany->id)->where('is_active', true)->ordered()->get(['id', 'account_code', 'account_name', 'account_type', 'is_default_sales']);
        $defaultSalesAccount = ChartOfAccount::getDefaultSalesForCompany($currentCompany->id);
        $defaultSalesCustomer = Customer::getDefaultSalesForCompany($currentCompany->id);

        return Inertia::render('jobcards/Create', [
            'customers' => $customers,
            'products' => $products,
            'users' => $users,
            'teams' => $teams,
            'taxRates' => $taxRates,
            'defaultSalesTaxRateId' => $defaultSalesTaxRate?->id,
            'chartOfAccounts' => $chartOfAccounts,
            'defaultSalesAccountId' => $defaultSalesAccount?->id,
            'defaultSalesCustomerId' => $defaultSalesCustomer?->id,
            'currentCompany' => $currentCompany,
            'defaultTerms' => $currentCompany->default_jobcard_terms,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'assigned_to_user_id' => ['nullable', 'exists:users,id'],
            'assigned_to_team_id' => ['nullable', 'exists:teams,id'],
            'order_number' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,pending,in_progress,completed,cancelled'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
            'terms_conditions' => ['nullable', 'string'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.product_id' => ['nullable', 'exists:products,id'],
            'line_items.*.description' => ['required', 'string', 'max:255'],
            'line_items.*.quantity' => ['required', 'integer', 'min:1'],
            'line_items.*.unit_price' => ['required', 'numeric'],
            'line_items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'line_items.*.discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'line_items.*.tax_rate_id' => ['nullable', 'exists:tax_rates,id'],
        ]);

        $validated['company_id'] = $currentCompany->id;
        $validated['job_number'] = Jobcard::generateJobNumber($currentCompany->id);
        $validated['order_number'] = $validated['order_number'] ?? null;
        $validated['contact_id'] = $validated['contact_id'] ?? null;
        $validated['email'] = $validated['email'] ?? null;
        $validated['phone'] = $validated['phone'] ?? null;
        $validated['tax_rate'] = $validated['tax_rate'] ?? 0;

        $jobcard = Jobcard::create($validated);

        // Create line items and deduct stock
        $stockService = new StockService();
        foreach ($validated['line_items'] as $index => $lineItemData) {
            $lineItem = new JobcardLineItem([
                'product_id' => $lineItemData['product_id'] ?? null,
                'description' => $lineItemData['description'],
                'quantity' => $lineItemData['quantity'],
                'unit_price' => $lineItemData['unit_price'],
                'discount_amount' => $lineItemData['discount_amount'] ?? 0,
                'discount_percentage' => $lineItemData['discount_percentage'] ?? 0,
                'tax_rate_id' => $lineItemData['tax_rate_id'] ?? null,
                'sort_order' => $index,
            ]);
            $lineItem->calculateTotal();
            $jobcard->lineItems()->save($lineItem);

            // Deduct stock if product is tracked
            if ($lineItemData['product_id']) {
                try {
                    $product = Product::find($lineItemData['product_id']);
                    if ($product && $product->track_stock) {
                        $stockService->removeStock(
                            $product,
                            $lineItemData['quantity'],
                            "Jobcard: {$jobcard->job_number}",
                            'jobcard',
                            $jobcard->id,
                            "Stock deducted for jobcard {$jobcard->job_number}"
                        );
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to deduct stock for jobcard line item', [
                        'jobcard_id' => $jobcard->id,
                        'product_id' => $lineItemData['product_id'],
                        'quantity' => $lineItemData['quantity'],
                        'error' => $e->getMessage(),
                    ]);
                    // Continue even if stock deduction fails
                }
            }
        }

        // Calculate totals
        $jobcard->calculateTotals();

        // Send automated reminder if enabled
        try {
            $reminderService = new ReminderService();
            $reminderService->sendJobcardCreatedConfirmation($jobcard);
        } catch (\Exception $e) {
            Log::error('Failed to send jobcard created confirmation', [
                'jobcard_id' => $jobcard->id,
                'error' => $e->getMessage(),
            ]);
            // Don't fail the jobcard creation if reminder fails
        }

        return redirect()->route('jobcards.show', $jobcard)
            ->with('success', 'Jobcard created successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(Jobcard $jobcard): Response
    {
        // Limited users can only view jobcards assigned to them or their teams
        if (auth()->user()->isLimitedUser()) {
            $teamIds = auth()->user()->teams()->pluck('teams.id')->toArray();
            $isAssignedToUser = $jobcard->assigned_to_user_id === auth()->id();
            $isAssignedToTeam = $jobcard->assigned_to_team_id && in_array($jobcard->assigned_to_team_id, $teamIds);
            if (!$isAssignedToUser && !$isAssignedToTeam) {
                abort(403, 'You do not have access to this jobcard.');
            }
        }

        $jobcard->load(['customer', 'contact', 'assignedUser', 'assignedTeam', 'lineItems.product', 'lineItems.taxRate', 'invoice', 'timeEntries.user']);

        $currentCompany = auth()->user()->getCurrentCompany();
        
        // Get available PDF templates for jobcards
        $pdfTemplates = \App\Models\PdfTemplate::where('company_id', $currentCompany->id)
            ->where('module', 'jobcard')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'is_default']);
        
        $defaultTemplateId = $pdfTemplates->where('is_default', true)->first()?->id ?? null;
        
        // Get running timer for current user and this jobcard
        $runningTimer = \App\Models\TimeEntry::getRunningEntry(auth()->id(), $jobcard->id);
        
        // Calculate time summary
        $timeSummary = [
            'total_hours' => $jobcard->timeEntries->sum('duration_minutes') / 60,
            'billable_hours' => $jobcard->timeEntries->where('is_billable', true)->sum('duration_minutes') / 60,
            'total_amount' => $jobcard->timeEntries->where('is_billable', true)->sum('total_amount'),
        ];
        
        return Inertia::render('jobcards/Show', [
            'jobcard' => $jobcard,
            'canEditCompleted' => auth()->user()->canEditCompletedJobcards(),
            'pdfTemplates' => $pdfTemplates,
            'defaultTemplateId' => $defaultTemplateId,
            'runningTimer' => $runningTimer,
            'timeSummary' => $timeSummary,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Jobcard $jobcard): Response
    {
        // Check if user can edit completed jobcards
        if ($jobcard->status === 'completed' && !auth()->user()->canEditCompletedJobcards()) {
            return redirect()->route('jobcards.show', $jobcard)
                ->with('error', 'You do not have permission to edit completed jobcards.');
        }

        $currentCompany = auth()->user()->getCurrentCompany();
        $customers = Customer::where('company_id', $currentCompany->id)->orderBy('name')->get(['id', 'name', 'email', 'phone', 'account_code']);
        $products = Product::where('company_id', $currentCompany->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'price', 'type']);
        $users = User::whereHas('companies', function ($q) use ($currentCompany) {
            $q->where('company_id', $currentCompany->id);
        })->orWhereDoesntHave('companies')->orderBy('name')->get(['id', 'name']);
        $teams = Team::where('company_id', $currentCompany->id)->orderBy('name')->get(['id', 'name']);
        $taxRates = TaxRate::where('company_id', $currentCompany->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'rate', 'is_default_sales']);
        $defaultSalesTaxRate = TaxRate::getDefaultSalesForCompany($currentCompany->id);
        $chartOfAccounts = ChartOfAccount::where('company_id', $currentCompany->id)->where('is_active', true)->ordered()->get(['id', 'account_code', 'account_name', 'account_type', 'is_default_sales']);
        $defaultSalesAccount = ChartOfAccount::getDefaultSalesForCompany($currentCompany->id);
        $jobcard->load(['customer', 'contact', 'lineItems']);

        return Inertia::render('jobcards/Edit', [
            'jobcard' => $jobcard,
            'customers' => $customers,
            'products' => $products,
            'users' => $users,
            'teams' => $teams,
            'taxRates' => $taxRates,
            'defaultSalesTaxRateId' => $defaultSalesTaxRate?->id,
            'chartOfAccounts' => $chartOfAccounts,
            'defaultSalesAccountId' => $defaultSalesAccount?->id,
            'currentCompany' => $currentCompany,
            'canEditCompleted' => auth()->user()->canEditCompletedJobcards(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Jobcard $jobcard): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'assigned_to_user_id' => ['nullable', 'exists:users,id'],
            'assigned_to_team_id' => ['nullable', 'exists:teams,id'],
            'order_number' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,pending,in_progress,completed,cancelled'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'completed_date' => ['nullable', 'date'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
            'terms_conditions' => ['nullable', 'string'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.id' => ['nullable', 'exists:jobcard_line_items,id'],
            'line_items.*.product_id' => ['nullable', 'exists:products,id'],
            'line_items.*.description' => ['required', 'string', 'max:255'],
            'line_items.*.quantity' => ['required', 'integer', 'min:1'],
            'line_items.*.unit_price' => ['required', 'numeric'],
            'line_items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'line_items.*.discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'line_items.*.tax_rate_id' => ['nullable', 'exists:tax_rates,id'],
            'line_items.*.account_id' => ['nullable', 'exists:chart_of_accounts,id'],
        ]);

        $validated['tax_rate'] = $validated['tax_rate'] ?? 0;

        $jobcard->update($validated);

        // Update line items
        $existingLineItemIds = [];
        foreach ($validated['line_items'] as $index => $lineItemData) {
            if (isset($lineItemData['id'])) {
                // Update existing line item
                $lineItem = JobcardLineItem::find($lineItemData['id']);
                $lineItem->update([
                    'product_id' => $lineItemData['product_id'] ?? null,
                    'description' => $lineItemData['description'],
                    'quantity' => $lineItemData['quantity'],
                    'unit_price' => $lineItemData['unit_price'],
                    'discount_amount' => $lineItemData['discount_amount'] ?? 0,
                    'discount_percentage' => $lineItemData['discount_percentage'] ?? 0,
                    'tax_rate_id' => $lineItemData['tax_rate_id'] ?? null,
                    'account_id' => $lineItemData['account_id'] ?? null,
                    'sort_order' => $index,
                ]);
                $lineItem->calculateTotal();
                $lineItem->save();
                $existingLineItemIds[] = $lineItem->id;
            } else {
                // Create new line item
                $lineItem = new JobcardLineItem([
                    'product_id' => $lineItemData['product_id'] ?? null,
                    'description' => $lineItemData['description'],
                    'quantity' => $lineItemData['quantity'],
                    'unit_price' => $lineItemData['unit_price'],
                    'discount_amount' => $lineItemData['discount_amount'] ?? 0,
                    'discount_percentage' => $lineItemData['discount_percentage'] ?? 0,
                    'tax_rate_id' => $lineItemData['tax_rate_id'] ?? null,
                    'account_id' => $lineItemData['account_id'] ?? null,
                    'sort_order' => $index,
                ]);
                $lineItem->calculateTotal();
                $jobcard->lineItems()->save($lineItem);
                $existingLineItemIds[] = $lineItem->id;
            }
        }

        // Delete removed line items
        $jobcard->lineItems()->whereNotIn('id', $existingLineItemIds)->delete();

        // Calculate totals
        $jobcard->calculateTotals();

        return redirect()->route('jobcards.show', $jobcard)
            ->with('success', 'Jobcard updated successfully');
    }

    /**
     * Update the status of the specified jobcard.
     */
    public function updateStatus(Request $request, Jobcard $jobcard): RedirectResponse
    {
        // Limited users can only update status on jobcards assigned to them or their teams
        if (auth()->user()->isLimitedUser()) {
            $teamIds = auth()->user()->teams()->pluck('teams.id')->toArray();
            $isAssignedToUser = $jobcard->assigned_to_user_id === auth()->id();
            $isAssignedToTeam = $jobcard->assigned_to_team_id && in_array($jobcard->assigned_to_team_id, $teamIds);
            if (!$isAssignedToUser && !$isAssignedToTeam) {
                abort(403, 'You do not have access to this jobcard.');
            }
        }

        $request->validate([
            'status' => 'required|in:draft,pending,in_progress,completed,cancelled',
        ]);

        $newStatus = $request->status;
        
        // Check if user can edit completed jobcards
        if ($jobcard->status === 'completed' && $newStatus !== 'completed') {
            if (!auth()->user()->canEditCompletedJobcards()) {
                return redirect()->back()
                    ->with('error', 'You do not have permission to edit completed jobcards.');
            }
        }

        $oldStatus = $jobcard->status;
        $jobcard->status = $newStatus;
        
        // Set completed_date if status is completed
        if ($newStatus === 'completed' && !$jobcard->completed_date) {
            $jobcard->completed_date = now();
        }
        
        $jobcard->save();

        // Send automated reminder if enabled and status actually changed
        if ($oldStatus !== $newStatus) {
            try {
                $reminderService = new ReminderService();
                $reminderService->sendJobcardStatusUpdatedConfirmation($jobcard, $oldStatus);
            } catch (\Exception $e) {
                Log::error('Failed to send jobcard status updated confirmation', [
                    'jobcard_id' => $jobcard->id,
                    'error' => $e->getMessage(),
                ]);
                // Don't fail the status update if reminder fails
            }
        }

        return redirect()->back()
            ->with('success', "Jobcard status updated to {$newStatus}");
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Jobcard $jobcard): RedirectResponse
    {
        // Check if user can delete completed jobcards
        if ($jobcard->status === 'completed' && !auth()->user()->canEditCompletedJobcards()) {
            return redirect()->back()
                ->with('error', 'You do not have permission to delete completed jobcards.');
        }

        $jobcard->delete();

        return redirect()->route('jobcards.index')
            ->with('success', 'Jobcard deleted successfully');
    }

    /**
     * Generate and download a PDF version of the jobcard.
     */
    public function print(Request $request, Jobcard $jobcard)
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        // Ensure the jobcard belongs to the current company
        if ($jobcard->company_id !== $currentCompany->id) {
            abort(403, 'Unauthorized access to jobcard.');
        }

        $jobcard->load(['customer', 'contact', 'lineItems.product', 'lineItems.taxRate', 'company']);
        $templateId = $request->get('template_id');

        $pdfService = new \App\Services\PdfGenerationService();
        $pdf = $pdfService->generatePdf('jobcard', [
            'jobcard' => $jobcard,
            'company' => $currentCompany,
        ], $currentCompany, $templateId);

        $filename = 'jobcard-' . $jobcard->job_number . '.pdf';
        
        return $pdf->download($filename);
    }

    /**
     * Send jobcard via email.
     */
    public function email(Request $request, Jobcard $jobcard): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        // Ensure the jobcard belongs to the current company
        if ($jobcard->company_id !== $currentCompany->id) {
            abort(403, 'Unauthorized access to jobcard.');
        }

        $validated = $request->validate([
            'email' => ['required', 'string'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
        ]);

        $emails = array_unique(array_filter(array_map('trim', explode(',', $validated['email']))));
        foreach ($emails as $e) {
            if (!filter_var($e, FILTER_VALIDATE_EMAIL)) {
                return redirect()->back()->withErrors(['email' => "Invalid email address: {$e}"]);
            }
        }
        if (empty($emails)) {
            return redirect()->back()->withErrors(['email' => 'At least one valid email is required.']);
        }

        $user = auth()->user();
        
        // Check if user has SMTP settings configured
        if (!$user->smtp_host || !$user->smtp_username || !$user->smtp_password) {
            return redirect()->back()
                ->withErrors(['message' => 'Please configure your SMTP settings in your user profile to send emails.']);
        }

        try {
            // Configure mail using user's SMTP settings
            Config::set([
                'mail.mailers.smtp.host' => $user->smtp_host,
                'mail.mailers.smtp.port' => $user->smtp_port ?? 587,
                'mail.mailers.smtp.username' => $user->smtp_username,
                'mail.mailers.smtp.password' => $user->smtp_password,
                'mail.mailers.smtp.encryption' => $user->smtp_encryption ?? 'tls',
            ]);

            // Log the SMTP configuration for debugging
            \Log::info('Email Configuration', [
                'host' => $user->smtp_host,
                'port' => $user->smtp_port ?? 587,
                'username' => $user->smtp_username,
                'encryption' => $user->smtp_encryption ?? 'tls',
                'from_email' => $user->smtp_from_email ?? $user->email,
                'from_name' => $user->smtp_from_name ?? $user->name,
                'to_email' => $emails,
            ]);

            $jobcard->load(['customer', 'contact', 'lineItems.product', 'lineItems.taxRate', 'company']);

            $subject = $validated['subject'] ?? "Jobcard #{$jobcard->job_number} - {$jobcard->title}";
            $fromEmail = $user->smtp_from_email ?? $user->email;
            $fromName = $user->smtp_from_name ?? $user->name;

            // Generate PDF
            $templateId = $request->get('template_id');
            
            $pdfService = new \App\Services\PdfGenerationService();
            $pdf = $pdfService->generatePdf('jobcard', [
                'jobcard' => $jobcard,
                'company' => $currentCompany,
                'customMessage' => $validated['message'] ?? '',
            ], $currentCompany, $templateId);

            $filename = 'jobcard-' . $jobcard->job_number . '.pdf';

            Mail::mailer('smtp')->send('emails.jobcard-pdf', [
                'jobcard' => $jobcard,
                'company' => $currentCompany,
                'customMessage' => $validated['message'] ?? '',
            ], function ($message) use ($emails, $subject, $fromEmail, $fromName, $pdf, $filename, $currentCompany) {
                $message->to($emails)
                    ->subject($subject)
                    ->from($fromEmail, $fromName)
                    ->attachData($pdf->output(), $filename, [
                        'mime' => 'application/pdf',
                    ]);

                if (!empty($currentCompany->email)) {
                    $message->replyTo($currentCompany->email, $currentCompany->name ?? null);
                }
            });

            \Log::info('Email sent successfully', [
                'to' => $emails,
                'subject' => $subject,
                'from' => $fromEmail,
            ]);

            return redirect()->back()
                ->with('success', 'Jobcard sent successfully to ' . implode(', ', $emails));

        } catch (\Exception $e) {
            \Log::error('Email sending failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'smtp_host' => $user->smtp_host,
                'smtp_port' => $user->smtp_port,
                'smtp_username' => $user->smtp_username,
                'to_email' => $emails,
            ]);
            
            return redirect()->back()
                ->withErrors(['message' => 'Failed to send email: ' . $e->getMessage()]);
        }
    }

    /**
     * Convert jobcard to invoice
     */
    public function convertToInvoice(Jobcard $jobcard): RedirectResponse
    {
        // Log the start of conversion
        \Log::info('Jobcard conversion started', [
            'jobcard_id' => $jobcard->id,
            'current_invoice_id' => $jobcard->invoice_id,
            'user_id' => auth()->id(),
            'request_method' => request()->method(),
            'request_url' => request()->url()
        ]);
        
        $currentCompany = auth()->user()->getCurrentCompany();
        
        // Ensure the jobcard belongs to the current company
        if ($jobcard->company_id !== $currentCompany->id) {
            \Log::error('Jobcard conversion failed - company mismatch', [
                'jobcard_id' => $jobcard->id,
                'jobcard_company_id' => $jobcard->company_id,
                'current_company_id' => $currentCompany->id
            ]);
            abort(403, 'Unauthorized access to jobcard.');
        }

        try {
            $invoice = $jobcard->convertToInvoice();
            
            // Update jobcard to link to invoice and set status to completed
            $oldStatus = $jobcard->status;
            $updated = $jobcard->update([
                'invoice_id' => $invoice->id,
                'status' => 'completed',
                'completed_date' => now(),
            ]);
            
            // Refresh the jobcard to get updated data
            $jobcard->refresh();
            
            // Send invoice created notification if enabled
            // Load invoice relationships needed for notifications
            $invoice->load('customer', 'company');
            try {
                $reminderService = new ReminderService();
                $reminderService->sendInvoiceCreatedConfirmation($invoice);
            } catch (\Exception $e) {
                Log::error('Failed to send invoice created confirmation after jobcard conversion', [
                    'invoice_id' => $invoice->id,
                    'jobcard_id' => $jobcard->id,
                    'error' => $e->getMessage(),
                ]);
                // Don't fail the conversion if reminder fails
            }
            
            // Send jobcard status updated notification if status changed
            if ($oldStatus !== 'completed') {
                try {
                    $reminderService = new ReminderService();
                    $reminderService->sendJobcardStatusUpdatedConfirmation($jobcard, $oldStatus);
                } catch (\Exception $e) {
                    Log::error('Failed to send jobcard status updated confirmation after conversion', [
                        'jobcard_id' => $jobcard->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Don't fail the conversion if reminder fails
                }
            }
            
            // Log for debugging
            \Log::info('Jobcard conversion completed', [
                'jobcard_id' => $jobcard->id,
                'invoice_id' => $invoice->id,
                'update_success' => $updated,
                'jobcard_invoice_id_after' => $jobcard->invoice_id,
                'jobcard_status' => $jobcard->status
            ]);
            
            return redirect()->route('invoices.show', $invoice)
                ->with('success', 'Jobcard converted to invoice successfully');
        } catch (\Exception $e) {
            \Log::error('Jobcard conversion failed', [
                'jobcard_id' => $jobcard->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->back()
                ->withErrors(['message' => 'Failed to convert jobcard to invoice: ' . $e->getMessage()]);
        }
    }
}

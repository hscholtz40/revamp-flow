<?php

namespace App\Http\Controllers;

use App\Exceptions\JobQueryNotActionableException;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\GoogleIntegrationSettings;
use App\Models\Jobcard;
use App\Models\JobcardLineItem;
use App\Models\LineGroup;
use App\Models\Note;
use App\Models\Query;
use App\Services\CustomerUpsertService;
use App\Services\JobQueryService;
use App\Services\RevampWebhookService;
use App\Support\CompanyMailer;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class QueriesController extends Controller
{
    private const PUBLIC_FORM_HONEYPOT_FIELD = 'website';

    /**
     * Store a query submitted from an external site (e.g. the Revamp marketing
     * landing page) via the public query API. Authenticated by a shared API key
     * (query.api.auth middleware); see routes/api.php → api.queries.store.
     */
    public function apiStore(Request $request): JsonResponse
    {
        $this->normalizeCompanyWebsiteInput($request);
        $this->normalizeSouthAfricanPhoneInput($request, 'company_contact_number');
        $this->normalizeSouthAfricanPhoneInput($request, 'cell');

        $validated = $request->validate([
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc,filter', 'max:255'],
            'cell' => ['required', 'string', 'size:10', 'regex:/^0[6-8][0-9]{8}$/'],
            'description' => ['required', 'string', 'max:5000'],
            'kind' => ['nullable', 'in:enquiry,contractor'],
            'company_name' => ['nullable', 'required_if:kind,contractor', 'string', 'max:255'],
            'company_registration_no' => ['nullable', 'required_if:kind,contractor', 'string', 'max:255'],
            'company_address' => ['nullable', 'required_if:kind,contractor', 'string', 'max:500'],
            'company_city' => ['nullable', 'required_if:kind,contractor', 'string', 'max:100'],
            'company_province' => ['nullable', 'required_if:kind,contractor', 'string', 'max:100'],
            'company_email' => ['nullable', 'required_if:kind,contractor', 'email:rfc,filter', 'max:255'],
            'company_contact_number' => ['nullable', 'required_if:kind,contractor', 'string', 'size:10', 'regex:/^0[1-9][0-9]{8}$/'],
            'website_status' => ['nullable', 'required_if:kind,contractor', 'in:have_website,need_website'],
            'company_website' => ['nullable', 'required_if:website_status,have_website', 'url', 'max:255'],
            'selected_package' => ['nullable', 'required_if:kind,contractor', 'in:option_1,option_2,custom'],
            'document_company_ck' => ['nullable', 'required_if:kind,contractor', 'file', 'max:51200', 'mimes:pdf,jpg,jpeg,png'],
            'document_proof_of_residence' => ['nullable', 'required_if:kind,contractor', 'file', 'max:51200', 'mimes:pdf,jpg,jpeg,png'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:51200', 'mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi,webm'],
        ], [
            'attachments.max' => 'You can upload a maximum of 10 files.',
            'attachments.*.max' => 'Each file may not be larger than 50MB.',
            'attachments.*.mimes' => 'Each file must be an image or video.',
            'document_company_ck.required_if' => 'The Company CK document is required.',
            'document_proof_of_residence.required_if' => 'The proof of residence for company document is required.',
            'document_company_ck.mimes' => 'Company CK must be a PDF or image (JPG/PNG).',
            'document_proof_of_residence.mimes' => 'Proof of residence must be a PDF or image (JPG/PNG).',
            'selected_package.required_if' => 'Please select a package.',
            'website_status.required_if' => 'Please select a website option.',
            'company_website.required_if' => 'The company website URL is required.',
            'company_city.required_if' => 'The company city field is required.',
            'company_province.required_if' => 'The company province field is required.',
            'email.email' => 'Enter a valid email address, e.g. name@example.com.',
            'company_email.email' => 'Enter a valid company email address, e.g. hello@example.com.',
            'cell.size' => 'The cell number must be a 10-digit South African mobile number.',
            'cell.regex' => 'Enter a valid South African mobile number, e.g. 0821234567.',
            'company_contact_number.size' => 'The company contact number must be a 10-digit South African number.',
            'company_contact_number.regex' => 'Enter a valid South African contact number, e.g. 0211234567 or 0821234567.',
            ...$this->contractorFieldRequiredMessages(),
        ]);

        // Route to the requested active company, or fall back to the default company.
        $company = ! empty($validated['company_id'])
            ? Company::where('id', $validated['company_id'])->where('is_active', true)->first()
            : Company::getDefault();

        abort_if($company === null, 404, 'No company is available to receive queries.');

        $kind = (string) ($validated['kind'] ?? Query::KIND_ENQUIRY);
        if ($kind === Query::KIND_CONTRACTOR && ! config('app.is_licensing_instance')) {
            abort(403, 'Contractor queries are only available on licensing instances.');
        }

        $websiteStatus = $validated['website_status'] ?? null;
        $companyWebsite = $websiteStatus === Query::WEBSITE_STATUS_HAVE
            ? ($validated['company_website'] ?? null)
            : null;

        $query = Query::create([
            'company_id' => $company->id,
            'kind' => $kind,
            'name' => $validated['name'],
            'surname' => $validated['surname'],
            'email' => $validated['email'],
            'cell' => $validated['cell'],
            'description' => $validated['description'],
            'company_name' => $validated['company_name'] ?? null,
            'company_registration_no' => $validated['company_registration_no'] ?? null,
            'company_address' => $validated['company_address'] ?? null,
            'company_city' => $validated['company_city'] ?? null,
            'company_province' => $validated['company_province'] ?? null,
            'company_email' => $validated['company_email'] ?? null,
            'company_contact_number' => $validated['company_contact_number'] ?? null,
            'company_website' => $companyWebsite,
            'website_status' => $websiteStatus,
            'selected_package' => $validated['selected_package'] ?? null,
            'status' => Query::STATUS_OPEN,
            'response' => $kind === Query::KIND_CONTRACTOR ? Query::RESPONSE_PENDING : null,
        ]);

        if ($kind === Query::KIND_CONTRACTOR) {
            $this->storeContractorDocument($query, $request->file('document_company_ck'), 'company_ck');
            $this->storeContractorDocument($query, $request->file('document_proof_of_residence'), 'proof_of_residence');
        }

        foreach ((array) $request->file('attachments', []) as $file) {
            $query->attachments()->create([
                'path' => $file->store('query-attachments', 'public'),
                'type' => str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'image',
                'original_name' => $file->getClientOriginalName(),
            ]);
        }

        $this->notifyCompanyOfNewQuery($company, $query);

        return response()->json([
            'message' => 'Your query has been submitted. We will get back to you shortly.',
            'id' => $query->id,
        ], 201);
    }

    /**
     * Hosted public query form that can be used directly or embedded in an iframe.
     */
    public function publicForm(Request $request, int $companyId, string $token): ViewContract
    {
        $company = Company::query()->whereKey($companyId)->firstOrFail();
        abort_unless($company->is_active, 404);
        abort_unless($this->isValidPublicFormToken($company, $token), 403, 'Invalid form token.');
        $kind = (string) $request->query('kind', Query::KIND_ENQUIRY);
        if (! in_array($kind, [Query::KIND_ENQUIRY, Query::KIND_CONTRACTOR], true)) {
            $kind = Query::KIND_ENQUIRY;
        }
        if ($kind === Query::KIND_CONTRACTOR && ! config('app.is_licensing_instance')) {
            abort(403, 'Contractor queries are only available on licensing instances.');
        }

        return view('public.query-form', [
            'company' => $company,
            'token' => $token,
            'kind' => $kind,
            'submitted' => (bool) $request->boolean('submitted'),
            'errors' => session('errors'),
            'googleMapsApiKey' => GoogleIntegrationSettings::mapsApiKey(),
        ]);
    }

    /**
     * Process hosted public form submissions.
     */
    public function publicStore(Request $request, int $companyId, string $token): RedirectResponse
    {
        $company = Company::query()->whereKey($companyId)->firstOrFail();
        abort_unless($company->is_active, 404);
        abort_unless($this->isValidPublicFormToken($company, $token), 403, 'Invalid form token.');

        $this->normalizeCompanyWebsiteInput($request);
        $this->normalizeSouthAfricanPhoneInput($request, 'company_contact_number');
        $this->normalizeSouthAfricanPhoneInput($request, 'cell');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc,filter', 'max:255'],
            'cell' => ['required', 'string', 'size:10', 'regex:/^0[6-8][0-9]{8}$/'],
            'description' => ['required', 'string', 'max:5000'],
            'kind' => ['nullable', 'in:enquiry,contractor'],
            'company_name' => ['nullable', 'required_if:kind,contractor', 'string', 'max:255'],
            'company_registration_no' => ['nullable', 'required_if:kind,contractor', 'string', 'max:255'],
            'company_address' => ['nullable', 'required_if:kind,contractor', 'string', 'max:500'],
            'company_city' => ['nullable', 'required_if:kind,contractor', 'string', 'max:100'],
            'company_province' => ['nullable', 'required_if:kind,contractor', 'string', 'max:100'],
            'company_email' => ['nullable', 'required_if:kind,contractor', 'email:rfc,filter', 'max:255'],
            'company_contact_number' => ['nullable', 'required_if:kind,contractor', 'string', 'size:10', 'regex:/^0[1-9][0-9]{8}$/'],
            'website_status' => ['nullable', 'required_if:kind,contractor', 'in:have_website,need_website'],
            'company_website' => ['nullable', 'required_if:website_status,have_website', 'url', 'max:255'],
            'selected_package' => ['nullable', 'required_if:kind,contractor', 'in:option_1,option_2,custom'],
            'document_company_ck' => ['nullable', 'required_if:kind,contractor', 'file', 'max:51200', 'mimes:pdf,jpg,jpeg,png'],
            'document_proof_of_residence' => ['nullable', 'required_if:kind,contractor', 'file', 'max:51200', 'mimes:pdf,jpg,jpeg,png'],
            self::PUBLIC_FORM_HONEYPOT_FIELD => ['nullable', 'max:0'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:51200', 'mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi,webm'],
        ], [
            'attachments.max' => 'You can upload a maximum of 10 files.',
            'attachments.*.max' => 'Each file may not be larger than 50MB.',
            'attachments.*.mimes' => 'Each file must be an image or video.',
            'document_company_ck.required_if' => 'The Company CK document is required.',
            'document_proof_of_residence.required_if' => 'The proof of residence for company document is required.',
            'document_company_ck.mimes' => 'Company CK must be a PDF or image (JPG/PNG).',
            'document_proof_of_residence.mimes' => 'Proof of residence must be a PDF or image (JPG/PNG).',
            'selected_package.required_if' => 'Please select a package.',
            'website_status.required_if' => 'Please select a website option.',
            'company_website.required_if' => 'The company website URL is required.',
            'company_city.required_if' => 'The company city field is required.',
            'company_province.required_if' => 'The company province field is required.',
            'email.email' => 'Enter a valid email address, e.g. name@example.com.',
            'company_email.email' => 'Enter a valid company email address, e.g. hello@example.com.',
            'cell.size' => 'The cell number must be a 10-digit South African mobile number.',
            'cell.regex' => 'Enter a valid South African mobile number, e.g. 0821234567.',
            'company_contact_number.size' => 'The company contact number must be a 10-digit South African number.',
            'company_contact_number.regex' => 'Enter a valid South African contact number, e.g. 0211234567 or 0821234567.',
            ...$this->contractorFieldRequiredMessages(),
        ]);

        $kind = (string) ($validated['kind'] ?? Query::KIND_ENQUIRY);
        if ($kind === Query::KIND_CONTRACTOR && ! config('app.is_licensing_instance')) {
            abort(403, 'Contractor queries are only available on licensing instances.');
        }

        $websiteStatus = $validated['website_status'] ?? null;
        $companyWebsite = $websiteStatus === Query::WEBSITE_STATUS_HAVE
            ? ($validated['company_website'] ?? null)
            : null;

        $query = Query::create([
            'company_id' => $company->id,
            'kind' => $kind,
            'name' => $validated['name'],
            'surname' => $validated['surname'],
            'email' => $validated['email'],
            'cell' => $validated['cell'],
            'description' => $validated['description'],
            'company_name' => $validated['company_name'] ?? null,
            'company_registration_no' => $validated['company_registration_no'] ?? null,
            'company_address' => $validated['company_address'] ?? null,
            'company_city' => $validated['company_city'] ?? null,
            'company_province' => $validated['company_province'] ?? null,
            'company_email' => $validated['company_email'] ?? null,
            'company_contact_number' => $validated['company_contact_number'] ?? null,
            'company_website' => $companyWebsite,
            'website_status' => $websiteStatus,
            'selected_package' => $validated['selected_package'] ?? null,
            'status' => Query::STATUS_OPEN,
            'response' => $kind === Query::KIND_CONTRACTOR ? Query::RESPONSE_PENDING : null,
        ]);

        if ($kind === Query::KIND_CONTRACTOR) {
            $this->storeContractorDocument($query, $request->file('document_company_ck'), 'company_ck');
            $this->storeContractorDocument($query, $request->file('document_proof_of_residence'), 'proof_of_residence');
        }

        foreach ((array) $request->file('attachments', []) as $file) {
            $query->attachments()->create([
                'path' => $file->store('query-attachments', 'public'),
                'type' => str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'image',
                'original_name' => $file->getClientOriginalName(),
            ]);
        }

        $this->notifyCompanyOfNewQuery($company, $query);

        return redirect()
            ->route('queries.public.form', [
                'companyId' => $company->id,
                'token' => $token,
                'kind' => $kind,
                'submitted' => 1,
            ]);
    }

    /**
     * Embeddable script that injects the hosted form in an iframe.
     */
    public function embedScript(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $companyId = (int) $request->query('company', 0);
        $token = (string) $request->query('token', '');
        $kind = (string) $request->query('kind', Query::KIND_ENQUIRY);
        if (! in_array($kind, [Query::KIND_ENQUIRY, Query::KIND_CONTRACTOR], true)) {
            $kind = Query::KIND_ENQUIRY;
        }
        $height = (int) $request->query('height', $kind === Query::KIND_CONTRACTOR ? 1600 : 820);
        $height = max(500, min(1800, $height));

        abort_unless($companyId > 0 && $token !== '', 422, 'Missing embed parameters.');

        $company = Company::query()->whereKey($companyId)->where('is_active', true)->firstOrFail();
        abort_unless($this->isValidPublicFormToken($company, $token), 403, 'Invalid form token.');
        if ($kind === Query::KIND_CONTRACTOR && ! config('app.is_licensing_instance')) {
            abort(403, 'Contractor queries are only available on licensing instances.');
        }

        $formUrl = route('queries.public.form', ['companyId' => $company->id, 'token' => $token]).'?kind='.$kind;
        $script = <<<JS
(function () {
  var script = document.currentScript;
  if (!script) return;
  var iframe = document.createElement('iframe');
  iframe.src = '{$formUrl}';
  iframe.width = '100%';
  iframe.height = '{$height}';
  iframe.style.border = '0';
  iframe.style.maxWidth = '100%';
  iframe.loading = 'lazy';
  iframe.referrerPolicy = 'strict-origin-when-cross-origin';
  iframe.title = 'Query form';
  script.parentNode.insertBefore(iframe, script);
})();
JS;

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * Display a listing of queries for the current company.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Query::class);

        $currentCompany = auth()->user()->getCurrentCompany();

        $status = $request->string('status')->toString();
        if (! in_array($status, [Query::STATUS_OPEN, Query::STATUS_CLOSED], true)) {
            $status = '';
        }

        if (! $currentCompany) {
            return Inertia::render('queries/Index', [
                'queries' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15),
                'filters' => ['search' => '', 'status' => $status],
                'counts' => ['open' => 0, 'closed' => 0],
            ]);
        }

        $base = Query::where('company_id', $currentCompany->id);

        $queries = (clone $base)
            ->withCount('attachments')
            ->when($request->string('search')->toString(), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('surname', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('cell', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('queries/Index', [
            'queries' => $queries,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $status,
            ],
            'counts' => [
                'open' => (clone $base)->where('status', Query::STATUS_OPEN)->count(),
                'closed' => (clone $base)->where('status', Query::STATUS_CLOSED)->count(),
            ],
            'integration' => $this->buildIntegrationPayload($currentCompany),
        ]);
    }

    /**
     * Display the specified query.
     */
    public function show(Query $query): Response
    {
        $this->authorize('view', $query);

        $query->load('attachments');

        return Inertia::render('queries/Show', [
            'query' => [
                'id' => $query->id,
                'kind' => $query->kind,
                'name' => $query->name,
                'surname' => $query->surname,
                'email' => $query->email,
                'cell' => $query->cell,
                'description' => $query->description,
                'company_name' => $query->company_name,
                'company_registration_no' => $query->company_registration_no,
                'company_address' => $query->company_address,
                'company_city' => $query->company_city,
                'company_province' => $query->company_province,
                'company_email' => $query->company_email,
                'company_contact_number' => $query->company_contact_number,
                'company_website' => $query->company_website,
                'website_status' => $query->website_status,
                'website_status_label' => $query->websiteStatusLabel(),
                'selected_package' => $query->selected_package,
                'selected_package_label' => $query->selectedPackageLabel(),
                'status' => $query->status,
                'created_at' => $query->created_at?->toIso8601String(),
                // Job fields (null for public enquiries). For job queries these
                // carry the read-only quote the contractor accepts/declines.
                'response' => $query->response,
                'responded_at' => $query->responded_at?->toIso8601String(),
                'external_source' => $query->external_source,
                'external_quote_id' => $query->external_quote_id,
                'job_location' => $query->job_location,
                'job_latitude' => $query->job_latitude,
                'job_longitude' => $query->job_longitude,
                'quote_line_items' => $query->quote_line_items,
                'quote_total_amount' => $query->quote_total_amount,
                'quote_client_email' => $query->quote_client_email,
                'quote_client_phone' => $query->quote_client_phone,
                'accepted_at' => $query->accepted_at?->toIso8601String(),
                'accepted_customer_id' => $query->accepted_customer_id,
                'accepted_contact_id' => $query->accepted_contact_id,
                'attachments' => $query->attachments->map(fn ($attachment) => [
                    'id' => $attachment->id,
                    'url' => '/storage/'.ltrim((string) $attachment->path, '/'),
                    'type' => $attachment->type,
                    'original_name' => $attachment->original_name,
                ])->values(),
            ],
        ]);
    }

    /**
     * Accept a dispatched job query (first-accept-wins). The other contractors'
     * pending copies are expired by JobQueryService.
     */
    public function accept(Query $query, JobQueryService $service, RevampWebhookService $webhook): RedirectResponse
    {
        $this->authorize('update', $query);
        abort_unless($query->isJob(), 404);

        try {
            $service->accept($query);
        } catch (JobQueryNotActionableException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        // Push status update to Revamp (fire-and-forget).
        if ($query->external_source === 'revamp') {
            $webhook->notifyStatusUpdate($query);
        }

        return redirect()->back()->with('success', 'Job accepted. It has been assigned to your company.');
    }

    /**
     * Convert an accepted Revamp quote query into a jobcard.
     *
     * Finds or auto-creates a Customer from the query's client details,
     * then creates a Jobcard with line items sourced from the quote JSON.
     */
    public function convertToJobcard(Query $query, CustomerUpsertService $customerUpsert): RedirectResponse
    {
        $this->authorize('update', $query);
        abort_unless($query->isJob(), 404);
        abort_unless($query->response === Query::RESPONSE_ACCEPTED, 400, 'This query has not been accepted yet.');
        abort_if(empty($query->quote_line_items), 400, 'This query has no quote line items to convert.');

        $currentCompany = auth()->user()->getCurrentCompany();
        abort_if($currentCompany === null, 403, 'No active company.');

        // Check if already converted
        $existingJobcard = Jobcard::where('company_id', $currentCompany->id)
            ->where('source_type', 'query')
            ->where('source_id', $query->id)
            ->first();
        if ($existingJobcard) {
            return redirect()->route('jobcards.show', $existingJobcard)
                ->with('info', 'This query has already been converted to a jobcard.');
        }

        // Find or create customer from the query's client details
        $clientEmail = $query->quote_client_email ?? $query->email;
        $clientName = trim($query->name . ' ' . $query->surname);

        $customer = null;
        if ($clientEmail) {
            $customer = Customer::where('company_id', $currentCompany->id)
                ->where('email', $clientEmail)
                ->first();
        }

        if (! $customer) {
            $customer = $customerUpsert->quickCreateForCompany([
                'name' => $clientName ?: 'Revamp Client',
                'email' => $clientEmail ?? '',
                'phone' => $query->quote_client_phone ?? $query->cell ?? null,
            ], $currentCompany->id);
        }

        $jobcard = DB::transaction(function () use ($query, $currentCompany, $customer) {
            $jobcard = Jobcard::create([
                'company_id' => $currentCompany->id,
                'customer_id' => $customer->id,
                'email' => $query->quote_client_email ?? $query->email,
                'phone' => $query->quote_client_phone ?? $query->cell,
                'service_address' => $query->job_location,
                'job_number' => Jobcard::generateJobNumber($currentCompany->id),
                'title' => 'Quote from Revamp - ' . $query->external_quote_id,
                'description' => $query->description,
                'status' => 'new',
                'total' => $query->quote_total_amount ?? 0,
                'source_type' => 'query',
                'source_id' => $query->id,
            ]);

            // Create default line group
            $defaultGroup = LineGroup::createDefaultFor($jobcard);

            // Create line items from the quote JSON
            $lineItems = $query->quote_line_items ?? [];
            $sortOrder = 0;
            foreach ($lineItems as $item) {
                JobcardLineItem::create([
                    'jobcard_id' => $jobcard->id,
                    'line_group_id' => $defaultGroup->id,
                    'description' => $item['description'] ?? '',
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_price' => $item['unit_price'] ?? 0,
                    'total' => $item['line_total'] ?? ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0),
                    'sort_order' => $sortOrder++,
                ]);
            }

            $jobcard->calculateTotals();

            // Extract only the "Notes: ..." portion from the bottom of the description
            $noteContent = null;
            if (preg_match('/\nNotes:\s*(.+)$/s', $query->description, $m)) {
                $noteContent = trim($m[1]);
            }

            if ($noteContent !== null) {
                $note = new Note([
                    'company_id' => $currentCompany->id,
                    'user_id' => auth()->id(),
                    'subject' => 'Notes',
                    'description' => $noteContent,
                ]);
                $note->noteable()->associate($jobcard);
                $note->save();
            }

            return $jobcard;
        });

        return redirect()->route('jobcards.show', $jobcard)
            ->with('success', 'Jobcard created from Revamp quote. Customer linked: ' . $customer->name);
    }

    /**
     * Decline a dispatched job query for this contractor only.
     */
    public function decline(Query $query, JobQueryService $service, RevampWebhookService $webhook): RedirectResponse
    {
        $this->authorize('update', $query);
        abort_unless($query->isJob(), 404);

        try {
            $service->decline($query);
        } catch (JobQueryNotActionableException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        // Push status update to Revamp (fire-and-forget).
        if ($query->external_source === 'revamp') {
            $webhook->notifyStatusUpdate($query);
        }

        return redirect()->back()->with('success', 'Job declined.');
    }

    /**
     * Accept a contractor onboarding query and create a customer + linked contact.
     */
    public function acceptContractor(Query $query): RedirectResponse
    {
        $this->authorize('update', $query);
        abort_unless($query->isContractorQuery(), 404);
        abort_if($query->response && $query->response !== Query::RESPONSE_PENDING, 422, 'This contractor query is no longer actionable.');

        $companyId = (int) $query->company_id;

        [$customer, $contact] = DB::transaction(function () use ($query, $companyId) {
            $customerEmail = $query->company_email ?: $query->email;
            $customerPhone = $query->company_contact_number ?: $query->cell;
            $customerName = trim((string) ($query->company_name ?: ($query->name.' '.$query->surname)));

            $customer = Customer::query()
                ->where('company_id', $companyId)
                ->when($customerEmail, fn ($q) => $q->where('email', $customerEmail), fn ($q) => $q->where('name', $customerName))
                ->first();

            if (! $customer) {
                $customer = Customer::create([
                    'company_id' => $companyId,
                    'name' => $customerName !== '' ? $customerName : 'Contractor Prospect',
                    'registration_number' => $query->company_registration_no ?: null,
                    'email' => $customerEmail ?: null,
                    'company_tel' => $customerPhone ?: null,
                    'phone' => $customerPhone ?: null,
                    'address' => $query->company_address ?: null,
                    'contact_first_name' => $query->name ?: null,
                    'contact_last_name' => $query->surname ?: null,
                    'contact_cell' => $query->cell ?: null,
                    'contact_email' => $query->email ?: null,
                    'notes' => trim(collect([
                        $query->company_website ? 'Website: '.$query->company_website : null,
                        'Source Query #'.$query->id,
                    ])->filter()->implode("\n")),
                    'account_code' => Customer::generateAccountCode($customerName !== '' ? $customerName : 'Contractor', $companyId),
                ]);
            }

            $contactName = trim($query->name.' '.$query->surname);
            $contact = Contact::query()
                ->where('company_id', $companyId)
                ->where('customer_id', $customer->id)
                ->when($query->email, fn ($q) => $q->where('email', $query->email), fn ($q) => $q->where('name', $contactName))
                ->first();

            if (! $contact) {
                $contact = Contact::create([
                    'company_id' => $companyId,
                    'customer_id' => $customer->id,
                    'name' => $contactName !== '' ? $contactName : 'Primary Contact',
                    'email' => $query->email ?: null,
                    'phone' => $query->cell ?: null,
                    'position' => 'Contact person',
                    'is_primary' => true,
                ]);
            }

            $query->update([
                'response' => Query::RESPONSE_ACCEPTED,
                'responded_at' => now(),
                'accepted_at' => now(),
                'accepted_customer_id' => $customer->id,
                'accepted_contact_id' => $contact->id,
                'status' => Query::STATUS_CLOSED,
            ]);

            return [$customer, $contact];
        });

        return redirect()->back()->with('success', "Contractor accepted and converted to customer {$customer->name} with contact {$contact->name}.");
    }

    /**
     * Update the status of the specified query (open/closed).
     */
    public function update(Request $request, Query $query): RedirectResponse
    {
        $this->authorize('update', $query);

        // Job queries are read-only here; their lifecycle is driven by accept/decline.
        abort_if($query->isJob(), 403, 'Job queries cannot be edited.');

        $validated = $request->validate([
            'status' => ['required', 'in:open,closed'],
        ]);

        $query->update(['status' => $validated['status']]);

        return redirect()->back()->with('success', 'Query status updated.');
    }

    /**
     * Create/open quote flow from an enquiry/contractor query.
     * Finds existing customer first, otherwise creates one, then opens Quote Create.
     */
    public function createQuote(Query $query): RedirectResponse
    {
        $this->authorize('update', $query);
        abort_if($query->isJob(), 403, 'Job queries cannot be converted to quotes from this action.');

        $currentCompany = auth()->user()->getCurrentCompany();
        abort_if($currentCompany === null, 403, 'No active company.');
        abort_unless((int) $query->company_id === (int) $currentCompany->id, 404);

        $customerEmail = trim((string) ($query->company_email ?: $query->email));
        $customerPhone = trim((string) ($query->company_contact_number ?: $query->cell));
        $customerName = trim((string) ($query->company_name ?: ($query->name.' '.$query->surname)));
        $customerName = $customerName !== '' ? $customerName : 'Query Prospect';

        $customer = Customer::query()
            ->where('company_id', $currentCompany->id)
            ->when($customerEmail !== '', fn ($q) => $q->where('email', $customerEmail))
            ->first();

        if (! $customer && $customerPhone !== '') {
            $customer = Customer::query()
                ->where('company_id', $currentCompany->id)
                ->where('phone', $customerPhone)
                ->first();
        }

        if (! $customer) {
            $customer = Customer::query()
                ->where('company_id', $currentCompany->id)
                ->where('name', $customerName)
                ->first();
        }

        if (! $customer) {
            $customer = Customer::create([
                'company_id' => $currentCompany->id,
                'name' => $customerName,
                'email' => $customerEmail !== '' ? $customerEmail : null,
                'company_tel' => $customerPhone !== '' ? $customerPhone : null,
                'phone' => $customerPhone !== '' ? $customerPhone : null,
                'address' => $query->company_address ?: null,
                'registration_number' => $query->company_registration_no ?: null,
                'contact_first_name' => $query->name ?: null,
                'contact_last_name' => $query->surname ?: null,
                'contact_cell' => $query->cell ?: null,
                'contact_email' => $query->email ?: null,
                'account_code' => Customer::generateAccountCode($customerName, $currentCompany->id),
            ]);
        }

        return redirect()->route('quotes.create', [
            'customer_id' => $customer->id,
        ])->with('success', "Quote started for customer {$customer->name}.");
    }

    /**
     * Remove the specified query.
     */
    public function destroy(Request $request, Query $query): RedirectResponse
    {
        $this->authorize('delete', $query);

        // Attached files are removed by the Query model's deleting hook.
        $query->delete();

        // Redirect to the list preserving any filters passed by the caller (status tab, search, page).
        $filters = array_filter($request->only(['status', 'search', 'page']), fn ($value) => $value !== null && $value !== '');

        return redirect()->route('queries.index', $filters)->with('success', 'Query deleted.');
    }

    private function publicFormKey(): string
    {
        $configured = (string) config('services.query_api.public_form_key', '');

        if ($configured !== '') {
            return $configured;
        }

        $apiKey = (string) config('services.query_api.key', '');
        if ($apiKey !== '') {
            return $apiKey;
        }

        // Fall back to APP_KEY so Hosted URL still appears on customer releases
        // that ship without QUERY_PUBLIC_FORM_KEY / QUERY_API_KEY set.
        return (string) config('app.key', '');
    }

    private function publicFormTokenForCompany(Company $company): ?string
    {
        $key = $this->publicFormKey();
        if ($key === '') {
            return null;
        }

        return hash_hmac('sha256', 'company:'.$company->id, $key);
    }

    private function isValidPublicFormToken(Company $company, string $provided): bool
    {
        $key = $this->publicFormKey();
        if ($key === '' || $provided === '') {
            return false;
        }

        $expected = hash_hmac('sha256', 'company:'.$company->id, $key);

        return hash_equals($expected, $provided);
    }

    /**
     * @return array<string, string>|null
     */
    private function buildIntegrationPayload(?Company $company): ?array
    {
        if (! $company) {
            return null;
        }

        $token = $this->publicFormTokenForCompany($company);
        if ($token === null) {
            return null;
        }
        $publicUrl = route('queries.public.form', [
            'companyId' => $company->id,
            'token' => $token,
        ]);
        $embedUrl = route('queries.public.embed');

        $contractorPublicUrl = null;
        $contractorEmbedHtml = null;
        if (config('app.is_licensing_instance')) {
            $contractorPublicUrl = $publicUrl.'?kind=contractor';
            $contractorEmbedHtml = '<script src="'.$embedUrl.'?company='.$company->id.'&token='.$token.'&kind=contractor" async></script>';
        }

        return [
            'public_url' => $publicUrl,
            'embed_script_url' => $embedUrl.'?company='.$company->id.'&token='.$token,
            'embed_html' => '<script src="'.$embedUrl.'?company='.$company->id.'&token='.$token.'" async></script>',
            'contractor_public_url' => $contractorPublicUrl,
            'contractor_embed_html' => $contractorEmbedHtml,
        ];
    }

    private function notifyCompanyOfNewQuery(Company $company, Query $query): void
    {
        if (! is_string($company->email) || ! filter_var($company->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            $mailConfig = CompanyMailer::resolve($company);
            $subject = $query->kind === Query::KIND_CONTRACTOR
                ? "New contractor query submitted (#{$query->id})"
                : "New enquiry submitted (#{$query->id})";

            Mail::mailer($mailConfig['mailer'])->send('emails.query-notification', [
                'company' => $company,
                'query' => $query,
            ], function ($message) use ($company, $mailConfig, $subject) {
                $message->to($company->email, $company->name)
                    ->subject($subject)
                    ->from($mailConfig['from_address'], $mailConfig['from_name']);

                if (is_string($company->email) && filter_var($company->email, FILTER_VALIDATE_EMAIL)) {
                    $message->replyTo($company->email, $company->name ?: null);
                }
            });
        } catch (\Throwable $e) {
            Log::warning('Failed to send new query notification email.', [
                'company_id' => $company->id,
                'query_id' => $query->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * User-facing messages for contractor-only required fields.
     * Avoid Laravel's default "when kind is contractor" wording on public forms.
     *
     * @return array<string, string>
     */
    private function contractorFieldRequiredMessages(): array
    {
        return [
            'company_name.required_if' => 'The company name field is required.',
            'company_registration_no.required_if' => 'The company registration no field is required.',
            'company_address.required_if' => 'The company address field is required.',
            'company_email.required_if' => 'The company email field is required.',
            'company_contact_number.required_if' => 'The company contact number field is required.',
        ];
    }

    private function storeContractorDocument(Query $query, mixed $file, string $type): void
    {
        if (! $file) {
            return;
        }

        $mime = (string) $file->getMimeType();
        $attachmentType = str_starts_with($mime, 'image/') ? 'image' : 'document';

        $query->attachments()->create([
            'path' => $file->store('query-attachments', 'public'),
            'type' => $type.':'.$attachmentType,
            'original_name' => $file->getClientOriginalName(),
        ]);
    }

    /**
     * Allow users to enter www.example.com; ensure a scheme so URL validation passes.
     */
    private function normalizeCompanyWebsiteInput(Request $request): void
    {
        $website = $request->input('company_website');
        if (! is_string($website)) {
            return;
        }

        $website = trim($website);
        if ($website === '') {
            $request->merge(['company_website' => null]);

            return;
        }

        if (! preg_match('#^https?://#i', $website)) {
            $website = 'http://'.ltrim($website, '/');
        }

        $request->merge(['company_website' => $website]);
    }

    /**
     * Normalize SA phone numbers to local 10-digit format (0XXXXXXXXX).
     * Accepts spaces/dashes and +27 / 27 country-code forms.
     */
    private function normalizeSouthAfricanPhoneInput(Request $request, string $field): void
    {
        $value = $request->input($field);
        if (! is_string($value)) {
            return;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if ($digits === '') {
            $request->merge([$field => null]);

            return;
        }

        if (str_starts_with($digits, '27') && strlen($digits) === 11) {
            $digits = '0'.substr($digits, 2);
        }

        $request->merge([$field => $digits]);
    }
}

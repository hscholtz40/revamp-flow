<?php

namespace App\Http\Controllers;

use App\Exceptions\JobQueryNotActionableException;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Jobcard;
use App\Models\JobcardLineItem;
use App\Models\LineGroup;
use App\Models\Note;
use App\Models\Query;
use App\Services\CustomerUpsertService;
use App\Services\JobQueryService;
use App\Services\RevampWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class QueriesController extends Controller
{
    /**
     * Store a query submitted from an external site (e.g. the Revamp marketing
     * landing page) via the public query API. Authenticated by a shared API key
     * (query.api.auth middleware); see routes/api.php → api.queries.store.
     */
    public function apiStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'cell' => ['required', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:51200', 'mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi,webm'],
        ], [
            'attachments.max' => 'You can upload a maximum of 10 files.',
            'attachments.*.max' => 'Each file may not be larger than 50MB.',
            'attachments.*.mimes' => 'Each file must be an image or video.',
        ]);

        // Route to the requested active company, or fall back to the default company.
        $company = ! empty($validated['company_id'])
            ? Company::where('id', $validated['company_id'])->where('is_active', true)->first()
            : Company::getDefault();

        abort_if($company === null, 404, 'No company is available to receive queries.');

        $query = Query::create([
            'company_id' => $company->id,
            'name' => $validated['name'],
            'surname' => $validated['surname'],
            'email' => $validated['email'],
            'cell' => $validated['cell'],
            'description' => $validated['description'],
            'status' => Query::STATUS_OPEN,
        ]);

        foreach ((array) $request->file('attachments', []) as $file) {
            $query->attachments()->create([
                'path' => $file->store('query-attachments', 'public'),
                'type' => str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'image',
                'original_name' => $file->getClientOriginalName(),
            ]);
        }

        return response()->json([
            'message' => 'Your query has been submitted. We will get back to you shortly.',
            'id' => $query->id,
        ], 201);
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
                'attachments' => $query->attachments->map(fn ($attachment) => [
                    'id' => $attachment->id,
                    'url' => Storage::disk('public')->url($attachment->path),
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
}

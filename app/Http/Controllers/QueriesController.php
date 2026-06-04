<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Query;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class QueriesController extends Controller
{
    /**
     * Show the public query submission form.
     * Optionally scoped to a specific company; falls back to the default company.
     */
    public function publicForm(?Company $company = null): Response
    {
        $company = $company && $company->is_active ? $company : Company::getDefault();

        abort_if($company === null, 404, 'No company is available to receive queries.');

        return Inertia::render('queries/PublicForm', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'logo_path' => $company->logo_path,
            ],
        ]);
    }

    /**
     * Store a query submitted through the public form (no authentication).
     */
    public function publicStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
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

        $company = Company::where('id', $validated['company_id'])->where('is_active', true)->first();
        abort_if($company === null, 404, 'This company is not available to receive queries.');

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

        return redirect()
            ->route('queries.public.form', ['company' => $company->id])
            ->with('success', 'Your query has been submitted. We will get back to you shortly.');
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
                'publicFormUrl' => route('queries.public.form'),
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
            'publicFormUrl' => route('queries.public.form', ['company' => $currentCompany->id]),
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
                'name' => $query->name,
                'surname' => $query->surname,
                'email' => $query->email,
                'cell' => $query->cell,
                'description' => $query->description,
                'status' => $query->status,
                'created_at' => $query->created_at?->toIso8601String(),
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
     * Update the status of the specified query (open/closed).
     */
    public function update(Request $request, Query $query): RedirectResponse
    {
        $this->authorize('update', $query);

        $validated = $request->validate([
            'status' => ['required', 'in:open,closed'],
        ]);

        $query->update(['status' => $validated['status']]);

        return redirect()->back()->with('success', 'Query status updated.');
    }

    /**
     * Remove the specified query.
     */
    public function destroy(Query $query): RedirectResponse
    {
        $this->authorize('delete', $query);

        // Attached files are removed by the Query model's deleting hook.
        $query->delete();

        // Redirect back to the list preserving the active filters (status tab, search, page).
        return redirect()->back()->with('success', 'Query deleted.');
    }
}

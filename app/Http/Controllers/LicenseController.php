<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\License;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LicenseController extends Controller
{
    /**
     * Display a listing of licenses.
     */
    public function index(Request $request): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();

        if (!$currentCompany) {
            return Inertia::render('licenses/Index', [
                'licenses' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15),
                'filters' => [
                    'search' => $request->input('search', ''),
                    'status' => $request->input('status', ''),
                ],
            ]);
        }

        $search = $request->input('search');
        $status = $request->input('status');

        $licenses = License::where('company_id', $currentCompany->id)
            ->with('customer')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('license_key', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($cq) use ($search) {
                            $cq->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status, function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('licenses/Index', [
            'licenses' => $licenses,
            'filters' => [
                'search' => $search ?? '',
                'status' => $status ?? '',
            ],
        ]);
    }

    /**
     * Show the form for creating a new license.
     */
    public function create(): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();

        $customers = $currentCompany
            ? Customer::where('company_id', $currentCompany->id)->orderBy('name')->get(['id', 'name', 'email'])
            : collect();

        return Inertia::render('licenses/Create', [
            'customers' => $customers,
        ]);
    }

    /**
     * Store a newly created license.
     */
    public function store(Request $request): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();

        if (!$currentCompany) {
            return redirect()->back()
                ->withErrors(['message' => 'No company selected. Please select a company first.']);
        }

        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'url' => ['nullable', 'url', 'max:255'],
            'limited_users' => ['required', 'integer', 'min:0'],
            'standard_users' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:active,suspended,expired,revoked'],
            'notes' => ['nullable', 'string'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $validated['company_id'] = $currentCompany->id;
        $validated['license_key'] = License::generateLicenseKey();

        $license = License::create($validated);

        return redirect()->route('licenses.show', $license)
            ->with('success', 'License created successfully. Key: ' . $license->license_key);
    }

    /**
     * Display the specified license.
     */
    public function show(License $license): Response
    {
        $this->authorizeCompany($license);

        $license->load('customer');

        return Inertia::render('licenses/Show', [
            'license' => $license,
        ]);
    }

    /**
     * Show the form for editing the specified license.
     */
    public function edit(License $license): Response
    {
        $this->authorizeCompany($license);

        $currentCompany = auth()->user()->getCurrentCompany();

        $customers = Customer::where('company_id', $currentCompany->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $license->load('customer');

        return Inertia::render('licenses/Edit', [
            'license' => $license,
            'customers' => $customers,
        ]);
    }

    /**
     * Update the specified license.
     */
    public function update(Request $request, License $license): RedirectResponse
    {
        $this->authorizeCompany($license);

        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'url' => ['nullable', 'url', 'max:255'],
            'limited_users' => ['required', 'integer', 'min:0'],
            'standard_users' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:active,suspended,expired,revoked'],
            'notes' => ['nullable', 'string'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $license->update($validated);

        return redirect()->route('licenses.show', $license)
            ->with('success', 'License updated successfully.');
    }

    /**
     * Remove the specified license.
     */
    public function destroy(License $license): RedirectResponse
    {
        $this->authorizeCompany($license);

        $license->delete();

        return redirect()->route('licenses.index')
            ->with('success', 'License deleted successfully.');
    }

    /**
     * Ensure the license belongs to the current company.
     */
    private function authorizeCompany(License $license): void
    {
        $currentCompany = auth()->user()->getCurrentCompany();

        if (!$currentCompany || $license->company_id !== $currentCompany->id) {
            abort(403, 'Unauthorized access to this license.');
        }
    }
}

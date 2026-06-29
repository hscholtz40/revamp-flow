<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SuppliersController extends Controller
{
    /**
     * JSON search for supplier pickers (e.g. purchase orders), scoped to the current company.
     */
    public function search(Request $request)
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        if (! $currentCompany) {
            return response()->json([]);
        }

        $search = $request->string('q', '')->toString();
        if ($search === '') {
            return response()->json([]);
        }

        $suppliers = Supplier::where('company_id', $currentCompany->id)
            ->where('is_active', true)
            ->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('vat_number', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email', 'phone', 'vat_number']);

        return response()->json($suppliers);
    }

    /**
     * Display a listing of suppliers.
     */
    public function index(Request $request): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $sortBy = $request->input('sort_by', 'name');
        $sortDir = $request->input('sort_dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $sortableFields = ['name', 'email', 'phone', 'city', 'country', 'vat_number', 'is_active', 'created_at'];
        if (! in_array($sortBy, $sortableFields, true)) {
            $sortBy = 'name';
        }

        if (! $currentCompany) {
            \Log::warning('SuppliersController::index - No current company found for user', [
                'user_id' => auth()->id(),
            ]);

            return Inertia::render('suppliers/Index', [
                'suppliers' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15),
                'filters' => [
                    'search' => $request->string('search')->toString(),
                    'active_only' => $request->boolean('active_only'),
                ],
            ]);
        }

        $suppliers = Supplier::where('company_id', $currentCompany->id)
            ->when($request->string('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($request->boolean('active_only'), function ($query) {
                $query->where('is_active', true);
            })
            ->orderBy($sortBy, $sortDir)
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'active_only' => $request->boolean('active_only'),
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
        ]);
    }

    /**
     * Show the form for creating a new supplier.
     */
    public function create(): Response
    {
        return Inertia::render('suppliers/Create');
    }

    /**
     * Store a newly created supplier.
     */
    public function store(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();

        if (! $currentCompany) {
            return redirect()->back()
                ->withErrors(['message' => 'No company selected. Please select a company first.']);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'vat_number' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $validated['company_id'] = $currentCompany->id;

        $supplier = Supplier::create($validated);

        \Log::info('SuppliersController::store - Supplier created', [
            'supplier_id' => $supplier->id,
            'company_id' => $supplier->company_id,
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'id' => $supplier->id,
                'name' => $supplier->name,
                'email' => $supplier->email,
                'phone' => $supplier->phone,
                'vat_number' => $supplier->vat_number,
            ], 201);
        }

        return redirect()->route('suppliers.index')->with('success', 'Supplier created successfully');
    }

    /**
     * Display the specified supplier.
     */
    public function show(Supplier $supplier, Request $request): Response
    {
        $this->authorize('view', $supplier);

        $purchaseOrdersPerPage = (int) $request->get('po_per_page', 10);
        if ($purchaseOrdersPerPage <= 0) {
            $purchaseOrdersPerPage = 10;
        }

        $supplier->load(['products']);

        $user = $request->user();
        $purchaseOrders = $user->hasModulePermission('purchase-orders', 'list')
            ? $supplier->purchaseOrders()
                ->orderByDesc('created_at')
                ->paginate($purchaseOrdersPerPage, ['id', 'po_number', 'status', 'total', 'created_at'], 'po_page')
            : new \Illuminate\Pagination\LengthAwarePaginator([], 0, $purchaseOrdersPerPage, 1, [
                'path' => $request->url(),
                'pageName' => 'po_page',
            ]);

        return Inertia::render('suppliers/Show', [
            'supplier' => $supplier,
            'purchaseOrders' => $purchaseOrders,
        ]);
    }

    /**
     * Show the form for editing the specified supplier.
     */
    public function edit(Supplier $supplier): Response
    {
        $this->authorize('update', $supplier);

        $currentCompany = auth()->user()->getCurrentCompany();

        return Inertia::render('suppliers/Edit', [
            'supplier' => $supplier,
        ]);
    }

    /**
     * Update the specified supplier.
     */
    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $currentCompany = auth()->user()->getCurrentCompany();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'vat_number' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $supplier->update($validated);

        return redirect()->route('suppliers.index')->with('success', 'Supplier updated successfully');
    }

    /**
     * Remove the specified supplier.
     */
    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorize('delete', $supplier);

        $currentCompany = auth()->user()->getCurrentCompany();

        // Check if supplier has products or purchase orders
        if ($supplier->products()->count() > 0) {
            return redirect()->back()
                ->withErrors(['message' => 'Cannot delete supplier with associated products. Please reassign or remove products first.']);
        }

        if ($supplier->purchaseOrders()->count() > 0) {
            return redirect()->back()
                ->withErrors(['message' => 'Cannot delete supplier with associated purchase orders.']);
        }

        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', 'Supplier deleted successfully');
    }
}

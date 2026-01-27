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
     * Display a listing of suppliers.
     */
    public function index(Request $request): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        if (!$currentCompany) {
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
        
        \Log::info('SuppliersController::index - Fetching suppliers', [
            'company_id' => $currentCompany->id,
            'company_name' => $currentCompany->name,
        ]);
        
        // Debug: Check all suppliers in database
        $allSuppliers = Supplier::all(['id', 'name', 'company_id']);
        \Log::info('SuppliersController::index - All suppliers in database', [
            'total_suppliers' => $allSuppliers->count(),
            'suppliers' => $allSuppliers->map(fn($s) => ['id' => $s->id, 'name' => $s->name, 'company_id' => $s->company_id])->toArray(),
        ]);
        
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
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();
        
        \Log::info('SuppliersController::index - Filtered suppliers', [
            'company_id' => $currentCompany->id,
            'suppliers_count' => $suppliers->count(),
            'total' => $suppliers->total(),
            'from' => $suppliers->firstItem(),
            'to' => $suppliers->lastItem(),
            'current_page' => $suppliers->currentPage(),
            'last_page' => $suppliers->lastPage(),
            'per_page' => $suppliers->perPage(),
            'suppliers' => $suppliers->map(fn($s) => ['id' => $s->id, 'name' => $s->name])->toArray(),
        ]);

        return Inertia::render('suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'active_only' => $request->boolean('active_only'),
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
    public function store(Request $request): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        if (!$currentCompany) {
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
        
        \Log::info('SuppliersController::store - Creating supplier', [
            'company_id' => $currentCompany->id,
            'company_name' => $currentCompany->name,
            'supplier_name' => $validated['name'],
        ]);
        
        $supplier = Supplier::create($validated);
        
        \Log::info('SuppliersController::store - Supplier created', [
            'supplier_id' => $supplier->id,
            'company_id' => $supplier->company_id,
        ]);

        return redirect()->route('suppliers.index')->with('success', 'Supplier created successfully');
    }

    /**
     * Display the specified supplier.
     */
    public function show(Supplier $supplier): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        if ($supplier->company_id !== $currentCompany->id) {
            abort(403, 'Unauthorized access to supplier.');
        }

        $supplier->load(['products', 'purchaseOrders' => function ($query) {
            $query->orderBy('created_at', 'desc')->limit(10);
        }]);

        return Inertia::render('suppliers/Show', [
            'supplier' => $supplier,
        ]);
    }

    /**
     * Show the form for editing the specified supplier.
     */
    public function edit(Supplier $supplier): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        if ($supplier->company_id !== $currentCompany->id) {
            abort(403, 'Unauthorized access to supplier.');
        }

        return Inertia::render('suppliers/Edit', [
            'supplier' => $supplier,
        ]);
    }

    /**
     * Update the specified supplier.
     */
    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        if ($supplier->company_id !== $currentCompany->id) {
            abort(403, 'Unauthorized access to supplier.');
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

        $supplier->update($validated);

        return redirect()->route('suppliers.index')->with('success', 'Supplier updated successfully');
    }

    /**
     * Remove the specified supplier.
     */
    public function destroy(Supplier $supplier): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        if ($supplier->company_id !== $currentCompany->id) {
            abort(403, 'Unauthorized access to supplier.');
        }

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

<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductBatchController extends Controller
{
    /**
     * Display a listing of batches for a product.
     */
    public function index(Product $product): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        if ($product->company_id !== $currentCompany->id) {
            abort(403);
        }

        $batches = $product->batches()
            ->orderBy('expiry_date', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('products/batches/Index', [
            'product' => $product,
            'batches' => $batches,
        ]);
    }

    /**
     * Show the form for creating a new batch.
     */
    public function create(Product $product): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        if ($product->company_id !== $currentCompany->id) {
            abort(403);
        }

        return Inertia::render('products/batches/Create', [
            'product' => $product,
        ]);
    }

    /**
     * Store a newly created batch.
     */
    public function store(Request $request, Product $product): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        if ($product->company_id !== $currentCompany->id) {
            abort(403);
        }

        $validated = $request->validate([
            'batch_number' => ['required', 'string', 'max:255', 'unique:product_batches,batch_number'],
            'manufacture_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:manufacture_date'],
            'quantity' => ['required', 'integer', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['company_id'] = $currentCompany->id;
        $validated['product_id'] = $product->id;

        ProductBatch::create($validated);

        return redirect()->route('products.batches.index', $product)
            ->with('success', 'Batch created successfully');
    }

    /**
     * Display the specified batch.
     */
    public function show(Product $product, ProductBatch $batch): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        if ($batch->company_id !== $currentCompany->id || $batch->product_id !== $product->id) {
            abort(403);
        }

        $batch->load('serialNumbers', 'stockMovements');

        return Inertia::render('products/batches/Show', [
            'product' => $product,
            'batch' => $batch,
        ]);
    }

    /**
     * Show the form for editing the specified batch.
     */
    public function edit(Product $product, ProductBatch $batch): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        if ($batch->company_id !== $currentCompany->id || $batch->product_id !== $product->id) {
            abort(403);
        }

        return Inertia::render('products/batches/Edit', [
            'product' => $product,
            'batch' => $batch,
        ]);
    }

    /**
     * Update the specified batch.
     */
    public function update(Request $request, Product $product, ProductBatch $batch): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        if ($batch->company_id !== $currentCompany->id || $batch->product_id !== $product->id) {
            abort(403);
        }

        $validated = $request->validate([
            'batch_number' => ['required', 'string', 'max:255', 'unique:product_batches,batch_number,' . $batch->id],
            'manufacture_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:manufacture_date'],
            'quantity' => ['required', 'integer', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $batch->update($validated);

        return redirect()->route('products.batches.show', [$product, $batch])
            ->with('success', 'Batch updated successfully');
    }

    /**
     * Remove the specified batch.
     */
    public function destroy(Product $product, ProductBatch $batch): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        if ($batch->company_id !== $currentCompany->id || $batch->product_id !== $product->id) {
            abort(403);
        }

        $batch->delete();

        return redirect()->route('products.batches.index', $product)
            ->with('success', 'Batch deleted successfully');
    }
}

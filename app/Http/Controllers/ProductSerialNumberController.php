<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductSerialNumber;
use App\Support\CompanyScopedRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductSerialNumberController extends Controller
{
    /**
     * Display a listing of serial numbers for a product.
     */
    public function index(Product $product): Response
    {
        $this->authorize('view', $product);

        $serialNumbers = $product->serialNumbers()
            ->with(['batch', 'invoice', 'jobcard'])
            ->orderBy('status')
            ->orderBy('serial_number')
            ->get();

        return Inertia::render('products/serial-numbers/Index', [
            'product' => $product,
            'serialNumbers' => $serialNumbers,
        ]);
    }

    /**
     * Show the form for creating new serial numbers.
     */
    public function create(Product $product): Response
    {
        $this->authorize('view', $product);

        $batches = $product->batches()->get();

        return Inertia::render('products/serial-numbers/Create', [
            'product' => $product,
            'batches' => $batches,
        ]);
    }

    /**
     * Store newly created serial numbers.
     */
    public function store(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('view', $product);

        $currentCompany = auth()->user()->getCurrentCompany();

        $validated = $request->validate([
            'serial_numbers' => ['required', 'array', 'min:1'],
            'serial_numbers.*' => ['required', 'string', 'max:255', 'unique:product_serial_numbers,serial_number'],
            'batch_id' => ['nullable', CompanyScopedRules::productBatchForProduct($currentCompany->id, $product->id)],
            'purchase_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $serialNumbers = [];
        foreach ($validated['serial_numbers'] as $serialNumber) {
            $serialNumbers[] = ProductSerialNumber::create([
                'company_id' => $currentCompany->id,
                'product_id' => $product->id,
                'serial_number' => $serialNumber,
                'batch_id' => $validated['batch_id'] ?? null,
                'purchase_date' => $validated['purchase_date'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => 'available',
            ]);
        }

        return redirect()->route('products.serial-numbers.index', $product)
            ->with('success', count($serialNumbers).' serial number(s) created successfully');
    }

    /**
     * Display the specified serial number.
     */
    public function show(Product $product, ProductSerialNumber $serialNumber): Response
    {
        $this->authorize('manageSerial', [$product, $serialNumber]);

        $serialNumber->load('batch', 'invoice', 'jobcard', 'stockMovement');

        return Inertia::render('products/serial-numbers/Show', [
            'product' => $product,
            'serialNumber' => $serialNumber,
        ]);
    }

    /**
     * Update the specified serial number.
     */
    public function update(Request $request, Product $product, ProductSerialNumber $serialNumber): RedirectResponse
    {
        $this->authorize('manageSerial', [$product, $serialNumber]);

        $currentCompany = auth()->user()->getCurrentCompany();

        $validated = $request->validate([
            'batch_id' => ['nullable', CompanyScopedRules::productBatchForProduct($currentCompany->id, $product->id)],
            'status' => ['required', 'in:available,sold,returned,damaged,scrapped'],
            'notes' => ['nullable', 'string'],
        ]);

        $serialNumber->update($validated);

        return redirect()->route('products.serial-numbers.show', [$product, $serialNumber])
            ->with('success', 'Serial number updated successfully');
    }

    /**
     * Remove the specified serial number.
     */
    public function destroy(Product $product, ProductSerialNumber $serialNumber): RedirectResponse
    {
        $this->authorize('manageSerial', [$product, $serialNumber]);

        $serialNumber->delete();

        return redirect()->route('products.serial-numbers.index', $product)
            ->with('success', 'Serial number deleted successfully');
    }
}

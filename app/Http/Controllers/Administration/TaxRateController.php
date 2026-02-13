<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\TaxRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaxRateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $taxRates = TaxRate::where('company_id', $currentCompany->id)
            ->orderBy('name')
            ->get();

        return Inertia::render('administration/tax-rates/Index', [
            'taxRates' => $taxRates,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('administration/tax-rates/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'is_default_sales' => ['boolean'],
            'is_default_purchasing' => ['boolean'],
        ]);

        $validated['company_id'] = $currentCompany->id;
        
        // If setting as default sales, unset other default sales for this company
        if ($validated['is_default_sales'] ?? false) {
            TaxRate::where('company_id', $currentCompany->id)
                ->where('is_default_sales', true)
                ->update(['is_default_sales' => false]);
        }
        
        // If setting as default purchasing, unset other default purchasing for this company
        if ($validated['is_default_purchasing'] ?? false) {
            TaxRate::where('company_id', $currentCompany->id)
                ->where('is_default_purchasing', true)
                ->update(['is_default_purchasing' => false]);
        }
        
        TaxRate::create($validated);

        return redirect()->route('administration.tax-rates.index')
            ->with('success', 'Tax rate created successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(TaxRate $taxRate): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        if ($taxRate->company_id !== $currentCompany->id) {
            abort(403, 'Unauthorized access to tax rate.');
        }

        return Inertia::render('administration/tax-rates/Show', [
            'taxRate' => $taxRate,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TaxRate $taxRate): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        if ($taxRate->company_id !== $currentCompany->id) {
            abort(403, 'Unauthorized access to tax rate.');
        }

        return Inertia::render('administration/tax-rates/Edit', [
            'taxRate' => $taxRate,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TaxRate $taxRate): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        if ($taxRate->company_id !== $currentCompany->id) {
            abort(403, 'Unauthorized access to tax rate.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'is_default_sales' => ['boolean'],
            'is_default_purchasing' => ['boolean'],
        ]);

        // If setting as default sales, unset other default sales for this company
        if ($validated['is_default_sales'] ?? false) {
            TaxRate::where('company_id', $currentCompany->id)
                ->where('is_default_sales', true)
                ->where('id', '!=', $taxRate->id)
                ->update(['is_default_sales' => false]);
        }
        
        // If setting as default purchasing, unset other default purchasing for this company
        if ($validated['is_default_purchasing'] ?? false) {
            TaxRate::where('company_id', $currentCompany->id)
                ->where('is_default_purchasing', true)
                ->where('id', '!=', $taxRate->id)
                ->update(['is_default_purchasing' => false]);
        }
        
        $taxRate->update($validated);

        return redirect()->route('administration.tax-rates.index')
            ->with('success', 'Tax rate updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TaxRate $taxRate): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        if ($taxRate->company_id !== $currentCompany->id) {
            abort(403, 'Unauthorized access to tax rate.');
        }

        // Check if tax rate is being used in any line items
        $usedInInvoiceItems = \App\Models\InvoiceLineItem::where('tax_rate_id', $taxRate->id)->exists();
        $usedInQuoteItems = \App\Models\QuoteLineItem::where('tax_rate_id', $taxRate->id)->exists();
        $usedInJobcardItems = \App\Models\JobcardLineItem::where('tax_rate_id', $taxRate->id)->exists();
        $usedInPOItems = \App\Models\PurchaseOrderItem::where('tax_rate_id', $taxRate->id)->exists();

        if ($usedInInvoiceItems || $usedInQuoteItems || $usedInJobcardItems || $usedInPOItems) {
            return redirect()->route('administration.tax-rates.index')
                ->with('error', 'Cannot delete tax rate that is being used in line items.');
        }

        $taxRate->delete();

        return redirect()->route('administration.tax-rates.index')
            ->with('success', 'Tax rate deleted successfully');
    }
}

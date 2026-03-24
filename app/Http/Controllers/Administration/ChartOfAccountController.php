<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Support\CompanyScopedRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ChartOfAccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $accounts = ChartOfAccount::where('company_id', $currentCompany->id)
            ->with('parentAccount')
            ->ordered()
            ->get();

        return Inertia::render('administration/chart-of-accounts/Index', [
            'accounts' => $accounts,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $parentAccounts = ChartOfAccount::where('company_id', $currentCompany->id)
            ->whereNull('parent_account_id')
            ->active()
            ->ordered()
            ->get();

        return Inertia::render('administration/chart-of-accounts/Create', [
            'parentAccounts' => $parentAccounts,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();

        $validated = $request->validate([
            'account_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('chart_of_accounts', 'account_code')
                    ->where(fn ($query) => $query->where('company_id', $currentCompany->id)),
            ],
            'account_name' => ['required', 'string', 'max:255'],
            'account_type' => ['required', 'in:Asset,Liability,Equity,Revenue,Expense'],
            'parent_account_id' => ['nullable', CompanyScopedRules::chartOfAccountParent($currentCompany->id)],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'is_default_sales' => ['boolean'],
            'is_default_purchasing' => ['boolean'],
            'is_default_rounding' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $validated['company_id'] = $currentCompany->id;

        if ($validated['is_default_sales'] ?? false) {
            ChartOfAccount::where('company_id', $currentCompany->id)
                ->where('is_default_sales', true)
                ->update(['is_default_sales' => false]);
        }

        if ($validated['is_default_purchasing'] ?? false) {
            ChartOfAccount::where('company_id', $currentCompany->id)
                ->where('is_default_purchasing', true)
                ->update(['is_default_purchasing' => false]);
        }

        if ($validated['is_default_rounding'] ?? false) {
            ChartOfAccount::where('company_id', $currentCompany->id)
                ->where('is_default_rounding', true)
                ->update(['is_default_rounding' => false]);
        }

        ChartOfAccount::create($validated);

        return redirect()->route('administration.chart-of-accounts.index')
            ->with('success', 'Chart of account created successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(ChartOfAccount $chartOfAccount): Response
    {
        $this->authorize('view', $chartOfAccount);

        $chartOfAccount->load(['parentAccount', 'childAccounts']);

        return Inertia::render('administration/chart-of-accounts/Show', [
            'account' => $chartOfAccount,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ChartOfAccount $chartOfAccount): Response
    {
        $this->authorize('update', $chartOfAccount);

        $currentCompany = auth()->user()->getCurrentCompany();

        $parentAccounts = ChartOfAccount::where('company_id', $currentCompany->id)
            ->whereNull('parent_account_id')
            ->where('id', '!=', $chartOfAccount->id)
            ->active()
            ->ordered()
            ->get();

        return Inertia::render('administration/chart-of-accounts/Edit', [
            'account' => $chartOfAccount,
            'parentAccounts' => $parentAccounts,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ChartOfAccount $chartOfAccount): RedirectResponse
    {
        $this->authorize('update', $chartOfAccount);

        $currentCompany = auth()->user()->getCurrentCompany();

        $validated = $request->validate([
            'account_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('chart_of_accounts', 'account_code')
                    ->where(fn ($query) => $query->where('company_id', $currentCompany->id))
                    ->ignore($chartOfAccount->id),
            ],
            'account_name' => ['required', 'string', 'max:255'],
            'account_type' => ['required', 'in:Asset,Liability,Equity,Revenue,Expense'],
            'parent_account_id' => ['nullable', CompanyScopedRules::chartOfAccountParent($currentCompany->id, $chartOfAccount->id)],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'is_default_sales' => ['boolean'],
            'is_default_purchasing' => ['boolean'],
            'is_default_rounding' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        // Prevent setting itself as parent
        if ($validated['parent_account_id'] == $chartOfAccount->id) {
            return redirect()->back()
                ->withErrors(['parent_account_id' => 'An account cannot be its own parent.']);
        }

        if ($validated['is_default_sales'] ?? false) {
            ChartOfAccount::where('company_id', $currentCompany->id)
                ->where('is_default_sales', true)
                ->where('id', '!=', $chartOfAccount->id)
                ->update(['is_default_sales' => false]);
        }

        if ($validated['is_default_purchasing'] ?? false) {
            ChartOfAccount::where('company_id', $currentCompany->id)
                ->where('is_default_purchasing', true)
                ->where('id', '!=', $chartOfAccount->id)
                ->update(['is_default_purchasing' => false]);
        }

        if ($validated['is_default_rounding'] ?? false) {
            ChartOfAccount::where('company_id', $currentCompany->id)
                ->where('is_default_rounding', true)
                ->where('id', '!=', $chartOfAccount->id)
                ->update(['is_default_rounding' => false]);
        }

        $chartOfAccount->update($validated);

        return redirect()->route('administration.chart-of-accounts.index')
            ->with('success', 'Chart of account updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ChartOfAccount $chartOfAccount): RedirectResponse
    {
        $this->authorize('delete', $chartOfAccount);

        $currentCompany = auth()->user()->getCurrentCompany();

        // Check if account has child accounts
        if ($chartOfAccount->childAccounts()->count() > 0) {
            return redirect()->route('administration.chart-of-accounts.index')
                ->with('error', 'Cannot delete account that has child accounts. Please delete or reassign child accounts first.');
        }

        $chartOfAccount->delete();

        return redirect()->route('administration.chart-of-accounts.index')
            ->with('success', 'Chart of account deleted successfully');
    }
}

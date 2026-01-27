<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankAccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $bankAccounts = BankAccount::where('company_id', $currentCompany->id)
            ->orderBy('is_default', 'desc')
            ->orderBy('account_name')
            ->get();

        return Inertia::render('administration/bank-accounts/Index', [
            'bankAccounts' => $bankAccounts,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('administration/bank-accounts/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        $validated = $request->validate([
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:50'],
            'bank_name' => ['required', 'string', 'max:255'],
            'branch_code' => ['nullable', 'string', 'max:50'],
            'account_type' => ['required', 'in:Current,Savings,Credit Card,Loan,Other'],
            'currency' => ['required', 'string', 'size:3'],
            'opening_balance' => ['required', 'numeric'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
        ]);

        $validated['company_id'] = $currentCompany->id;
        
        // If setting as default, unset other defaults for this company
        if ($validated['is_default'] ?? false) {
            BankAccount::where('company_id', $currentCompany->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }
        
        BankAccount::create($validated);

        return redirect()->route('administration.bank-accounts.index')
            ->with('success', 'Bank account created successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(BankAccount $bankAccount): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        if ($bankAccount->company_id !== $currentCompany->id) {
            abort(403, 'Unauthorized access to bank account.');
        }

        return Inertia::render('administration/bank-accounts/Show', [
            'bankAccount' => $bankAccount,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(BankAccount $bankAccount): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        if ($bankAccount->company_id !== $currentCompany->id) {
            abort(403, 'Unauthorized access to bank account.');
        }

        return Inertia::render('administration/bank-accounts/Edit', [
            'bankAccount' => $bankAccount,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, BankAccount $bankAccount): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        if ($bankAccount->company_id !== $currentCompany->id) {
            abort(403, 'Unauthorized access to bank account.');
        }

        $validated = $request->validate([
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:50'],
            'bank_name' => ['required', 'string', 'max:255'],
            'branch_code' => ['nullable', 'string', 'max:50'],
            'account_type' => ['required', 'in:Current,Savings,Credit Card,Loan,Other'],
            'currency' => ['required', 'string', 'size:3'],
            'opening_balance' => ['required', 'numeric'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
        ]);

        // If setting as default, unset other defaults for this company
        if ($validated['is_default'] ?? false) {
            BankAccount::where('company_id', $currentCompany->id)
                ->where('is_default', true)
                ->where('id', '!=', $bankAccount->id)
                ->update(['is_default' => false]);
        }

        $bankAccount->update($validated);

        return redirect()->route('administration.bank-accounts.index')
            ->with('success', 'Bank account updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BankAccount $bankAccount): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        if ($bankAccount->company_id !== $currentCompany->id) {
            abort(403, 'Unauthorized access to bank account.');
        }

        // Check if bank account is being used in payments
        $usedInPayments = \App\Models\Payment::where('company_id', $currentCompany->id)
            ->where('bank_account_id', $bankAccount->id)
            ->exists();

        if ($usedInPayments) {
            return redirect()->route('administration.bank-accounts.index')
                ->with('error', 'Cannot delete bank account that is being used in payments.');
        }

        $bankAccount->delete();

        return redirect()->route('administration.bank-accounts.index')
            ->with('success', 'Bank account deleted successfully');
    }
}

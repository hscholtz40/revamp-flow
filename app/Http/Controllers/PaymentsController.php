<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\XeroService;
use App\Services\ReminderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Log;

class PaymentsController extends Controller
{
    /**
     * Store a newly created payment.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,card,eft',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        $invoice = Invoice::findOrFail($validated['invoice_id']);
        $currentCompany = auth()->user()->getCurrentCompany();

        // Check if user has access to this invoice
        if ($invoice->company_id !== $currentCompany->id) {
            return redirect()->back()->with('error', 'You do not have access to this invoice.');
        }

        // Check if payment amount exceeds remaining balance
        $remainingBalance = $invoice->remaining_balance;
        if ($validated['amount'] > $remainingBalance) {
            return redirect()->back()->with('error', 'Payment amount cannot exceed the remaining balance of ' . number_format($remainingBalance, 2));
        }

        $validated['company_id'] = $currentCompany->id;

        $payment = Payment::create($validated);
        
        // Refresh invoice to reload payments relationship for accurate calculations
        $invoice->refresh();
        $invoice->load('payments');
        
        $payment->load('invoice.customer', 'invoice.company');

        // Update invoice status if fully paid
        if ($invoice->isFullyPaid()) {
            $invoice->update(['status' => 'paid']);
        }

        // Payment export is handled by scheduled sync (xero:sync-payments)
        // to avoid duplicate request bursts from both UI and scheduler paths.

        // Send automated reminder if enabled
        try {
            $reminderService = new ReminderService();
            $reminderService->sendPaymentReceivedConfirmation($payment);
        } catch (\Exception $e) {
            Log::error('Failed to send payment received confirmation', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            // Don't fail the payment creation if reminder fails
        }

        return redirect()->back()->with('success', 'Payment added successfully.');
    }

    /**
     * Remove the specified payment.
     */
    public function destroy(Payment $payment): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();

        // Check if user has access to this payment
        if ($payment->company_id !== $currentCompany->id) {
            return redirect()->back()->with('error', 'You do not have access to this payment.');
        }

        $invoice = $payment->invoice;
        if ($payment->xero_payment_id) {
            try {
                $xeroService = new XeroService($currentCompany);
                if ($xeroService->isConfigured()) {
                    $xeroService->deletePaymentInXero($payment);
                }
            } catch (\Exception $e) {
                Log::error('Failed to delete payment in Xero during local deletion', [
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice?->id,
                    'xero_payment_id' => $payment->xero_payment_id,
                    'error' => $e->getMessage(),
                ]);

                return redirect()->back()->with('error', 'Failed to delete payment in Xero. Local payment was not removed.');
            }
        }

        $payment->delete();

        // Refresh invoice to reload payments relationship for accurate calculations
        $invoice->refresh();
        $invoice->load('payments');

        // Update invoice status if no longer fully paid
        if (!$invoice->isFullyPaid() && $invoice->status === 'paid') {
            $invoice->update(['status' => 'sent']);
        }

        return redirect()->back()->with('success', 'Payment removed successfully.');
    }
}

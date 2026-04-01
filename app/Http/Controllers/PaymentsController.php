<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\ReminderService;
use App\Services\XeroService;
use App\Support\CompanyScopedRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentsController extends Controller
{
    /**
     * Store a newly created payment.
     */
    public function store(Request $request): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        abort_if(! $currentCompany, 403);

        $validated = $request->validate([
            'invoice_id' => ['required', CompanyScopedRules::invoice($currentCompany->id)],
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,card,eft',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        abort_unless(
            auth()->user()->canRecordPaymentMethod($validated['payment_method']),
            403,
            'You are not permitted to record payments with this method.'
        );

        $invoice = Invoice::findOrFail($validated['invoice_id']);
        $this->authorize('view', $invoice);

        // Check if payment amount exceeds remaining balance
        $remainingBalance = $invoice->remaining_balance;
        if ($validated['amount'] > $remainingBalance) {
            return redirect()->back()->with('error', 'Payment amount cannot exceed the remaining balance of '.number_format($remainingBalance, 2));
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

        // Sync payment to Xero if invoice is synced to Xero
        if ($invoice->xero_invoice_id) {
            try {
                $xeroService = new XeroService($currentCompany);
                if ($xeroService->isConfigured()) {
                    $xeroService->syncPaymentsToXero($invoice);
                    Log::info('Payment synced to Xero', [
                        'payment_id' => $payment->id,
                        'invoice_id' => $invoice->id,
                        'amount' => $payment->amount,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to sync payment to Xero', [
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
                // Don't fail the payment creation if Xero sync fails
            }
        }

        // Send automated reminder if enabled
        try {
            $reminderService = new ReminderService;
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
        $this->authorize('delete', $payment);

        $currentCompany = auth()->user()->getCurrentCompany();

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
        if (! $invoice->isFullyPaid() && $invoice->status === 'paid') {
            $invoice->update(['status' => 'sent']);
        }

        return redirect()->back()->with('success', 'Payment removed successfully.');
    }
}

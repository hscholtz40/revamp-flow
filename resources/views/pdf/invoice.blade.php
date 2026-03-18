<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tax Invoice #{{ $invoice->invoice_number ?? 'N/A' }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.4;
            color: #000;
            margin: 0;
            padding: 20px;
            font-size: 12px;
            background: white;
        }
        
        .page-header {
            text-align: right;
            font-size: 10px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .header-row {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        
        .header-logo {
            display: table-cell;
            width: 50%;
            vertical-align: middle;
        }
        
        .document-title {
            display: table-cell;
            width: 50%;
            text-align: right;
            vertical-align: middle;
            font-size: 24px;
            font-weight: bold;
            color: #000;
            text-transform: uppercase;
        }
        
        .company-section {
            display: table;
            width: 100%;
            margin-bottom: 20px;
            border: 1px solid #000;
            padding: 15px;
        }
        
        .company-left {
            display: table-cell;
            width: 60%;
            vertical-align: top;
        }
        
        .company-right {
            display: table-cell;
            width: 40%;
            vertical-align: top;
            text-align: left;
        }
        
        .company-logo {
            max-width: 380px;
            max-height: 260px;
            margin-bottom: 10px;
        }
        
        .company-name {
            font-size: 16px;
            font-weight: bold;
            color: #000;
            margin-bottom: 5px;
        }
        
        .company-tagline {
            font-size: 11px;
            color: #666;
            margin-bottom: 15px;
        }
        
        .company-details {
            font-size: 11px;
            line-height: 1.3;
        }
        
        .company-details-table {
            width: 90%;
            max-width: 100%;
            border-collapse: collapse;
            table-layout: auto;
        }

        .company-details-table td {
            padding: 2px 0;
            vertical-align: top;
        }

        .company-details-label {
            color: #555;
            padding-right: 8px;
            white-space: nowrap;
        }
        
        .banking-details {
            text-align: left;
            font-size: 11px;
        }
        
        .banking-details h4 {
            margin: 0 0 8px 0;
            font-size: 12px;
            font-weight: bold;
            color: #000;
        }
        
        .banking-details p {
            margin: 2px 0;
        }
        
        .customer-section {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        
        .customer-left {
            display: table-cell;
            width: 60%;
            vertical-align: top;
        }
        
        .customer-right {
            display: table-cell;
            width: 40%;
            vertical-align: top;
            text-align: right;
        }
        
        .customer-info h4 {
            margin: 0 0 8px 0;
            font-size: 12px;
            font-weight: bold;
            color: #000;
        }
        
        .customer-info p {
            margin: 2px 0;
            font-size: 11px;
        }
        
        .vat-number {
            font-size: 11px;
        }
        
        .vat-number label {
            font-weight: bold;
        }
        
        .separator-line {
            border-top: 1px solid #000;
            margin: 15px 0;
        }
        
        .invoice-details {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        
        .invoice-detail {
            display: table-cell;
            width: 20%;
            border: 1px solid #000;
            padding: 8px;
            text-align: center;
            font-size: 11px;
        }
        
        .invoice-detail-label {
            font-weight: bold;
            display: block;
            margin-bottom: 4px;
        }
        
        .invoice-detail-value {
            color: #000;
        }
        
        .line-items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .line-items-table th,
        .line-items-table td {
            padding: 3px;
            font-size: 11px;
        }
        
        .line-items-table th {
            font-weight: bold;
            text-align: left;
            border-bottom: 2px solid #000;
        }
        
        .line-items-table td {
            text-align: left;
        }
        
        .line-items-table .text-right {
            text-align: right;
        }
        
        .totals-section {
            float: right;
            width: 300px;
            margin-top: 20px;
        }
        
        .total-row {
            display: table;
            width: 100%;
            margin-bottom: 5px;
        }
        
        .total-row span {
            display: table-cell;
            font-size: 11px;
            padding: 2px 0;
        }
        
        .total-row span:first-child {
            text-align: left;
        }
        
        .total-row span:last-child {
            text-align: right;
        }
        
        .total-row.final {
            font-weight: bold;
            font-size: 12px;
            border-top: 1px solid #000;
            padding-top: 8px;
            margin-top: 8px;
        }

        .terms-section {
            clear: both;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #000;
            font-size: 11px;
            text-align: left;
        }

        .terms-section h4 {
            margin: 0 0 10px 0;
            font-size: 12px;
            font-weight: bold;
            color: #000;
        }

        .terms-section p {
            margin: 0 0 8px 0;
            font-size: 11px;
            line-height: 1.4;
        }
        
        .signature-section {
            clear: both;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #000;
        }
        
        .signature-row {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }
        
        .signature-item {
            display: table-cell;
            width: 33.33%;
            text-align: left;
            font-size: 11px;
        }
        
        .signature-line {
            border-bottom: 1px dashed #000;
            height: 20px;
            margin-top: 5px;
        }
        
        .payment-clause {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #000;
            font-size: 11px;
            text-align: center;
        }
        
        .footer {
            position: fixed;
            bottom: 20px;
            left: 20px;
            right: 20px;
            font-size: 9px;
            color: #666;
            display: table;
            width: 100%;
        }
        
        .footer-left {
            display: table-cell;
            text-align: left;
        }
        
        .footer-right {
            display: table-cell;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="page-header">Page 1 of 1</div>
    
    <div class="header-row">
        <div class="header-logo">
            @if($company->getLogoPathForPdf())
                <img src="{{ $company->getLogoPathForPdf() }}" alt="Company Logo" class="company-logo">
            @endif
        </div>
        <div class="document-title">Tax Invoice</div>
    </div>
    
    <div class="company-section">
        <div class="company-left">
            <div class="company-name">{{ $company->name ?? 'COMPANY NAME' }}</div>
            @if($company->tagline)
                <div class="company-tagline">{{ $company->tagline }}</div>
            @endif
            
            <div class="company-details">
                <table class="company-details-table">
                    @if($company->address)
                        <tr>
                            <td class="company-details-label">Address:</td>
                            <td>{{ $company->address }}</td>
                        </tr>
                    @endif
                    @if($company->city)
                        <tr>
                            <td class="company-details-label">City:</td>
                            <td>{{ $company->city }}</td>
                        </tr>
                    @endif
                    @if($company->vat_number)
                        <tr>
                            <td class="company-details-label">VAT Number:</td>
                            <td>{{ $company->vat_number }}</td>
                        </tr>
                    @endif
                    @if($company->phone)
                        <tr>
                            <td class="company-details-label">Phone:</td>
                            <td>{{ $company->phone }}</td>
                        </tr>
                    @endif
                    @if($company->email && stripos($company->email, 'sage-migration.local') === false)
                        <tr>
                            <td class="company-details-label">Email:</td>
                            <td>{{ $company->email }}</td>
                        </tr>
                    @endif
                    @if($company->fax)
                        <tr>
                            <td class="company-details-label">Fax:</td>
                            <td>{{ $company->fax }}</td>
                        </tr>
                    @endif
                </table>
            </div>
        </div>
        
        <div class="company-right">
            <div class="banking-details">
                <h4>Banking Details</h4>
                <p><strong>{{ $company->bank_account_name ?? $company->name }}</strong></p>
                @if($company->bank_name)
                    <p>Bank Name: {{ $company->bank_name }}</p>
                @endif
                @if($company->bank_account_number)
                    <p>Acc No: {{ $company->bank_account_number }}</p>
                @endif
                @if($company->bank_sort_code)
                    <p>Branch Code: {{ $company->bank_sort_code }}</p>
                @endif
            </div>
        </div>
    </div>
    
    <div class="customer-section">
        <div class="customer-left">
            <div class="customer-info">
                <h4>To:</h4>
                <p><strong>{{ $invoice->customer->account_code ?? 'N/A' }}</strong></p>
                <p><strong>{{ $invoice->customer->name ?? 'CUSTOMER NAME' }}</strong></p>
                @if($invoice->contact)
                    <p><em>Attn: {{ $invoice->contact->name }}</em></p>
                @endif
                @if($invoice->customer->address)
                    <p>{{ $invoice->customer->address }}</p>
                @endif
                @if($invoice->customer->city)
                    <p>{{ $invoice->customer->city }}</p>
                @endif
                @if($invoice->customer->postal_code)
                    <p>{{ $invoice->customer->postal_code }}</p>
                @endif
                @php
                    $invoiceDisplayEmail = null;
                    if ($invoice->email && stripos($invoice->email, 'sage-migration.local') === false) {
                        $invoiceDisplayEmail = $invoice->email;
                    } elseif ($invoice->customer->email && stripos($invoice->customer->email, 'sage-migration.local') === false) {
                        $invoiceDisplayEmail = $invoice->customer->email;
                    }
                @endphp
                @if($invoiceDisplayEmail)
                    <p>{{ $invoiceDisplayEmail }}</p>
                @endif
                @if($invoice->phone)
                    <p>{{ $invoice->phone }}</p>
                @elseif($invoice->customer->phone)
                    <p>{{ $invoice->customer->phone }}</p>
                @endif
            </div>
        </div>
        
        <div class="customer-right">
            <div class="vat-number">
                <label>Customer Vat Number</label>
                <div style="border: 1px solid #000; min-height: 20px; margin-top: 5px; padding: 2px 4px;">
                    {{ $invoice->customer->vat_number ?? '-' }}
                </div>
            </div>
        </div>
    </div>
    
    @if($invoice->description)
    <div style="margin-bottom: 20px; font-size: 11px;">
        <h4 style="margin: 0 0 6px 0; font-size: 12px; font-weight: bold; color: #000;">Description</h4>
        <p style="margin: 0; line-height: 1.4;">{{ $invoice->description }}</p>
    </div>
    @endif
    
    <div class="separator-line"></div>
    
    <div class="invoice-details">
        <div class="invoice-detail">
            <span class="invoice-detail-label">Account</span>
            <span class="invoice-detail-value">{{ $invoice->customer->account_code ?? 'N/A' }}</span>
        </div>
        <div class="invoice-detail">
            <span class="invoice-detail-label">Date</span>
            <span class="invoice-detail-value">{{ $invoice->invoice_date ? \Carbon\Carbon::parse($invoice->invoice_date)->format('Y/m/d') : date('Y/m/d') }}</span>
        </div>
        <div class="invoice-detail">
            <span class="invoice-detail-label">Order No</span>
            <span class="invoice-detail-value">{{ $invoice->order_number ?? '' }}</span>
        </div>
        <div class="invoice-detail">
            <span class="invoice-detail-label">Job No</span>
            <span class="invoice-detail-value">{{ $invoice->job_number ?? '' }}</span>
        </div>
        <div class="invoice-detail">
            <span class="invoice-detail-label">Invoice No</span>
            <span class="invoice-detail-value">{{ $invoice->invoice_number ?? 'N/A' }}</span>
        </div>
    </div>
    
    <table class="line-items-table">
        <thead>
            <tr>
                <th>Item Description</th>
                <th>Item Code</th>
                <th class="text-right">QTY</th>
                <th class="text-right">Price (Ex)</th>
                <th class="text-right">Discount</th>
                <th class="text-right">Tax</th>
                <th class="text-right">Total (Incl)</th>
            </tr>
        </thead>
        <tbody>
            @php
                $lineGroups = $invoice->lineGroups ?? collect();
                $items = ($invoice->lineItems ?? collect())->filter(function ($item) {
                    return strtolower(trim((string) ($item->description ?? ''))) !== 'rounding adjustment';
                });
                $resolvedGroups = collect();
                $renderedItemIds = collect();

                foreach ($lineGroups->sortBy('sort_order') as $group) {
                    $groupItems = $items->where('line_group_id', $group->id);
                    if ($groupItems->isNotEmpty()) {
                        $resolvedGroups->push((object) [
                            'name' => $group->name,
                            'items' => $groupItems,
                        ]);
                        $renderedItemIds = $renderedItemIds->merge($groupItems->pluck('id'));
                    }
                }

                $ungroupedItems = $items->filter(function ($item) use ($renderedItemIds) {
                    return !$renderedItemIds->contains($item->id);
                });

                if ($resolvedGroups->isEmpty() && $items->isNotEmpty()) {
                    $resolvedGroups->push((object) [
                        'name' => 'Items',
                        'items' => $items,
                    ]);
                } elseif ($ungroupedItems->isNotEmpty()) {
                    $resolvedGroups->push((object) [
                        'name' => 'Items',
                        'items' => $ungroupedItems,
                    ]);
                }
            @endphp
            @foreach($resolvedGroups as $group)
                    @if($group->name)
                        <tr class="group-header"><td colspan="7" style="font-weight: bold; background: #f5f5f5; padding: 4px 2px;">{{ $group->name }}</td></tr>
                    @endif
                    @foreach($group->items->sortBy('sort_order') as $item)
                <tr>
                    <td>
                        <div>{{ $item->description ?? 'Item Description' }}</div>
                        @if(!empty($item->serialNumbers) && $item->serialNumbers->count() > 0)
                            <div style="margin-top: 4px; font-size: 9px; color: #666;">
                                <strong>Serial Numbers:</strong>
                                @foreach($item->serialNumbers as $serial)
                                    {{ $serial->serial_number }}{{ !$loop->last ? ', ' : '' }}
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td>{{ ($item->product->sku ?? $item->product->barcode ?? $item->product->item_code ?? '—') }}</td>
                    <td class="text-right">{{ number_format($item->quantity ?? 0, 2) }}</td>
                    <td class="text-right">R{{ number_format($item->unit_price ?? 0, 2) }}</td>
                    <td class="text-right">
                        @if(($item->discount_percentage ?? 0) > 0)
                            {{ number_format($item->discount_percentage, 2) }}%
                        @elseif(($item->discount_amount ?? 0) > 0)
                            R{{ number_format($item->discount_amount, 2) }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-right">
                        @if($item->taxRate)
                            R{{ number_format($item->tax_amount ?? 0, 2) }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-right">R{{ number_format($item->total ?? 0, 2) }}</td>
                </tr>
                    @endforeach
            @endforeach
        </tbody>
    </table>
    
    @php
        $lineItemsForRoundingTotals = $invoice->lineItemsForRoundingTotals ?? $invoice->lineItems ?? collect();
        $roundingAdjustment = $lineItemsForRoundingTotals->reduce(function ($sum, $item) {
            $description = strtolower(trim((string) ($item->description ?? '')));
            if ($description !== 'rounding adjustment') {
                return $sum;
            }
            $lineTotal = (float) ($item->total ?? (($item->quantity ?? 0) * ($item->unit_price ?? 0)));
            return $sum + $lineTotal;
        }, 0.0);
    @endphp
    <div class="totals-section">
        <div class="total-row">
            <span>Subtotal:</span>
            <span>R{{ number_format($invoice->subtotal ?? 0, 2) }}</span>
        </div>
        @if(($invoice->tax_amount ?? 0) > 0)
        <div class="total-row">
            <span>Tax:</span>
            <span>R{{ number_format($invoice->tax_amount, 2) }}</span>
        </div>
        @endif
        @if(($invoice->discount_amount ?? 0) > 0)
        <div class="total-row">
            <span>Discount:</span>
            <span>-R{{ number_format($invoice->discount_amount, 2) }}</span>
        </div>
        @endif
        @if(abs($roundingAdjustment) > 0.0001)
        <div class="total-row">
            <span>Rounding Adjustment:</span>
            <span>R{{ number_format($roundingAdjustment, 2) }}</span>
        </div>
        @endif
        <div class="total-row final">
            <span>Total:</span>
            <span>R{{ number_format($invoice->total ?? 0, 2) }}</span>
        </div>
    </div>

    @if($invoice->notes || $invoice->terms)
    <div class="terms-section">
        @if($invoice->notes)
            <h4>Notes</h4>
            <p>{{ $invoice->notes }}</p>
        @endif
        @if($invoice->terms)
            <h4>Terms & Conditions</h4>
            <p>{{ $invoice->terms }}</p>
        @endif
    </div>
    @endif
    
    <div class="signature-section">
        <div class="signature-row">
            <div class="signature-item">
                <div>Received by</div>
                <div class="signature-line"></div>
            </div>
            <div class="signature-item">
                <div>Date</div>
                <div class="signature-line"></div>
            </div>
            <div class="signature-item">
                <div>Signed</div>
                <div class="signature-line"></div>
            </div>
        </div>
    </div>
    
    <div class="payment-clause">
        Goods will only be released once payment has reflected in our account.
    </div>
    
    <div class="footer">
        <div class="footer-left">
            JobCard Online (Registered to {{ $company->name ?? 'Company' }})
        </div>
        <div class="footer-right">
            {{ date('Y/m/d H:i:s') }}
        </div>
    </div>
</body>
</html>
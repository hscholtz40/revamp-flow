<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quote #{{ $quote->quote_number ?? 'N/A' }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.4;
            color: #000;
            margin: 0;
            font-size: 12px;
            background: white;
        }
        
        .page-header {
            text-align: right;
            font-size: 10px;
            color: #666;
            margin-bottom: 6px;
        }
        
        .header-row {
            display: table;
            width: 100%;
            margin-bottom: 12px;
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
            margin-bottom: 12px;
            border: 1px solid #000;
            padding: 10px;
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
            margin-bottom: 12px;
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
        
        .validity-period {
            font-size: 11px;
        }
        
        .validity-period label {
            font-weight: bold;
        }
        
        .separator-line {
            border-top: 1px solid #000;
            margin: 10px 0;
        }
        
        .quote-details {
            display: table;
            width: 100%;
            margin-bottom: 12px;
        }
        
        .quote-detail {
            display: table-cell;
            width: 25%;
            border: 1px solid #000;
            padding: 2px;
            text-align: center;
            font-size: 11px;
        }
        
        .quote-detail-label {
            font-weight: bold;
            display: block;
            margin-bottom: 4px;
        }
        
        .quote-detail-value {
            color: #000;
        }
        
        .line-items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        
        .line-items-table th,
        .line-items-table td {
            padding: 2px;
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

        .signature-card {
            display: inline-block;
            width: 48%;
            margin: 0 1% 12px 1%;
            border: 1px solid #ddd;
            padding: 8px;
            vertical-align: top;
            box-sizing: border-box;
        }

        .signature-image {
            height: 70px;
            width: 100%;
            object-fit: contain;
            border: 1px solid #eee;
            background: #fff;
        }
        
        .signature-line {
            border-bottom: 1px dashed #000;
            height: 20px;
            margin-top: 5px;
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
        <div class="document-title">Quote</div>
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
                <p><strong>{{ $quote->customer->account_code ?? 'N/A' }}</strong></p>
                <p><strong>{{ $quote->customer->name ?? 'CUSTOMER NAME' }}</strong></p>
                @if($quote->contact)
                    <p><em>Attn: {{ $quote->contact->name }}</em></p>
                @endif
                @if($quote->customer->address)
                    <p>{{ $quote->customer->address }}</p>
                @endif
                @if($quote->customer->city)
                    <p>{{ $quote->customer->city }}</p>
                @endif
                @if($quote->customer->postal_code)
                    <p>{{ $quote->customer->postal_code }}</p>
                @endif
                @php
                    $quoteDisplayEmail = null;
                    if ($quote->email && stripos($quote->email, 'sage-migration.local') === false) {
                        $quoteDisplayEmail = $quote->email;
                    } elseif ($quote->customer->email && stripos($quote->customer->email, 'sage-migration.local') === false) {
                        $quoteDisplayEmail = $quote->customer->email;
                    }
                @endphp
                @if($quoteDisplayEmail)
                    <p>{{ $quoteDisplayEmail }}</p>
                @endif
                @if($quote->phone)
                    <p>{{ $quote->phone }}</p>
                @elseif($quote->customer->phone)
                    <p>{{ $quote->customer->phone }}</p>
                @endif
            </div>
        </div>
        
        <div class="customer-right">
            <div class="validity-period">
                <label>Valid Until</label>
                <div style="border: 1px solid #000; height: 20px; margin-top: 5px; padding: 2px;">
                    {{ $quote->expiry_date ? \Carbon\Carbon::parse($quote->expiry_date)->format('Y/m/d') : date('Y/m/d', strtotime('+30 days')) }}
                </div>
            </div>
        </div>
    </div>
    
    @if($quote->description)
    <div style="margin-bottom: 12px; font-size: 11px;">
        <h4 style="margin: 0 0 6px 0; font-size: 12px; font-weight: bold; color: #000;">Description</h4>
        <p style="margin: 0; line-height: 1.4;">{{ $quote->description }}</p>
    </div>
    @endif
    
    <div class="separator-line"></div>
    
    <div class="quote-details">
        <div class="quote-detail">
            <span class="quote-detail-label">Account</span>
            <span class="quote-detail-value">{{ $quote->customer->account_code ?? 'N/A' }}</span>
        </div>
        <div class="quote-detail">
            <span class="quote-detail-label">Date</span>
            <span class="quote-detail-value">{{ $quote->created_at ? \Carbon\Carbon::parse($quote->created_at)->format('Y/m/d') : date('Y/m/d') }}</span>
        </div>
        <div class="quote-detail">
            <span class="quote-detail-label">Order No</span>
            <span class="quote-detail-value">{{ $quote->order_number ?? '' }}</span>
        </div>
        <div class="quote-detail">
            <span class="quote-detail-label">Quote No</span>
            <span class="quote-detail-value">{{ $quote->quote_number ?? 'N/A' }}</span>
        </div>
    </div>
    
    <table class="line-items-table">
        <thead>
            <tr>
                <th>Item Description</th>
                <th class="text-right">QTY</th>
                <th class="text-right">Price (Ex)</th>
                <th class="text-right">Discount</th>
                <th class="text-right">Tax</th>
                <th class="text-right">Total (Incl)</th>
            </tr>
        </thead>
        <tbody>
            @php
                $lineGroups = $quote->lineGroups ?? collect();
                $items = ($quote->lineItems ?? collect())->filter(function ($item) {
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
                        <tr class="group-header"><td colspan="6" style="font-weight: bold; background: #f5f5f5; padding: 4px 2px;">{{ $group->name }}</td></tr>
                    @endif
                    @foreach($group->items->sortBy('sort_order') as $item)
                <tr>
                    <td>{{ $item->description ?? 'Item Description' }}{{ ($item->product && ($item->product->sku ?? $item->product->barcode)) ? ' (' . ($item->product->sku ?? $item->product->barcode) . ')' : '' }}</td>
                    <td class="text-right">{{ $company->formatNumber($item->quantity ?? 0, 2) }}</td>
                    <td class="text-right">{{ $company->formatCurrencyZar($item->unit_price ?? 0) }}</td>
                    <td class="text-right">
                        @if(($item->discount_percentage ?? 0) > 0)
                            {{ $company->formatNumber($item->discount_percentage, 2) }}%
                        @elseif(($item->discount_amount ?? 0) > 0)
                            {{ $company->formatCurrencyZar($item->discount_amount) }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-right">
                        @if($item->taxRate)
                            {{ $company->formatCurrencyZar($item->tax_amount ?? 0) }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-right">{{ $company->formatCurrencyZar($item->total ?? 0) }}</td>
                </tr>
                    @endforeach
            @endforeach
        </tbody>
    </table>
    
    @php
        $roundingAdjustment = ($quote->lineItems ?? collect())->reduce(function ($sum, $item) {
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
            <span>{{ $company->formatCurrencyZar($quote->subtotal ?? 0) }}</span>
        </div>
        @if(($quote->tax_amount ?? 0) > 0)
        <div class="total-row">
            <span>Tax:</span>
            <span>{{ $company->formatCurrencyZar($quote->tax_amount) }}</span>
        </div>
        @endif
        @if(($quote->discount_amount ?? 0) > 0)
        <div class="total-row">
            <span>Discount:</span>
            <span>-{{ $company->formatCurrencyZar($quote->discount_amount) }}</span>
        </div>
        @endif
        @if(abs($roundingAdjustment) > 0.0001)
        <div class="total-row">
            <span>Rounding Adjustment:</span>
            <span>{{ $company->formatCurrencyZar($roundingAdjustment) }}</span>
        </div>
        @endif
        <div class="total-row final">
            <span>Total:</span>
            <span>{{ $company->formatCurrencyZar($quote->total ?? 0) }}</span>
        </div>
    </div>
    
    <div class="terms-section" style="text-align: center;">
        @if($quote->notes)
            <h4>Notes</h4>
            <p>{{ $quote->notes }}</p>
        @endif
        <h4>Terms & Conditions</h4>
        <p>This quote is valid for 30 days from the date of issue. Prices are subject to change without notice.</p>
        @if($quote->terms_conditions)
            <p>{{ $quote->terms_conditions }}</p>
        @endif
    </div>
    
    @if(($quote->signatures ?? collect())->count() > 0)
        <div class="signature-section">
            <div style="font-weight: bold; margin-bottom: 10px;">Signatures</div>
            @foreach($quote->signatures as $signature)
                <div class="signature-card">
                    <div style="font-size: 10px; margin-bottom: 6px;">
                        <strong>{{ $signature->signer_name }}</strong>
                        @if($signature->signed_at)
                            - {{ $signature->signed_at->format('Y/m/d H:i') }}
                        @endif
                    </div>
                    @php($signatureDataUri = $signature->getSignaturePathForPdf())
                    @if($signatureDataUri)
                        <img src="{{ $signatureDataUri }}" alt="Signature" class="signature-image">
                    @endif
                </div>
            @endforeach
        </div>
    @endif
    
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
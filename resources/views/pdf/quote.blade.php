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
            max-width: 240px;
            max-height: 120px;
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
        
        .company-details p {
            margin: 2px 0;
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
        
        .validity-period {
            font-size: 11px;
        }
        
        .validity-period label {
            font-weight: bold;
        }
        
        .separator-line {
            border-top: 1px solid #000;
            margin: 15px 0;
        }
        
        .quote-details {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        
        .quote-detail {
            display: table-cell;
            width: 25%;
            border: 1px solid #000;
            padding: 8px;
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
            margin-bottom: 20px;
        }
        
        .line-items-table th,
        .line-items-table td {
            border-bottom: 1px solid #000;
            padding: 6px;
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
                <p><strong>{{ $company->legal_name ?? $company->name }}</strong></p>
                @if($company->address)
                    <p>{{ $company->address }}</p>
                @endif
                @if($company->city)
                    <p>{{ $company->city }}</p>
                @endif
                @if($company->vat_number)
                    <p>VAT Number {{ $company->vat_number }}</p>
                @endif
                @if($company->phone)
                    <p>Telephone {{ $company->phone }}</p>
                @endif
                @if($company->email)
                    <p>Email {{ $company->email }}</p>
                @endif
                @if($company->fax)
                    <p>Fax {{ $company->fax }}</p>
                @endif
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
                @if($quote->customer->address)
                    <p>{{ $quote->customer->address }}</p>
                @endif
                @if($quote->customer->city)
                    <p>{{ $quote->customer->city }}</p>
                @endif
                @if($quote->customer->postal_code)
                    <p>{{ $quote->customer->postal_code }}</p>
                @endif
                @if($quote->email)
                    <p>{{ $quote->email }}</p>
                @elseif($quote->customer->email)
                    <p>{{ $quote->customer->email }}</p>
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
            @foreach($quote->lineItems ?? [] as $item)
                <tr>
                    <td>{{ $item->description ?? 'Item Description' }}</td>
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
                            <div style="font-size: 9px; color: #666;">{{ $item->taxRate->name }} ({{ $item->taxRate->rate }}%)</div>
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-right">R{{ number_format($item->total ?? 0, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    
    <div class="totals-section">
        <div class="total-row">
            <span>Subtotal:</span>
            <span>R{{ number_format($quote->subtotal ?? 0, 2) }}</span>
        </div>
        @if(($quote->tax_amount ?? 0) > 0)
        <div class="total-row">
            <span>Tax:</span>
            <span>R{{ number_format($quote->tax_amount, 2) }}</span>
        </div>
        @endif
        @if(($quote->discount_amount ?? 0) > 0)
        <div class="total-row">
            <span>Discount:</span>
            <span>-R{{ number_format($quote->discount_amount, 2) }}</span>
        </div>
        @endif
        <div class="total-row final">
            <span>Total:</span>
            <span>R{{ number_format($quote->total ?? 0, 2) }}</span>
        </div>
    </div>
    
    <div class="terms-section">
        @if($quote->description)
            <h4>Description</h4>
            <p>{{ $quote->description }}</p>
        @endif
        @if($quote->notes)
            <h4>Notes</h4>
            <p>{{ $quote->notes }}</p>
        @endif
        <h4>Terms & Conditions</h4>
        <p>This quote is valid for 30 days from the date of issue.</p>
        <p>Prices are subject to change without notice.</p>
        <p>Payment terms: Net 30 days from invoice date.</p>
        @if($quote->terms_conditions)
            <p>{{ $quote->terms_conditions }}</p>
        @endif
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
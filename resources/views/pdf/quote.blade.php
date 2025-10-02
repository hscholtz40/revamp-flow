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
            color: #333;
            margin: 0;
            padding: 20px;
            font-size: 12px;
        }
        .header {
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
            margin-bottom: 20px;
            display: table;
            width: 100%;
        }
        .header-left {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .header-right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            text-align: right;
        }
        .company-info {
            margin-bottom: 15px;
        }
        .company-logo {
            max-width: 150px;
            max-height: 80px;
            margin-bottom: 10px;
        }
        .company-name {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }
        .document-type {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        .quote-title {
            font-size: 16px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        .quote-number {
            font-size: 14px;
            color: #666;
        }
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .info-section {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 20px;
        }
        .info-section h3 {
            margin: 0 0 8px 0;
            color: #333;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 2px;
        }
        .info-section p {
            margin: 0 0 4px 0;
            font-size: 11px;
        }
        .description-section {
            margin-bottom: 20px;
        }
        .description-section h3 {
            margin: 0 0 8px 0;
            color: #333;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 2px;
        }
        .line-items {
            margin-bottom: 20px;
        }
        .line-items h3 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 2px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            font-size: 11px;
        }
        th {
            background-color: #f5f5f5;
            font-weight: bold;
            color: #333;
        }
        .text-right {
            text-align: right;
        }
        .totals {
            margin-top: 15px;
            padding-top: 10px;
            border-top: 2px solid #333;
        }
        .total-row {
            display: table;
            width: 100%;
            margin-bottom: 5px;
        }
        .total-row span {
            display: table-cell;
            font-size: 11px;
        }
        .total-row span:last-child {
            text-align: right;
        }
        .total-row.final {
            font-weight: bold;
            font-size: 13px;
            color: #333;
            border-top: 1px solid #ccc;
            padding-top: 8px;
            margin-top: 8px;
        }
        .notes-section, .terms-section {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #ccc;
        }
        .notes-section h3, .terms-section h3 {
            margin: 0 0 8px 0;
            color: #333;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 2px;
        }
        .notes-section p, .terms-section p {
            margin: 0;
            font-size: 11px;
            line-height: 1.4;
        }
        .message-section {
            background-color: #f9f9f9;
            padding: 10px;
            border-left: 4px solid #007cba;
            margin-bottom: 15px;
        }
        .message-section strong {
            color: #333;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <div class="company-info">
                @if($quote->company->getLogoPathForPdf())
                    <img src="{{ $quote->company->getLogoPathForPdf() }}" alt="Company Logo" class="company-logo">
                @endif
                <div class="company-name">{{ $quote->company->name ?? 'Company Name' }}</div>
                @if($quote->company->email ?? false)
                    <div style="font-size: 10px; color: #666;">{{ $quote->company->email }}</div>
                @endif
                @if($quote->company->phone ?? false)
                    <div style="font-size: 10px; color: #666;">{{ $quote->company->phone }}</div>
                @endif
            </div>
        </div>
        
        <div class="header-right">
            <div class="document-type">QUOTE</div>
            <div class="quote-title">{{ $quote->title ?? 'Quote Title' }}</div>
            <div class="quote-number">Quote #{{ $quote->quote_number ?? 'N/A' }}</div>
        </div>
    </div>

    @if($customMessage ?? false)
        <div class="message-section">
            <strong>Message:</strong><br>
            {{ $customMessage }}
        </div>
    @endif

    <div class="info-grid">
        <div class="info-section">
            <h3>Customer Information</h3>
            <p><strong>{{ $quote->customer->name ?? 'Customer Name' }}</strong></p>
            @if($quote->customer->email ?? false)
                <p>{{ $quote->customer->email }}</p>
            @endif
            @if($quote->customer->phone ?? false)
                <p>{{ $quote->customer->phone }}</p>
            @endif
            @if($quote->customer->address ?? false)
                <p>{{ $quote->customer->address }}</p>
            @endif
        </div>
        
        <div class="info-section">
            <h3>Quote Details</h3>
            <p><strong>Status:</strong> {{ ucfirst($quote->status ?? 'draft') }}</p>
            @if($quote->expiry_date ?? false)
                <p><strong>Expiry Date:</strong> {{ \Carbon\Carbon::parse($quote->expiry_date)->format('M d, Y') }}</p>
            @endif
            <p><strong>Created:</strong> {{ \Carbon\Carbon::parse($quote->created_at)->format('M d, Y') }}</p>
        </div>
    </div>

    @if($quote->description ?? false)
        <div class="description-section">
            <h3>Description</h3>
            <p>{{ $quote->description }}</p>
        </div>
    @endif

    <div class="line-items">
        <h3>Line Items</h3>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Unit Price</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($quote->lineItems ?? [] as $item)
                    <tr>
                        <td>{{ $item->description ?? 'Item Description' }}</td>
                        <td class="text-right">{{ $item->quantity ?? 0 }}</td>
                        <td class="text-right">R{{ number_format($item->unit_price ?? 0, 2) }}</td>
                        <td class="text-right">R{{ number_format($item->total ?? 0, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <div class="total-row">
                <span>Subtotal:</span>
                <span>R{{ number_format($quote->subtotal ?? 0, 2) }}</span>
            </div>
            @if(($quote->discount_amount ?? 0) > 0)
                <div class="total-row">
                    <span>Discount:</span>
                    <span>-R{{ number_format($quote->discount_amount ?? 0, 2) }}</span>
                </div>
            @endif
            @if(($quote->tax_rate ?? 0) > 0)
                <div class="total-row">
                    <span>Tax ({{ $quote->tax_rate }}%):</span>
                    <span>R{{ number_format($quote->tax_amount ?? 0, 2) }}</span>
                </div>
            @endif
            <div class="total-row final">
                <span>Total:</span>
                <span>R{{ number_format($quote->total ?? 0, 2) }}</span>
            </div>
        </div>
    </div>

    @if($quote->notes ?? false)
        <div class="notes-section">
            <h3>Notes</h3>
            <p>{{ $quote->notes }}</p>
        </div>
    @endif

    @if($quote->terms_conditions ?? false)
        <div class="terms-section">
            <h3>Terms & Conditions</h3>
            <p>{{ $quote->terms_conditions }}</p>
        </div>
    @endif

    @if($quote->company->quote_footer ?? false)
        <div class="footer-section">
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ccc; text-align: center; font-size: 10px; color: #666; line-height: 1.4;">
                {{ $quote->company->quote_footer }}
            </div>
        </div>
    @endif
</body>
</html>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice #{{ $invoice->invoice_number ?? 'N/A' }}</title>
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
        .invoice-title {
            font-size: 16px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        .invoice-number {
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
                @if($invoice->company->getLogoPathForPdf())
                    <img src="{{ $invoice->company->getLogoPathForPdf() }}" alt="Company Logo" class="company-logo">
                @endif
                <div class="company-name">{{ $invoice->company->name ?? 'Company Name' }}</div>
                @if($invoice->company->email ?? false)
                    <div style="font-size: 10px; color: #666;">{{ $invoice->company->email }}</div>
                @endif
                @if($invoice->company->phone ?? false)
                    <div style="font-size: 10px; color: #666;">{{ $invoice->company->phone }}</div>
                @endif
            </div>
        </div>
        
        <div class="header-right">
            <div class="document-type">INVOICE</div>
            <div class="invoice-title">{{ $invoice->title ?? 'Invoice Title' }}</div>
            <div class="invoice-number">Invoice #{{ $invoice->invoice_number ?? 'N/A' }}</div>
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
            <p><strong>{{ $invoice->customer->name ?? 'Customer Name' }}</strong></p>
            @if($invoice->customer->email ?? false)
                <p>{{ $invoice->customer->email }}</p>
            @endif
            @if($invoice->customer->phone ?? false)
                <p>{{ $invoice->customer->phone }}</p>
            @endif
            @if($invoice->customer->address ?? false)
                <p>{{ $invoice->customer->address }}</p>
            @endif
        </div>
        
        <div class="info-section">
            <h3>Invoice Details</h3>
            <p><strong>Status:</strong> {{ ucfirst($invoice->status ?? 'draft') }}</p>
            @if($invoice->invoice_date ?? false)
                <p><strong>Invoice Date:</strong> {{ \Carbon\Carbon::parse($invoice->invoice_date)->format('M d, Y') }}</p>
            @endif
            @if($invoice->due_date ?? false)
                <p><strong>Due Date:</strong> {{ \Carbon\Carbon::parse($invoice->due_date)->format('M d, Y') }}</p>
            @endif
            <p><strong>Created:</strong> {{ \Carbon\Carbon::parse($invoice->created_at)->format('M d, Y') }}</p>
        </div>
    </div>

    @if($invoice->description ?? false)
        <div class="description-section">
            <h3>Description</h3>
            <p>{{ $invoice->description }}</p>
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
                @foreach($invoice->lineItems ?? [] as $item)
                    <tr>
                        <td>{{ $item->description ?? 'Item Description' }}</td>
                        <td class="text-right">{{ $item->quantity ?? 0 }}</td>
                        <td class="text-right">R{{ number_format($item->unit_price ?? 0, 2) }}</td>
                        <td class="text-right">R{{ number_format($item->total ?? 0, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

        <div class="totals">
            <div class="total-row">
                <span>Subtotal:</span>
                <span>R{{ number_format($invoice->subtotal ?? 0, 2) }}</span>
            </div>
            @if(($invoice->discount_amount ?? 0) > 0)
                <div class="total-row">
                    <span>Discount:</span>
                    <span>-R{{ number_format($invoice->discount_amount ?? 0, 2) }}</span>
                </div>
            @endif
            @if(($invoice->tax_rate ?? 0) > 0)
                <div class="total-row">
                    <span>Tax ({{ $invoice->tax_rate }}%):</span>
                    <span>R{{ number_format($invoice->tax_amount ?? 0, 2) }}</span>
                </div>
            @endif
            <div class="total-row final">
                <span>Total:</span>
                <span>R{{ number_format($invoice->total ?? 0, 2) }}</span>
            </div>
        </div>
    </div>

    @if($invoice->notes ?? false)
        <div class="notes-section">
            <h3>Notes</h3>
            <p>{{ $invoice->notes }}</p>
        </div>
    @endif

    @if($invoice->terms_conditions ?? false)
        <div class="terms-section">
            <h3>Terms & Conditions</h3>
            <p>{{ $invoice->terms_conditions }}</p>
        </div>
    @endif

    @if($invoice->company->invoice_footer ?? false)
        <div class="footer-section">
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ccc; text-align: center; font-size: 10px; color: #666; line-height: 1.4;">
                {{ $invoice->company->invoice_footer }}
            </div>
        </div>
    @endif
</body>
</html>

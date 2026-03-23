<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f8fafc;
            color: #334155;
            line-height: 1.6;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: bold;
        }
        .header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        .content {
            padding: 30px;
        }
        .invoice-details {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        .company-info {
            flex: 1;
        }
        .company-name {
            font-size: 20px;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 8px;
        }
        .invoice-info {
            text-align: right;
            flex: 1;
        }
        .invoice-number {
            font-size: 24px;
            font-weight: bold;
            color: #3b82f6;
            margin-bottom: 5px;
        }
        .invoice-title {
            font-size: 18px;
            color: #64748b;
            margin-bottom: 10px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-draft { background-color: #f1f5f9; color: #475569; }
        .status-sent { background-color: #dbeafe; color: #1e40af; }
        .status-paid { background-color: #dcfce7; color: #166534; }
        .status-overdue { background-color: #fecaca; color: #991b1b; }
        .status-cancelled { background-color: #f1f5f9; color: #475569; }
        .customer-section {
            margin-bottom: 30px;
        }
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 5px;
        }
        .customer-info {
            background-color: #f8fafc;
            border-radius: 6px;
            padding: 15px;
        }
        .customer-name {
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 5px;
        }
        .line-items {
            margin-bottom: 30px;
        }
        .line-items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        .line-items-table th {
            background-color: #f1f5f9;
            padding: 15px;
            text-align: left;
            font-weight: bold;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
        }
        .line-items-table td {
            padding: 15px;
            border-bottom: 1px solid #f1f5f9;
        }
        .line-items-table .text-right {
            text-align: right;
        }
        .totals {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .totals-row.total {
            font-weight: bold;
            font-size: 18px;
            border-top: 2px solid #3b82f6;
            border-bottom: none;
            padding-top: 15px;
            margin-top: 10px;
            color: #1e293b;
        }
        .message {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin-bottom: 30px;
            border-radius: 0 6px 6px 0;
        }
        .message strong {
            color: #92400e;
        }
        .next-steps {
            background-color: #ecfdf5;
            border-left: 4px solid #10b981;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 0 6px 6px 0;
        }
        .next-steps h3 {
            margin: 0 0 10px 0;
            color: #065f46;
            font-size: 16px;
        }
        .next-steps ul {
            margin: 0;
            padding-left: 20px;
            color: #047857;
        }
        .next-steps li {
            margin-bottom: 5px;
        }
        .footer {
            background-color: #f8fafc;
            padding: 20px 30px;
            text-align: center;
            color: #64748b;
            font-size: 14px;
            border-top: 1px solid #e2e8f0;
        }
        .footer p {
            margin: 5px 0;
        }
        .due-date-notice {
            background-color: #fef2f2;
            border-left: 4px solid #ef4444;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 0 6px 6px 0;
        }
        .due-date-notice strong {
            color: #991b1b;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Invoice</h1>
            <p>{{ $invoice->company->name }}</p>
        </div>

        <div class="content">
            <div class="invoice-details">
                <div class="invoice-header">
                    <div class="company-info">
                        <div class="company-name">{{ $invoice->company->name }}</div>
                        @if($invoice->company->address)
                            <div>{{ $invoice->company->address }}</div>
                        @endif
                        @if($invoice->company->phone)
                            <div>{{ $invoice->company->phone }}</div>
                        @endif
                        @if($invoice->company->email)
                            <div>{{ $invoice->company->email }}</div>
                        @endif
                    </div>
                    
                    <div class="invoice-info">
                        <div class="invoice-number">{{ $invoice->invoice_number }}</div>
                        <div class="invoice-title">{{ $invoice->title }}</div>
                        <div>
                            <span class="status-badge status-{{ $invoice->status }}">{{ $invoice->status }}</span>
                        </div>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; margin-top: 20px;">
                    <div>
                        <strong>Invoice Date:</strong> {{ \Carbon\Carbon::parse($invoice->invoice_date)->format('M d, Y') }}
                    </div>
                    <div>
                        <strong>Due Date:</strong> {{ \Carbon\Carbon::parse($invoice->due_date)->format('M d, Y') }}
                    </div>
                </div>
            </div>

            @if($customMessage)
                <div class="message">
                    <strong>Message:</strong><br>
                    {{ $customMessage }}
                </div>
            @endif

            <div class="customer-section">
                <div class="section-title">Bill To</div>
                <div class="customer-info">
                    <div class="customer-name">{{ $invoice->customer->name }}</div>
                    @if($invoice->customer->email)
                        <div>{{ $invoice->customer->email }}</div>
                    @endif
                    @if($invoice->customer->phone)
                        <div>{{ $invoice->customer->phone }}</div>
                    @endif
                    @if($invoice->customer->address)
                        <div>{{ $invoice->customer->address }}</div>
                    @endif
                </div>
            </div>

            @if($invoice->description)
                <div class="customer-section">
                    <div class="section-title">Description</div>
                    <div>{{ $invoice->description }}</div>
                </div>
            @endif

            <div class="line-items">
                <div class="section-title">Invoice Items</div>
                <table class="line-items-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="text-right">Quantity</th>
                            <th class="text-right">Unit Price</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->lineItems as $item)
                            <tr>
                                <td>{{ $item->description }}</td>
                                <td class="text-right">{{ $item->quantity }}</td>
                                <td class="text-right">{{ $invoice->company->formatCurrencyZar($item->unit_price) }}</td>
                                <td class="text-right">{{ $invoice->company->formatCurrencyZar($item->total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="totals">
                <div class="totals-row">
                    <span>Subtotal:</span>
                    <span>{{ $invoice->company->formatCurrencyZar($invoice->subtotal) }}</span>
                </div>
                <div class="totals-row">
                    <span>Tax ({{ $invoice->tax_rate }}%):</span>
                    <span>{{ $invoice->company->formatCurrencyZar($invoice->tax_amount) }}</span>
                </div>
                <div class="totals-row total">
                    <span>Total:</span>
                    <span>{{ $invoice->company->formatCurrencyZar($invoice->total) }}</span>
                </div>
            </div>

            @if($invoice->due_date < now())
                <div class="due-date-notice">
                    <strong>Important:</strong> This invoice is overdue. Please make payment as soon as possible.
                </div>
            @endif

            <div class="next-steps">
                <h3>Next Steps</h3>
                <ul>
                    <li>Review the invoice details above</li>
                    <li>Make payment by the due date: {{ \Carbon\Carbon::parse($invoice->due_date)->format('M d, Y') }}</li>
                    <li>Contact us if you have any questions about this invoice</li>
                    <li>Keep this email for your records</li>
                </ul>
            </div>

            @if($invoice->notes)
                <div class="customer-section">
                    <div class="section-title">Notes</div>
                    <div>{{ $invoice->notes }}</div>
                </div>
            @endif

            @if($invoice->terms)
                <div class="customer-section">
                    <div class="section-title">Terms & Conditions</div>
                    <div>{{ $invoice->terms }}</div>
                </div>
            @endif
        </div>

        <div class="footer">
            <p><strong>{{ $invoice->company->name }}</strong></p>
            <p>Thank you for your business!</p>
            <p>This invoice was generated on {{ now()->format('M d, Y \a\t g:i A') }}</p>
        </div>
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quote {{ $quote->quote_number }}</title>
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
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
        .quote-details {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .quote-header {
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
        .quote-info {
            text-align: right;
            flex: 1;
        }
        .quote-number {
            font-size: 24px;
            font-weight: bold;
            color: #10b981;
            margin-bottom: 5px;
        }
        .quote-title {
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
        .status-accepted { background-color: #dcfce7; color: #166534; }
        .status-rejected { background-color: #fecaca; color: #991b1b; }
        .status-expired { background-color: #f1f5f9; color: #475569; }
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
            border-top: 2px solid #10b981;
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
        .expiry-notice {
            background-color: #fef2f2;
            border-left: 4px solid #ef4444;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 0 6px 6px 0;
        }
        .expiry-notice strong {
            color: #991b1b;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Quote</h1>
            <p>{{ $quote->company->name }}</p>
        </div>

        <div class="content">
            <div class="quote-details">
                <div class="quote-header">
                    <div class="company-info">
                        <div class="company-name">{{ $quote->company->name }}</div>
                        @if($quote->company->address)
                            <div>{{ $quote->company->address }}</div>
                        @endif
                        @if($quote->company->phone)
                            <div>{{ $quote->company->phone }}</div>
                        @endif
                        @if($quote->company->email)
                            <div>{{ $quote->company->email }}</div>
                        @endif
                    </div>
                    
                    <div class="quote-info">
                        <div class="quote-number">{{ $quote->quote_number }}</div>
                        <div class="quote-title">{{ $quote->title }}</div>
                        <div>
                            <span class="status-badge status-{{ $quote->status }}">{{ $quote->status }}</span>
                        </div>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; margin-top: 20px;">
                    <div>
                        <strong>Created:</strong> {{ \Carbon\Carbon::parse($quote->created_at)->format('M d, Y') }}
                    </div>
                    @if($quote->expiry_date)
                        <div>
                            <strong>Expires:</strong> {{ \Carbon\Carbon::parse($quote->expiry_date)->format('M d, Y') }}
                        </div>
                    @endif
                </div>
            </div>

            @if($customMessage)
                <div class="message">
                    <strong>Message:</strong><br>
                    {{ $customMessage }}
                </div>
            @endif

            <div class="customer-section">
                <div class="section-title">Quote For</div>
                <div class="customer-info">
                    <div class="customer-name">{{ $quote->customer->name }}</div>
                    @if($quote->customer->email)
                        <div>{{ $quote->customer->email }}</div>
                    @endif
                    @if($quote->customer->phone)
                        <div>{{ $quote->customer->phone }}</div>
                    @endif
                    @if($quote->customer->address)
                        <div>{{ $quote->customer->address }}</div>
                    @endif
                </div>
            </div>

            @if($quote->description)
                <div class="customer-section">
                    <div class="section-title">Description</div>
                    <div>{{ $quote->description }}</div>
                </div>
            @endif

            <div class="line-items">
                <div class="section-title">Quote Items</div>
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
                        @foreach($quote->lineItems as $item)
                            <tr>
                                <td>{{ $item->description }}</td>
                                <td class="text-right">{{ $item->quantity }}</td>
                                <td class="text-right">{{ $quote->company->formatCurrencyZar($item->unit_price) }}</td>
                                <td class="text-right">{{ $quote->company->formatCurrencyZar($item->total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="totals">
                <div class="totals-row">
                    <span>Subtotal:</span>
                    <span>{{ $quote->company->formatCurrencyZar($quote->subtotal) }}</span>
                </div>
                @if($quote->discount_amount > 0)
                    <div class="totals-row">
                        <span>Discount:</span>
                        <span>-{{ $quote->company->formatCurrencyZar($quote->discount_amount) }}</span>
                    </div>
                @endif
                <div class="totals-row">
                    <span>Tax ({{ $quote->tax_rate }}%):</span>
                    <span>{{ $quote->company->formatCurrencyZar($quote->tax_amount) }}</span>
                </div>
                <div class="totals-row total">
                    <span>Total:</span>
                    <span>{{ $quote->company->formatCurrencyZar($quote->total) }}</span>
                </div>
            </div>

            @if($quote->expiry_date && $quote->expiry_date < now())
                <div class="expiry-notice">
                    <strong>Important:</strong> This quote has expired. Please contact us for a new quote.
                </div>
            @elseif($quote->expiry_date)
                <div class="expiry-notice">
                    <strong>Important:</strong> This quote is valid until {{ \Carbon\Carbon::parse($quote->expiry_date)->format('M d, Y') }}.
                </div>
            @endif

            <div class="next-steps">
                <h3>Next Steps</h3>
                <ul>
                    <li>Review the quote details above</li>
                    <li>Contact us to accept this quote or ask questions</li>
                    <li>We can convert this quote to a jobcard to begin work</li>
                    <li>Keep this email for your records</li>
                </ul>
            </div>

            @if($quote->notes)
                <div class="customer-section">
                    <div class="section-title">Notes</div>
                    <div>{{ $quote->notes }}</div>
                </div>
            @endif

            @if($quote->terms_conditions)
                <div class="customer-section">
                    <div class="section-title">Terms & Conditions</div>
                    <div>{{ $quote->terms_conditions }}</div>
                </div>
            @endif
        </div>

        <div class="footer">
            <p><strong>{{ $quote->company->name }}</strong></p>
            <p>Thank you for considering our services!</p>
            <p>This quote was generated on {{ now()->format('M d, Y \a\t g:i A') }}</p>
        </div>
    </div>
</body>
</html>
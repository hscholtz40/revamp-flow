<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jobcard {{ $jobcard->job_number }}</title>
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
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
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
        .jobcard-details {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .jobcard-header {
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
        .jobcard-info {
            text-align: right;
            flex: 1;
        }
        .jobcard-number {
            font-size: 24px;
            font-weight: bold;
            color: #f59e0b;
            margin-bottom: 5px;
        }
        .jobcard-title {
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
        .status-in-progress { background-color: #dbeafe; color: #1e40af; }
        .status-completed { background-color: #dcfce7; color: #166534; }
        .status-on-hold { background-color: #fef3c7; color: #92400e; }
        .status-cancelled { background-color: #fecaca; color: #991b1b; }
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
            border-top: 2px solid #f59e0b;
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
            <h1>Jobcard</h1>
            <p>{{ $jobcard->company->name }}</p>
        </div>

        <div class="content">
            <div class="jobcard-details">
                <div class="jobcard-header">
                    <div class="company-info">
                        <div class="company-name">{{ $jobcard->company->name }}</div>
                        @if($jobcard->company->address)
                            <div>{{ $jobcard->company->address }}</div>
                        @endif
                        @if($jobcard->company->phone)
                            <div>{{ $jobcard->company->phone }}</div>
                        @endif
                        @if($jobcard->company->email)
                            <div>{{ $jobcard->company->email }}</div>
                        @endif
                    </div>
                    
                    <div class="jobcard-info">
                        <div class="jobcard-number">{{ $jobcard->job_number }}</div>
                        <div class="jobcard-title">{{ $jobcard->title }}</div>
                        <div>
                            <span class="status-badge status-{{ $jobcard->status }}">{{ $jobcard->status }}</span>
                        </div>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; margin-top: 20px;">
                    @if($jobcard->start_date)
                        <div>
                            <strong>Start Date:</strong> {{ \Carbon\Carbon::parse($jobcard->start_date)->format('M d, Y') }}
                        </div>
                    @endif
                    @if($jobcard->due_date)
                        <div>
                            <strong>Due Date:</strong> {{ \Carbon\Carbon::parse($jobcard->due_date)->format('M d, Y') }}
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
                <div class="section-title">Customer</div>
                <div class="customer-info">
                    <div class="customer-name">{{ $jobcard->customer->name }}</div>
                    @if($jobcard->customer->email)
                        <div>{{ $jobcard->customer->email }}</div>
                    @endif
                    @if($jobcard->customer->phone)
                        <div>{{ $jobcard->customer->phone }}</div>
                    @endif
                    @if($jobcard->customer->address)
                        <div>{{ $jobcard->customer->address }}</div>
                    @endif
                </div>
            </div>

            @if($jobcard->description)
                <div class="customer-section">
                    <div class="section-title">Description</div>
                    <div>{{ $jobcard->description }}</div>
                </div>
            @endif

            <div class="line-items">
                <div class="section-title">Job Items</div>
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
                        @foreach($jobcard->lineItems as $item)
                            <tr>
                                <td>{{ $item->description }}</td>
                                <td class="text-right">{{ $item->quantity }}</td>
                                <td class="text-right">{{ $jobcard->company->formatCurrencyZar($item->unit_price) }}</td>
                                <td class="text-right">{{ $jobcard->company->formatCurrencyZar($item->total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="totals">
                <div class="totals-row">
                    <span>Subtotal:</span>
                    <span>{{ $jobcard->company->formatCurrencyZar($jobcard->subtotal) }}</span>
                </div>
                @if($jobcard->discount_amount > 0)
                    <div class="totals-row">
                        <span>Discount:</span>
                        <span>-{{ $jobcard->company->formatCurrencyZar($jobcard->discount_amount) }}</span>
                    </div>
                @endif
                <div class="totals-row">
                    <span>Tax ({{ $jobcard->tax_rate }}%):</span>
                    <span>{{ $jobcard->company->formatCurrencyZar($jobcard->tax_amount) }}</span>
                </div>
                <div class="totals-row total">
                    <span>Total:</span>
                    <span>{{ $jobcard->company->formatCurrencyZar($jobcard->total) }}</span>
                </div>
            </div>

            @if($jobcard->due_date && $jobcard->due_date < now() && $jobcard->status !== 'completed')
                <div class="due-date-notice">
                    <strong>Important:</strong> This jobcard is overdue. Please contact us for an update.
                </div>
            @endif

            <div class="next-steps">
                <h3>Next Steps</h3>
                <ul>
                    <li>Review the jobcard details above</li>
                    <li>Contact us if you have any questions about this work</li>
                    <li>We'll keep you updated on the progress</li>
                    <li>Keep this email for your records</li>
                </ul>
            </div>

            @if($jobcard->notes)
                <div class="customer-section">
                    <div class="section-title">Notes</div>
                    <div>{{ $jobcard->notes }}</div>
                </div>
            @endif

            @if($jobcard->terms_conditions)
                <div class="customer-section">
                    <div class="section-title">Terms & Conditions</div>
                    <div>{{ $jobcard->terms_conditions }}</div>
                </div>
            @endif
        </div>

        <div class="footer">
            <p><strong>{{ $jobcard->company->name }}</strong></p>
            <p>Thank you for your business!</p>
            <p>This jobcard was generated on {{ now()->format('M d, Y \a\t g:i A') }}</p>
        </div>
    </div>
</body>
</html>
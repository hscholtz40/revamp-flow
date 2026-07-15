@php
    $emailCompany = $company;
    $emailBrand = $emailCompany->getEmailBranding();
    $emailLogoSrc = $emailBrand['logo_path'] && isset($message)
        ? $message->embed($emailBrand['logo_path'])
        : $emailBrand['logo_url'];
@endphp
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
            color: {{ $emailBrand['text'] }};
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
            background: linear-gradient(135deg, {{ $emailBrand['primary'] }} 0%, {{ $emailBrand['secondary'] }} 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header-logo {
            display: block;
            max-height: 72px;
            max-width: 180px;
            margin: 0 auto 16px auto;
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
            color: {{ $emailBrand['heading'] }};
            margin-bottom: 8px;
        }
        .jobcard-info {
            text-align: right;
            flex: 1;
        }
        .jobcard-number {
            font-size: 24px;
            font-weight: bold;
            color: {{ $emailBrand['primary'] }};
            margin-bottom: 5px;
        }
        .jobcard-title {
            font-size: 18px;
            color: {{ $emailBrand['muted'] }};
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
        .status-in-progress { background-color: #f3f4f6; color: #4b5563; }
        .status-completed { background-color: #dcfce7; color: #166534; }
        .status-on-hold { background-color: #fef3c7; color: #92400e; }
        .status-cancelled { background-color: #fecaca; color: #991b1b; }
        .customer-section {
            margin-bottom: 30px;
        }
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: {{ $emailBrand['heading'] }};
            margin-bottom: 10px;
            border-bottom: 2px solid {{ $emailBrand['border'] }};
            padding-bottom: 5px;
        }
        .customer-info {
            background-color: #f8fafc;
            border-radius: 6px;
            padding: 15px;
        }
        .customer-name {
            font-weight: bold;
            color: {{ $emailBrand['heading'] }};
            margin-bottom: 5px;
        }
        .message {
            background-color: #fef3c7;
            border-left: 4px solid {{ $emailBrand['accent'] }};
            padding: 15px;
            margin-bottom: 30px;
            border-radius: 0 6px 6px 0;
        }
        .message strong {
            color: #92400e;
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
            border-top: 2px solid {{ $emailBrand['primary'] }};
            border-bottom: none;
            padding-top: 15px;
            margin-top: 10px;
            color: {{ $emailBrand['heading'] }};
        }
        .attachment-notice {
            background-color: {{ $emailBrand['surface'] }};
            border: 2px solid {{ $emailBrand['accent'] }};
            border-radius: 8px;
            padding: 20px;
            margin: 30px 0;
            text-align: center;
        }
        .attachment-notice h3 {
            margin: 0 0 10px 0;
            color: {{ $emailBrand['heading'] }};
            font-size: 18px;
        }
        .attachment-notice p {
            margin: 0;
            color: {{ $emailBrand['text'] }};
            font-size: 14px;
        }
        .footer {
            background-color: #f8fafc;
            padding: 20px 30px;
            text-align: center;
            color: {{ $emailBrand['muted'] }};
            font-size: 14px;
            border-top: 1px solid #e2e8f0;
        }
        .footer p {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            @if($emailLogoSrc)
                <img src="{{ $emailLogoSrc }}" alt="{{ $emailCompany->name }} logo" class="header-logo">
            @endif
            <h1>Jobcard</h1>
            <p>{{ $company->name ?? 'Company Name' }}</p>
        </div>

        <div class="content">
            <div class="jobcard-details">
                <div class="jobcard-header">
                    <div class="company-info">
                        <div class="company-name">{{ $company->name ?? 'Company Name' }}</div>
                        @if($company->email ?? false)
                            <div>{{ $company->email }}</div>
                        @endif
                        @if($company->phone ?? false)
                            <div>{{ $company->phone }}</div>
                        @endif
                    </div>
                    
                    <div class="jobcard-info">
                        <div class="jobcard-number">{{ $jobcard->job_number ?? 'N/A' }}</div>
                        <div class="jobcard-title">{{ $jobcard->title ?? 'Jobcard Title' }}</div>
                        <div>
                            <span class="status-badge status-{{ $jobcard->status ?? 'draft' }}">{{ ucfirst($jobcard->status ?? 'draft') }}</span>
                        </div>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; margin-top: 20px;">
                    @if($jobcard->start_date ?? false)
                        <div>
                            <strong>Start Date:</strong> {{ \Carbon\Carbon::parse($jobcard->start_date)->format('M d, Y') }}
                        </div>
                    @endif
                    @if($jobcard->due_date ?? false)
                        <div>
                            <strong>Due Date:</strong> {{ \Carbon\Carbon::parse($jobcard->due_date)->format('M d, Y') }}
                        </div>
                    @endif
                </div>
            </div>

            @if($customMessage ?? false)
                <div class="message">
                    <strong>Message:</strong><br>
                    {{ $customMessage }}
                </div>
            @endif

            <div class="customer-section">
                <div class="section-title">Customer</div>
                <div class="customer-info">
                    <div class="customer-name">{{ $jobcard->customer->name ?? 'Customer Name' }}</div>
                    @if($jobcard->customer->email ?? false)
                        <div>{{ $jobcard->customer->email }}</div>
                    @endif
                    @if($jobcard->customer->phone ?? false)
                        <div>{{ $jobcard->customer->phone }}</div>
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
                        @foreach($jobcard->lineItems ?? [] as $item)
                            <tr>
                                <td>{{ $item->description ?? 'Item Description' }}</td>
                                <td class="text-right">{{ $item->quantity ?? 0 }}</td>
                                <td class="text-right">{{ $company->formatCurrencyZar($item->unit_price ?? 0) }}</td>
                                <td class="text-right">{{ $company->formatCurrencyZar($item->total ?? 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="totals">
                <div class="totals-row">
                    <span>Subtotal:</span>
                    <span>{{ $company->formatCurrencyZar($jobcard->subtotal ?? 0) }}</span>
                </div>
                @if(($jobcard->discount_amount ?? 0) > 0)
                    <div class="totals-row">
                        <span>Discount:</span>
                        <span>-{{ $company->formatCurrencyZar($jobcard->discount_amount) }}</span>
                    </div>
                @endif
                <div class="totals-row">
                    <span>Tax ({{ $jobcard->tax_rate ?? 0 }}%):</span>
                    <span>{{ $company->formatCurrencyZar($jobcard->tax_amount ?? 0) }}</span>
                </div>
                <div class="totals-row total">
                    <span>Total:</span>
                    <span>{{ $company->formatCurrencyZar($jobcard->total ?? 0) }}</span>
                </div>
            </div>

            <div class="attachment-notice">
                <h3>📎 Jobcard PDF Attached</h3>
                <p>Please find the complete jobcard details in the attached PDF document.</p>
            </div>
        </div>

        <div class="footer">
            <p><strong>{{ $company->name ?? 'Company Name' }}</strong></p>
            <p>Thank you for your business!</p>
            <p>This jobcard was generated on {{ now()->format('M d, Y \a\t g:i A') }}</p>
        </div>
    </div>
</body>
</html>
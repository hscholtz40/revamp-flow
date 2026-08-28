@php
    $emailCompany = $purchaseOrder->company;
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
    <title>Purchase Order {{ $purchaseOrder->po_number }}</title>
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
        .po-details {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .po-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid {{ $emailBrand['border'] }};
        }
        .po-number {
            font-size: 24px;
            font-weight: bold;
            color: {{ $emailBrand['primary'] }};
        }
        .po-date {
            color: {{ $emailBrand['muted'] }};
            font-size: 14px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 8px 0;
        }
        .detail-label {
            font-weight: 600;
            color: {{ $emailBrand['heading'] }};
        }
        .detail-value {
            color: {{ $emailBrand['text'] }};
        }
        .message-box {
            background-color: {{ $emailBrand['surface'] }};
            border-left: 4px solid {{ $emailBrand['accent'] }};
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .footer {
            background-color: #f8fafc;
            padding: 20px;
            text-align: center;
            color: {{ $emailBrand['muted'] }};
            font-size: 12px;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: {{ $emailBrand['primary'] }};
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 20px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            @if($emailLogoSrc)
                <img src="{{ $emailLogoSrc }}" alt="{{ $emailCompany->name }} logo" class="header-logo">
            @endif
            <h1>Purchase Order</h1>
            <p>{{ $purchaseOrder->company->name }}</p>
        </div>
        
        <div class="content">
            <div class="po-details">
                <div class="po-header">
                    <div>
                        <div class="po-number">PO #{{ $purchaseOrder->po_number }}</div>
                        <div class="po-date">Order Date: {{ \Carbon\Carbon::parse($purchaseOrder->order_date)->format('F d, Y') }}</div>
                    </div>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Supplier:</span>
                    <span class="detail-value">{{ $purchaseOrder->supplier->name }}</span>
                </div>
                
                @if($purchaseOrder->expected_delivery_date)
                <div class="detail-row">
                    <span class="detail-label">Expected Delivery:</span>
                    <span class="detail-value">{{ \Carbon\Carbon::parse($purchaseOrder->expected_delivery_date)->format('F d, Y') }}</span>
                </div>
                @endif
                
                <div class="detail-row">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value" style="text-transform: capitalize;">{{ $purchaseOrder->status }}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Total Amount:</span>
                    <span class="detail-value" style="font-weight: bold; font-size: 18px; color: {{ $emailBrand['primary'] }};">
                        {{ $purchaseOrder->company->formatCurrencyZar($purchaseOrder->total) }}
                    </span>
                </div>
            </div>
            
            @if($customMessage)
            <div class="message-box">
                <strong>Message:</strong><br>
                @include('emails.partials.multiline-text', ['text' => $customMessage])
            </div>
            @endif
            
            <p>Please find the attached purchase order PDF for your records.</p>
            
            <p>If you have any questions or need to make changes to this purchase order, please contact us at your earliest convenience.</p>
            
            <p>Thank you for your business!</p>
        </div>
        
        <div class="footer">
            <p>This is an automated email from {{ $purchaseOrder->company->name }}</p>
            <p>Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>


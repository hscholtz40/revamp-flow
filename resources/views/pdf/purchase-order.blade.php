<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Purchase Order #{{ $purchaseOrder->po_number ?? 'N/A' }}</title>
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
            max-width: 260px;
            max-height: 140px;
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
        
        .supplier-section {
            display: table;
            width: 100%;
            margin-bottom: 20px;
            border: 1px solid #000;
            padding: 15px;
        }
        
        .supplier-left {
            display: table-cell;
            width: 60%;
            vertical-align: top;
        }
        
        .supplier-right {
            display: table-cell;
            width: 40%;
            vertical-align: top;
            text-align: left;
        }
        
        .supplier-info h4 {
            margin: 0 0 8px 0;
            font-size: 12px;
            font-weight: bold;
            color: #000;
        }
        
        .supplier-info p {
            margin: 2px 0;
            font-size: 11px;
        }
        
        .po-info {
            text-align: left;
            font-size: 11px;
        }
        
        .po-info h4 {
            margin: 0 0 8px 0;
            font-size: 12px;
            font-weight: bold;
            color: #000;
        }
        
        .po-info p {
            margin: 2px 0;
        }
        
        .line-items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            border: 1px solid #000;
        }
        
        .line-items-table th {
            background-color: #f0f0f0;
            padding: 6px;
            text-align: left;
            border-bottom: 2px solid #000;
            font-size: 11px;
            font-weight: bold;
        }
        
        .line-items-table td {
            padding: 6px;
            border-bottom: 1px solid #ccc;
            font-size: 11px;
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
        <div class="document-title">Purchase Order</div>
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
            </div>
        </div>
        
        <div class="company-right">
            <div class="po-info">
                <h4>Purchase Order Details</h4>
                <p><strong>PO Number:</strong> {{ $purchaseOrder->po_number ?? 'N/A' }}</p>
                <p><strong>Order Date:</strong> {{ $purchaseOrder->order_date ? \Carbon\Carbon::parse($purchaseOrder->order_date)->format('Y/m/d') : date('Y/m/d') }}</p>
                @if($purchaseOrder->expected_delivery_date)
                    <p><strong>Expected Delivery:</strong> {{ \Carbon\Carbon::parse($purchaseOrder->expected_delivery_date)->format('Y/m/d') }}</p>
                @endif
                <p><strong>Status:</strong> {{ ucfirst($purchaseOrder->status ?? 'Draft') }}</p>
            </div>
        </div>
    </div>
    
    <div class="supplier-section">
        <div class="supplier-left">
            <div class="supplier-info">
                <h4>Supplier:</h4>
                <p><strong>{{ $purchaseOrder->supplier->name ?? 'SUPPLIER NAME' }}</strong></p>
                @if($purchaseOrder->supplier->address)
                    <p>{{ $purchaseOrder->supplier->address }}</p>
                @endif
                @if($purchaseOrder->supplier->city)
                    <p>{{ $purchaseOrder->supplier->city }}</p>
                @endif
                @if($purchaseOrder->supplier->postal_code)
                    <p>{{ $purchaseOrder->supplier->postal_code }}</p>
                @endif
                @if($purchaseOrder->supplier->phone)
                    <p>Phone: {{ $purchaseOrder->supplier->phone }}</p>
                @endif
                @if($purchaseOrder->supplier->email)
                    <p>Email: {{ $purchaseOrder->supplier->email }}</p>
                @endif
            </div>
        </div>
        
        <div class="supplier-right">
            <div class="po-info">
                <h4>Delivery Information</h4>
                @if($purchaseOrder->expected_delivery_date)
                    <p><strong>Expected:</strong> {{ \Carbon\Carbon::parse($purchaseOrder->expected_delivery_date)->format('Y/m/d') }}</p>
                @endif
                @if($purchaseOrder->received_date)
                    <p><strong>Received:</strong> {{ \Carbon\Carbon::parse($purchaseOrder->received_date)->format('Y/m/d') }}</p>
                @endif
            </div>
        </div>
    </div>
    
    <table class="line-items-table">
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-right">Quantity</th>
                <th class="text-right">Unit Cost</th>
                <th class="text-right">Tax</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($purchaseOrder->items as $item)
                <tr>
                    <td>
                        <strong>{{ $item->product->name ?? $item->description ?? 'Item' }}</strong>
                        @if($item->product && $item->product->sku)
                            <br><small>SKU: {{ $item->product->sku }}</small>
                        @endif
                        @if($item->description && $item->product)
                            <br><small>{{ $item->description }}</small>
                        @endif
                    </td>
                    <td class="text-right">{{ number_format($item->quantity, 0) }}</td>
                    <td class="text-right">R {{ number_format($item->unit_cost, 2, '.', ',') }}</td>
                    <td class="text-right">
                        @if($item->taxRate)
                            R {{ number_format($item->tax_amount ?? 0, 2, '.', ',') }}
                            <div style="font-size: 9px; color: #666;">{{ $item->taxRate->name }} ({{ $item->taxRate->rate }}%)</div>
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-right">R {{ number_format($item->total, 2, '.', ',') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    
    <div class="totals-section">
        <div class="total-row">
            <span>Subtotal:</span>
            <span>R{{ number_format($purchaseOrder->subtotal ?? 0, 2) }}</span>
        </div>
        @if(($purchaseOrder->tax_amount ?? 0) > 0)
        <div class="total-row">
            <span>Tax:</span>
            <span>R{{ number_format($purchaseOrder->tax_amount, 2) }}</span>
        </div>
        @endif
        <div class="total-row final">
            <span>Total:</span>
            <span>R{{ number_format($purchaseOrder->total ?? 0, 2) }}</span>
        </div>
    </div>
    
    @if($purchaseOrder->notes || $purchaseOrder->terms)
    <div class="terms-section">
        @if($purchaseOrder->notes)
        <h4>Notes:</h4>
        <p>{{ $purchaseOrder->notes }}</p>
        @endif
        
        @if($purchaseOrder->terms)
        <h4>Terms & Conditions:</h4>
        <p>{{ $purchaseOrder->terms }}</p>
        @endif
    </div>
    @endif
    
    <div class="footer">
        <div class="footer-left">
            Generated on {{ date('Y/m/d H:i') }}
        </div>
        <div class="footer-right">
            {{ $company->name ?? 'Company' }}
        </div>
    </div>
</body>
</html>


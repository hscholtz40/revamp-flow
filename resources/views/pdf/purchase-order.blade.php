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
            max-width: 360px;
            max-height: 240px;
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
            margin-bottom: 20px;
        }
        
        .line-items-table th,
        .line-items-table td {
            padding: 3px;
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
        <div class="document-title">Purchase Order</div>
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
                @if($purchaseOrder->supplier->email && stripos($purchaseOrder->supplier->email, 'sage-migration.local') === false)
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
                <th>Item Description</th>
                <th class="text-right">QTY</th>
                <th class="text-right">Price (Ex)</th>
                <th class="text-right">Tax</th>
                <th class="text-right">Total (Incl)</th>
            </tr>
        </thead>
        <tbody>
            @php
                $lineGroups = $purchaseOrder->lineGroups ?? collect();
                $items = $purchaseOrder->items ?? collect();
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
                        <tr class="group-header"><td colspan="5" style="font-weight: bold; background: #f5f5f5; padding: 4px 2px;">{{ $group->name }}</td></tr>
                    @endif
                    @foreach($group->items->sortBy('id') as $item)
                <tr>
                    <td>{{ $item->description ?? $item->product->name ?? 'Item Description' }}{{ ($item->product && ($item->product->sku ?? $item->product->barcode)) ? ' (' . ($item->product->sku ?? $item->product->barcode) . ')' : '' }}</td>
                    <td class="text-right">{{ number_format($item->quantity ?? 0, 2) }}</td>
                    <td class="text-right">R{{ number_format($item->unit_cost ?? 0, 2) }}</td>
                    <td class="text-right">
                        @if($item->taxRate)
                            R{{ number_format($item->tax_amount ?? 0, 2) }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-right">R{{ number_format($item->total ?? 0, 2) }}</td>
                </tr>
                    @endforeach
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


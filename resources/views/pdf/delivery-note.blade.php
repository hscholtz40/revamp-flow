<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Delivery Note #{{ $deliveryNote->delivery_note_number ?? 'N/A' }}</title>
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
            text-transform: uppercase;
        }
        .company-logo {
            max-width: 360px;
            max-height: 240px;
            margin-bottom: 10px;
        }
        .info-section {
            display: table;
            width: 100%;
            margin-bottom: 20px;
            border: 1px solid #000;
            padding: 15px;
        }
        .info-left, .info-right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .info-right {
            text-align: left;
        }
        .line-items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .line-items-table th,
        .line-items-table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        .line-items-table th {
            background: #f0f0f0;
            font-weight: bold;
        }
        .line-items-table .text-right {
            text-align: right;
        }
        .group-header td {
            background: #fafafa;
            font-weight: bold;
        }
        .notes-section {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #000;
        }
    </style>
</head>
<body>
    <div class="header-row">
        <div class="header-logo">
            @if($company->getLogoPathForPdf())
                <img src="{{ $company->getLogoPathForPdf() }}" alt="Company Logo" class="company-logo">
            @endif
        </div>
        <div class="document-title">Delivery Note</div>
    </div>

    <div class="info-section">
        <div class="info-left">
            <strong>{{ $company->name ?? 'COMPANY NAME' }}</strong><br>
            @if($company->address){{ $company->address }}<br>@endif
            @if($company->phone)Phone: {{ $company->phone }}<br>@endif
            @if($company->email && stripos($company->email, 'sage-migration.local') === false)Email: {{ $company->email }}@endif
        </div>
        <div class="info-right">
            <p><strong>Delivery Note #:</strong> {{ $deliveryNote->delivery_note_number }}</p>
            <p><strong>Delivery Date:</strong> {{ $deliveryNote->delivery_date ? $company->formatLocalizedDate($deliveryNote->delivery_date) : '—' }}</p>
            <p><strong>Jobcard #:</strong> {{ $deliveryNote->jobcard?->job_number ?? '—' }}</p>
            <p><strong>Status:</strong> {{ ucfirst($deliveryNote->status ?? 'draft') }}</p>
        </div>
    </div>

    <div class="info-section">
        <div class="info-left">
            <strong>Deliver To</strong><br>
            @if($deliveryNote->customer)
                {{ $deliveryNote->customer->name }}<br>
            @endif
            @if($deliveryNote->delivery_address)
                {{ $deliveryNote->delivery_address }}
            @elseif($deliveryNote->customer?->address)
                {{ $deliveryNote->customer->address }}
            @endif
        </div>
        <div class="info-right">
            @if($deliveryNote->contact)
                <p><strong>Contact:</strong> {{ $deliveryNote->contact->name }}</p>
            @endif
        </div>
    </div>

    <table class="line-items-table">
        <thead>
            <tr>
                <th style="width: 50%">Description</th>
                <th style="width: 25%">Product / SKU</th>
                <th style="width: 25%" class="text-right">Quantity</th>
            </tr>
        </thead>
        <tbody>
            @php
                $groups = $deliveryNote->lineGroups->sortBy('sort_order');
                $items = $deliveryNote->lineItems->sortBy('sort_order');
                $groupedItems = $groups->isNotEmpty()
                    ? $groups->map(fn ($group) => ['group' => $group, 'items' => $items->where('line_group_id', $group->id)])
                    : collect([['group' => null, 'items' => $items]]);
            @endphp
            @foreach($groupedItems as $groupData)
                @if($groupData['group'] && $groups->count() > 1)
                    <tr class="group-header">
                        <td colspan="3">{{ $groupData['group']->name }}</td>
                    </tr>
                @endif
                @foreach($groupData['items'] as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td>
                            @if($item->product)
                                {{ $item->product->name }}@if($item->product->sku) ({{ $item->product->sku }})@endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-right">{{ $item->quantity }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    @if($deliveryNote->notes)
        <div class="notes-section">
            <strong>Notes</strong>
            <p>{{ $deliveryNote->notes }}</p>
        </div>
    @endif
</body>
</html>

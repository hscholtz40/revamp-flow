<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Client Ageing Statement</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        h1 { margin: 0 0 8px; font-size: 20px; }
        h2 { margin: 20px 0 8px; font-size: 14px; }
        .meta { margin-bottom: 12px; color: #4b5563; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 6px; text-align: left; }
        th { background: #f3f4f6; }
        .num { text-align: right; }
    </style>
</head>
<body>
    <h1>Client Ageing Statement</h1>
    <div class="meta">
        <div><strong>Customer:</strong> {{ $customer->name }}</div>
        <div><strong>Email:</strong> {{ $customer->email }}</div>
        <div><strong>Company:</strong> {{ $company->name }}</div>
        <div><strong>Generated:</strong> {{ $generatedAt->timezone('Africa/Johannesburg')->format('Y-m-d H:i') }}</div>
    </div>

    <h2>Ageing Summary</h2>
    <table>
        <thead>
            <tr>
                <th class="num">Current</th>
                <th class="num">30 Days</th>
                <th class="num">60 Days</th>
                <th class="num">90+ Days</th>
                <th class="num">Total Balance</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="num">{{ number_format((float) $totals['current'], 2) }}</td>
                <td class="num">{{ number_format((float) $totals['days_30'], 2) }}</td>
                <td class="num">{{ number_format((float) $totals['days_60'], 2) }}</td>
                <td class="num">{{ number_format((float) $totals['days_90_plus'], 2) }}</td>
                <td class="num"><strong>{{ number_format((float) $totals['total_balance'], 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <h2>Open Invoices (Unpaid)</h2>
    <table>
        <thead>
            <tr>
                <th>Invoice</th>
                <th>Invoice Date</th>
                <th>Due Date</th>
                <th>Status</th>
                <th class="num">Invoice Total</th>
                <th class="num">Payments</th>
                <th class="num">Balance</th>
                <th>Bucket</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['invoice_number'] }}</td>
                    <td>{{ optional($row['invoice_date'])->format('Y-m-d') }}</td>
                    <td>{{ optional($row['due_date'])->format('Y-m-d') }}</td>
                    <td>{{ $row['status'] }}</td>
                    <td class="num">{{ number_format((float) $row['total'], 2) }}</td>
                    <td class="num">{{ number_format((float) $row['payments'], 2) }}</td>
                    <td class="num">{{ number_format((float) $row['balance'], 2) }}</td>
                    <td>
                        @if ($row['bucket'] === 'current')
                            Current
                        @elseif ($row['bucket'] === 'days_30')
                            30 Days
                        @elseif ($row['bucket'] === 'days_60')
                            60 Days
                        @else
                            90+ Days
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8">No open invoices found for this company.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Totals</h2>
    <table>
        <thead>
            <tr>
                <th class="num">Invoice Total</th>
                <th class="num">Payments</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="num">{{ number_format((float) $totals['invoice_total'], 2) }}</td>
                <td class="num">{{ number_format((float) $totals['payments_total'], 2) }}</td>
                <td class="num"><strong>{{ number_format((float) $totals['total_balance'], 2) }}</strong></td>
            </tr>
        </tbody>
    </table>
</body>
</html>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Client Ageing Statement</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        .header-row { width: 100%; margin-bottom: 16px; }
        .header-logo { float: left; width: 48%; vertical-align: middle; }
        .header-title { float: right; width: 48%; text-align: right; vertical-align: middle; }
        .clearfix::after { content: ""; display: table; clear: both; }
        .company-logo { max-width: 220px; max-height: 72px; margin-bottom: 6px; }
        h1 { margin: 0 0 8px; font-size: 20px; }
        h2 { margin: 20px 0 8px; font-size: 14px; }
        .meta { margin-bottom: 12px; color: #4b5563; clear: both; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 6px; text-align: left; }
        th { background: #f3f4f6; }
        .num { text-align: right; }
    </style>
</head>
<body>
    <div class="header-row clearfix">
        <div class="header-logo">
            @if($company->getLogoPathForPdf())
                <img src="{{ $company->getLogoPathForPdf() }}" alt="Company Logo" class="company-logo">
            @endif
        </div>
        <div class="header-title">
            <h1>Client Ageing Statement</h1>
        </div>
    </div>
    <div class="meta">
        <div><strong>Customer:</strong> {{ $customer->name }}</div>
        <div><strong>Email:</strong> {{ $customer->email }}</div>
        <div><strong>Company:</strong> {{ $company->name }}</div>
        <div><strong>Generated:</strong> {{ $company->formatLocalizedDateTime($generatedAt) }}</div>
    </div>

    <h2>Ageing Summary (open invoices)</h2>
    <p class="meta" style="margin-top: 0;">Buckets reflect unpaid invoice balances only.</p>
    <table>
        <thead>
            <tr>
                <th class="num">Current</th>
                <th class="num">30 Days</th>
                <th class="num">60 Days</th>
                <th class="num">90+ Days</th>
                <th class="num">Invoice balance</th>
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

    <h2>Overall position</h2>
    <table>
        <tbody>
            <tr>
                <th style="text-align: left;">Outstanding on open invoices</th>
                <td class="num">{{ number_format((float) $totals['total_balance'], 2) }}</td>
            </tr>
            <tr>
                <th style="text-align: left;">Unapplied credit notes</th>
                <td class="num">({{ number_format((float) ($totals['unapplied_credit_total'] ?? 0), 2) }})</td>
            </tr>
            <tr>
                <th style="text-align: left;"><strong>Net amount due</strong></th>
                <td class="num"><strong>{{ number_format((float) ($totals['net_balance'] ?? $totals['total_balance']), 2) }}</strong></td>
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
                    <td>{{ $company->formatLocalizedDate($row['invoice_date'] ?? null) }}</td>
                    <td>{{ $company->formatLocalizedDate($row['due_date'] ?? null) }}</td>
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

    <h2>Unapplied credit notes</h2>
    <p class="meta" style="margin-top: 0;">Credit not yet allocated to invoices (or remaining after allocations).</p>
    <table>
        <thead>
            <tr>
                <th>Credit note</th>
                <th>Date</th>
                <th>Status</th>
                <th class="num">Note total</th>
                <th class="num">Remaining credit</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($creditNoteRows as $cn)
                <tr>
                    <td>{{ $cn['credit_note_number'] }}</td>
                    <td>{{ $company->formatLocalizedDate($cn['credit_note_date'] ?? null) }}</td>
                    <td>{{ $cn['status'] }}</td>
                    <td class="num">{{ number_format((float) $cn['total'], 2) }}</td>
                    <td class="num">{{ number_format((float) $cn['remaining_credit'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No unapplied credit notes for this company.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Listed document totals</h2>
    <table>
        <thead>
            <tr>
                <th class="num">Open invoice gross</th>
                <th class="num">Payments on listed invoices</th>
                <th class="num">Outstanding on listed invoices</th>
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

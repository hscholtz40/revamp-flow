<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quote Response</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8fafc;
            color: #1f2937;
            margin: 0;
            padding: 24px;
        }
        .card {
            max-width: 640px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 24px;
        }
        .status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-accepted { background: #dcfce7; color: #166534; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        .muted { color: #6b7280; font-size: 14px; }
    </style>
</head>
<body>
<div class="card">
    <h1 style="margin-top: 0;">Quote Response Recorded</h1>
    <p>
        Quote <strong>{{ $quote->quote_number }}</strong> for
        <strong>{{ $quote->customer->name ?? 'Customer' }}</strong>
        has been marked as
        <span class="status status-{{ $decision }}">{{ $decision }}</span>.
    </p>

    @if($didChange)
        <p class="muted">
            Thank you, your response has been saved and the quote creator has been notified.
        </p>
    @else
        <p class="muted">
            No change was needed because this quote is already marked as {{ $previousStatus }}.
        </p>
    @endif

    <p class="muted">You can close this page.</p>
</div>
</body>
</html>

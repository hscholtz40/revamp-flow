<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quote Response Notification</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
    <h2 style="margin-bottom: 8px;">Quote {{ $quote->quote_number }} was {{ strtoupper($decision) }}</h2>
    <p style="margin-top: 0;">
        Hi {{ $creator->name ?? 'there' }}, your customer has responded to quote
        <strong>{{ $quote->quote_number }}</strong>.
    </p>

    <p>
        <strong>Customer:</strong> {{ $quote->customer->name ?? 'N/A' }}<br>
        <strong>Quote Title:</strong> {{ $quote->title }}<br>
        <strong>Previous Status:</strong> {{ strtoupper($previousStatus) }}<br>
        <strong>New Status:</strong> {{ strtoupper($decision) }}
    </p>

    <p>
        You can review this quote in JobCardOnline.
    </p>
</body>
</html>

@php
    $emailCompany = $quote->company;
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
    <title>Quote Response Notification</title>
</head>
<body style="font-family: Arial, sans-serif; color: {{ $emailBrand['text'] }}; line-height: 1.6; background-color: #f8fafc; margin: 0; padding: 24px;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden;">
        <div style="background: linear-gradient(135deg, {{ $emailBrand['primary'] }} 0%, {{ $emailBrand['secondary'] }} 100%); color: #ffffff; padding: 28px; text-align: center;">
            @if($emailLogoSrc)
                <img src="{{ $emailLogoSrc }}" alt="{{ $emailCompany->name }} logo" style="display: block; max-height: 72px; max-width: 180px; margin: 0 auto 16px auto;">
            @endif
            <h2 style="margin: 0;">Quote {{ $quote->quote_number }} was {{ strtoupper($decision) }}</h2>
        </div>

        <div style="padding: 28px;">
            <p style="margin-top: 0;">
                Hi {{ $creator->name ?? 'there' }}, your customer has responded to quote
                <strong>{{ $quote->quote_number }}</strong>.
            </p>

            <p style="background-color: {{ $emailBrand['surface'] }}; border-left: 4px solid {{ $emailBrand['accent'] }}; padding: 16px;">
                <strong>Customer:</strong> {{ $quote->customer->name ?? 'N/A' }}<br>
                <strong>Quote Title:</strong> {{ $quote->title }}<br>
                <strong>Previous Status:</strong> {{ strtoupper($previousStatus) }}<br>
                <strong>New Status:</strong> {{ strtoupper($decision) }}
            </p>

            <p>
                You can review this quote in JobCardOnline.
            </p>
        </div>
    </div>
</body>
</html>

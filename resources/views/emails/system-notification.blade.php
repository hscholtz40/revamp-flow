@php
    $emailBrand = $company->getEmailBranding();
    $emailLogoSrc = $emailBrand['logo_path'] && isset($message)
        ? $message->embed($emailBrand['logo_path'])
        : $emailBrand['logo_url'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $emailTitle ?? ($company->name ?? config('app.name')) }}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: {{ $emailBrand['text'] }}; background-color: #f8fafc; margin: 0; padding: 24px;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid {{ $emailBrand['border'] }};">
        <div style="background: linear-gradient(135deg, {{ $emailBrand['primary'] }} 0%, {{ $emailBrand['secondary'] }} 100%); color: #ffffff; padding: 28px; text-align: center;">
            @if($emailLogoSrc)
                <img src="{{ $emailLogoSrc }}" alt="{{ $company->name }} logo" style="display: block; max-height: 72px; max-width: 180px; margin: 0 auto 16px auto;">
            @endif
            <h2 style="margin: 0; font-size: 20px; font-weight: 600;">{{ $emailTitle ?? $company->name }}</h2>
        </div>

        <div style="padding: 28px; color: {{ $emailBrand['text'] }};">
            {!! $bodyHtml !!}
        </div>

        <div style="padding: 16px 28px 24px; border-top: 1px solid {{ $emailBrand['border'] }}; color: {{ $emailBrand['muted'] }}; font-size: 12px; text-align: center;">
            {{ $company->name }}
            @if(!empty($company->email))
                · {{ $company->email }}
            @endif
        </div>
    </div>
</body>
</html>

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
    <title>New Query Notification</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.5; color: {{ $emailBrand['text'] }}; background-color: #f8fafc; margin: 0; padding: 24px;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden;">
        <div style="background: linear-gradient(135deg, {{ $emailBrand['primary'] }} 0%, {{ $emailBrand['secondary'] }} 100%); color: #ffffff; padding: 28px; text-align: center;">
            @if($emailLogoSrc)
                <img src="{{ $emailLogoSrc }}" alt="{{ $company->name }} logo" style="display: block; max-height: 72px; max-width: 180px; margin: 0 auto 16px auto;">
            @endif
            <h2 style="margin: 0;">
                New {{ $query->kind === 'contractor' ? 'Contractor Query' : 'Enquiry' }} Submitted
            </h2>
        </div>

        <div style="padding: 28px;">
            <p style="margin: 0 0 16px;">
                A new query has been submitted for <strong>{{ $company->name }}</strong>.
            </p>

            <table cellpadding="6" cellspacing="0" border="0" style="border-collapse: collapse; width: 100%; background-color: {{ $emailBrand['surface'] }}; border-left: 4px solid {{ $emailBrand['accent'] }};">
                <tr>
                    <td><strong>Query ID:</strong></td>
                    <td>#{{ $query->id }}</td>
                </tr>
                <tr>
                    <td><strong>Type:</strong></td>
                    <td>{{ ucfirst($query->kind ?? 'enquiry') }}</td>
                </tr>
                <tr>
                    <td><strong>Name:</strong></td>
                    <td>{{ trim(($query->name ?? '').' '.($query->surname ?? '')) }}</td>
                </tr>
                <tr>
                    <td><strong>Email:</strong></td>
                    <td>{{ $query->email ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td><strong>Cell:</strong></td>
                    <td>{{ $query->cell ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td><strong>Description:</strong></td>
                    <td>{{ $query->description ?? '' }}</td>
                </tr>
                @if(($query->kind ?? null) === 'contractor')
                    <tr>
                        <td><strong>Company Name:</strong></td>
                        <td>{{ $query->company_name ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td><strong>Company Email:</strong></td>
                        <td>{{ $query->company_email ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td><strong>Company Contact:</strong></td>
                        <td>{{ $query->company_contact_number ?? 'N/A' }}</td>
                    </tr>
                @endif
            </table>

            <p style="margin-top: 20px;">
                Review this query in JobCardOnline: <a href="{{ url('/queries/'.$query->id) }}" style="color: {{ $emailBrand['primary'] }};">{{ url('/queries/'.$query->id) }}</a>
            </p>
        </div>
    </div>
</body>
</html>

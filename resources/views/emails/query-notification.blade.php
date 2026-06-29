<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Query Notification</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.5; color: #111827;">
    <h2 style="margin: 0 0 12px;">
        New {{ $query->kind === 'contractor' ? 'Contractor Query' : 'Enquiry' }} Submitted
    </h2>

    <p style="margin: 0 0 16px;">
        A new query has been submitted for <strong>{{ $company->name }}</strong>.
    </p>

    <table cellpadding="6" cellspacing="0" border="0" style="border-collapse: collapse;">
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
        Review this query in JobCardOnline: <a href="{{ url('/queries/'.$query->id) }}">{{ url('/queries/'.$query->id) }}</a>
    </p>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $company->name }} - Query Form</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f8fafc; color: #111827; }
        .container { max-width: 760px; margin: 24px auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 20px; }
        h1 { margin: 0 0 6px; font-size: 24px; }
        p.sub { margin: 0 0 20px; color: #6b7280; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .field { margin-bottom: 14px; }
        .field.full { grid-column: 1 / -1; }
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        input, textarea { width: 100%; box-sizing: border-box; border: 1px solid #d1d5db; border-radius: 8px; padding: 10px 12px; font-size: 14px; }
        textarea { min-height: 140px; resize: vertical; }
        .help { font-size: 12px; color: #6b7280; margin-top: 4px; }
        .btn { appearance: none; border: 0; background: #2563eb; color: #fff; padding: 10px 16px; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn:hover { background: #1d4ed8; }
        .success { margin-bottom: 14px; padding: 10px 12px; border-radius: 8px; background: #ecfdf5; border: 1px solid #10b981; color: #065f46; }
        .error { margin-bottom: 10px; padding: 10px 12px; border-radius: 8px; background: #fef2f2; border: 1px solid #ef4444; color: #991b1b; font-size: 14px; }
        @media (max-width: 720px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <h1>{{ $kind === 'contractor' ? 'Contractor Onboarding' : 'Contact '.$company->name }}</h1>
        <p class="sub">{{ $kind === 'contractor' ? 'Submit your contractor details for review.' : 'Submit your enquiry and our team will get back to you.' }}</p>

        @if ($submitted)
            <div class="success">Thank you for submitting your enquiry</div>
        @endif

        @if ($errors && $errors->any())
            <div class="error">
                <strong>Please fix the following:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (! $submitted)
        <form action="{{ route('queries.public.store', ['companyId' => $company->id, 'token' => $token]) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="kind" value="{{ $kind }}">
            <div class="grid">
                <div class="field">
                    <label for="name">Name</label>
                    <input id="name" name="name" type="text" required value="{{ old('name') }}" maxlength="255">
                </div>
                <div class="field">
                    <label for="surname">Surname</label>
                    <input id="surname" name="surname" type="text" required value="{{ old('surname') }}" maxlength="255">
                </div>
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" required value="{{ old('email') }}" maxlength="255">
                </div>
                <div class="field">
                    <label for="cell">Cell</label>
                    <input id="cell" name="cell" type="text" required value="{{ old('cell') }}" maxlength="50">
                </div>
                <div class="field full">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" required maxlength="5000">{{ old('description') }}</textarea>
                </div>
                @if($kind === 'contractor')
                    <div class="field full">
                        <label for="company_name">Company Name</label>
                        <input id="company_name" name="company_name" type="text" required value="{{ old('company_name') }}" maxlength="255">
                    </div>
                    <div class="field">
                        <label for="company_registration_no">Company Registration No</label>
                        <input id="company_registration_no" name="company_registration_no" type="text" value="{{ old('company_registration_no') }}" maxlength="255">
                    </div>
                    <div class="field">
                        <label for="company_email">Company Email</label>
                        <input id="company_email" name="company_email" type="email" value="{{ old('company_email') }}" maxlength="255">
                    </div>
                    <div class="field full">
                        <label for="company_address">Company Address</label>
                        <input id="company_address" name="company_address" type="text" value="{{ old('company_address') }}" maxlength="500">
                    </div>
                    <div class="field">
                        <label for="company_contact_number">Company Contact Number</label>
                        <input id="company_contact_number" name="company_contact_number" type="text" value="{{ old('company_contact_number') }}" maxlength="100">
                    </div>
                    <div class="field">
                        <label for="company_website">Company Website</label>
                        <input id="company_website" name="company_website" type="url" value="{{ old('company_website') }}" maxlength="255">
                    </div>
                @endif
                <div class="field full">
                    <label for="attachments">Attachments (optional)</label>
                    <input id="attachments" name="attachments[]" type="file" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.mp4,.mov,.avi,.webm">
                    <div class="help">Up to 10 files, max 50MB each. Images/videos only.</div>
                </div>
            </div>

            <div style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;">
                <label for="website">Website</label>
                <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>

            <button class="btn" type="submit">Submit Query</button>
        </form>
        @endif
    </div>
</body>
</html>

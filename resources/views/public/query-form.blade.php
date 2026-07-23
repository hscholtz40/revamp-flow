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
        .brand { display: flex; justify-content: center; margin-bottom: 18px; }
        .company-logo { max-width: 180px; max-height: 90px; object-fit: contain; }
        h1 { margin: 0 0 6px; font-size: 24px; }
        h2.section { margin: 8px 0 12px; font-size: 16px; }
        p.sub { margin: 0 0 20px; color: #6b7280; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .field { margin-bottom: 14px; }
        .field.full { grid-column: 1 / -1; }
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        input, textarea, select { width: 100%; box-sizing: border-box; border: 1px solid #d1d5db; border-radius: 8px; padding: 10px 12px; font-size: 14px; background: #fff; }
        textarea { min-height: 140px; resize: vertical; }
        .help { font-size: 12px; color: #6b7280; margin-top: 4px; }
        .btn { appearance: none; border: 0; background: #2563eb; color: #fff; padding: 10px 16px; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn:hover { background: #1d4ed8; }
        .success { margin-bottom: 14px; padding: 10px 12px; border-radius: 8px; background: #ecfdf5; border: 1px solid #10b981; color: #065f46; }
        .error { margin-bottom: 10px; padding: 10px 12px; border-radius: 8px; background: #fef2f2; border: 1px solid #ef4444; color: #991b1b; font-size: 14px; }
        .package-option { border: 1px solid #d1d5db; border-radius: 10px; padding: 14px; margin-bottom: 10px; background: #f9fafb; }
        .package-option.selected { border-color: #2563eb; background: #eff6ff; }
        .package-option label.title { display: flex; gap: 10px; align-items: flex-start; font-size: 14px; cursor: pointer; margin-bottom: 8px; }
        .package-option ul { margin: 0; padding-left: 28px; color: #374151; font-size: 13px; line-height: 1.45; }
        .docs-list { margin: 0 0 10px; padding-left: 18px; color: #374151; font-size: 13px; }
        @media (max-width: 720px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        @if ($company->logo)
            <div class="brand">
                <img src="{{ $company->logo }}" alt="{{ $company->name }} logo" class="company-logo">
            </div>
        @endif

        <h1>{{ $kind === 'contractor' ? 'Contractor Onboarding' : 'Contact '.$company->name }}</h1>
        <p class="sub">{{ $kind === 'contractor' ? 'Submit your contractor details for review.' : 'Submit your enquiry and our team will get back to you.' }}</p>

        @if ($submitted)
            <div class="success">Thank you for submitting your enquiry</div>
        @endif

        @if ($errors && $errors->any())
            <div class="error">
                <strong>Please complete mandatory fields:</strong>
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
                        <input id="company_registration_no" name="company_registration_no" type="text" required value="{{ old('company_registration_no') }}" maxlength="255">
                    </div>
                    <div class="field">
                        <label for="company_email">Company Email</label>
                        <input id="company_email" name="company_email" type="email" required value="{{ old('company_email') }}" maxlength="255">
                    </div>
                    <div class="field">
                        <label for="company_contact_number">Company Contact Number</label>
                        <input id="company_contact_number" name="company_contact_number" type="tel" required value="{{ old('company_contact_number') }}" maxlength="16" inputmode="numeric" pattern="0[1-9][0-9]{8}" title="Enter a 10-digit South African number, e.g. 0211234567" placeholder="0211234567" autocomplete="tel-national">
                        <div class="help">Enter a valid 10-digit South African number, e.g. 0211234567 or 0821234567 (+27 also accepted).</div>
                    </div>
                    <div class="field">
                        <label for="website_status">Website</label>
                        <select id="website_status" name="website_status" required>
                            <option value="">Select an option</option>
                            <option value="have_website" @selected(old('website_status') === 'have_website')>I have a website</option>
                            <option value="need_website" @selected(old('website_status') === 'need_website')>I need a website</option>
                        </select>
                    </div>
                    <div class="field" id="company_website_field" style="{{ old('website_status', '') === 'have_website' ? '' : 'display:none;' }}">
                        <label for="company_website">Company Website</label>
                        <input id="company_website" name="company_website" type="text" value="{{ old('company_website') }}" maxlength="255" placeholder="www.example.com" inputmode="url" autocomplete="url">
                        <div class="help">Enter your site as www.example.com — http:// is added automatically.</div>
                    </div>
                    <div class="field full">
                        <label for="company_address">Company Address</label>
                        <input id="company_address" name="company_address" type="text" required value="{{ old('company_address') }}" maxlength="500" autocomplete="street-address">
                        <div class="help">Start typing and select an address from Google Maps suggestions.</div>
                    </div>
                    <div class="field">
                        <label for="company_city">City</label>
                        <input id="company_city" name="company_city" type="text" required value="{{ old('company_city') }}" maxlength="100" autocomplete="address-level2">
                    </div>
                    <div class="field">
                        <label for="company_province">Province</label>
                        <input id="company_province" name="company_province" type="text" required value="{{ old('company_province') }}" maxlength="100" autocomplete="address-level1">
                    </div>

                    <div class="field full">
                        <h2 class="section">Required documents</h2>
                        <ul class="docs-list">
                            <li>Company CK</li>
                            <li>Proof of residence for company</li>
                        </ul>
                        <div class="field">
                            <label for="document_company_ck">Company CK *</label>
                            <input id="document_company_ck" name="document_company_ck" type="file" required accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">
                            <div class="help">PDF or image, max 50MB.</div>
                        </div>
                        <div class="field">
                            <label for="document_proof_of_residence">Proof of residence for company *</label>
                            <input id="document_proof_of_residence" name="document_proof_of_residence" type="file" required accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">
                            <div class="help">PDF or image, max 50MB.</div>
                        </div>
                    </div>

                    <div class="field full">
                        <h2 class="section">Select package *</h2>
                        @php $selectedPackage = old('selected_package'); @endphp
                        <div class="package-option {{ $selectedPackage === 'option_1' ? 'selected' : '' }}">
                            <label class="title">
                                <input type="radio" name="selected_package" value="option_1" required @checked($selectedPackage === 'option_1')>
                                <span><strong>Option 1</strong> — R 550 pm incl VAT · 60 day trial</span>
                            </label>
                            <ul>
                                <li>1 Main user — Full access</li>
                                <li>5 Sub users — Restricted access</li>
                                <li>Unlimited "limited users" use on tracking app</li>
                                <li>Access to Revamp© automated quote generator and enquiry form link</li>
                                <li>Access to new business via Revamp© user interface</li>
                                <li>8 Design previews included per month — R10 incl VAT per additional design preview out of bundle</li>
                            </ul>
                        </div>
                        <div class="package-option {{ $selectedPackage === 'option_2' ? 'selected' : '' }}">
                            <label class="title">
                                <input type="radio" name="selected_package" value="option_2" required @checked($selectedPackage === 'option_2')>
                                <span><strong>Option 2</strong> — R 850 pm incl VAT · 60 day trial</span>
                            </label>
                            <ul>
                                <li>2 Main users — Full access</li>
                                <li>10 Sub users — Restricted access</li>
                                <li>Unlimited "limited users" use on tracking app</li>
                                <li>Access to Revamp© automated quote generator and enquiry form link</li>
                                <li>Access to new business via Revamp© user interface</li>
                                <li>12 Design previews included per month — R10 incl VAT per additional design preview out of bundle</li>
                            </ul>
                        </div>
                        <div class="package-option {{ $selectedPackage === 'custom' ? 'selected' : '' }}">
                            <label class="title">
                                <input type="radio" name="selected_package" value="custom" required @checked($selectedPackage === 'custom')>
                                <span><strong>Custom package</strong> — Select to request Revamp© to contact you to discuss a custom package</span>
                            </label>
                        </div>
                    </div>
                @else
                    <div class="field full">
                        <label for="attachments">Attachments (optional)</label>
                        <input id="attachments" name="attachments[]" type="file" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.mp4,.mov,.avi,.webm">
                        <div class="help">Up to 10 files, max 50MB each. Images/videos only.</div>
                    </div>
                @endif
            </div>

            <div style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;">
                <label for="website">Website</label>
                <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>

            <button class="btn" type="submit">Submit Query</button>
        </form>
        @endif
    </div>

    @if($kind === 'contractor' && ! $submitted)
    <script>
        (function () {
            var statusEl = document.getElementById('website_status');
            var websiteField = document.getElementById('company_website_field');
            var websiteInput = document.getElementById('company_website');

            function syncWebsiteField() {
                var haveWebsite = statusEl && statusEl.value === 'have_website';
                if (websiteField) {
                    websiteField.style.display = haveWebsite ? '' : 'none';
                }
                if (websiteInput) {
                    websiteInput.required = haveWebsite;
                    if (!haveWebsite) {
                        websiteInput.value = '';
                    }
                }
            }

            function ensureWebsiteProtocol() {
                if (!websiteInput) {
                    return;
                }
                var value = (websiteInput.value || '').trim();
                if (!value) {
                    return;
                }
                if (!/^https?:\/\//i.test(value)) {
                    websiteInput.value = 'http://' + value.replace(/^\/+/, '');
                }
            }

            var contactInput = document.getElementById('company_contact_number');

            function normalizeSaPhone() {
                if (!contactInput) {
                    return;
                }
                var digits = (contactInput.value || '').replace(/\D+/g, '');
                if (digits.indexOf('27') === 0 && digits.length >= 11) {
                    digits = '0' + digits.slice(2);
                }
                contactInput.value = digits.slice(0, 10);
            }

            if (websiteInput) {
                websiteInput.addEventListener('blur', ensureWebsiteProtocol);
            }

            if (contactInput) {
                contactInput.addEventListener('blur', normalizeSaPhone);
                contactInput.addEventListener('input', normalizeSaPhone);
            }

            var form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function () {
                    ensureWebsiteProtocol();
                    normalizeSaPhone();
                });
            }

            if (statusEl) {
                statusEl.addEventListener('change', syncWebsiteField);
                syncWebsiteField();
            }

            document.querySelectorAll('input[name="selected_package"]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    document.querySelectorAll('.package-option').forEach(function (el) {
                        el.classList.remove('selected');
                    });
                    if (radio.checked) {
                        radio.closest('.package-option').classList.add('selected');
                    }
                });
            });

            var mapsKey = @json($googleMapsApiKey ?? '');
            if (!mapsKey) {
                return;
            }

            function componentValue(components, type, useShort) {
                for (var i = 0; i < components.length; i++) {
                    if (components[i].types.indexOf(type) !== -1) {
                        return (useShort ? components[i].short_name : components[i].long_name) || '';
                    }
                }
                return '';
            }

            function initPlaces() {
                var addressInput = document.getElementById('company_address');
                var cityInput = document.getElementById('company_city');
                var provinceInput = document.getElementById('company_province');
                if (!addressInput || !window.google || !google.maps || !google.maps.places) {
                    return;
                }

                var autocomplete = new google.maps.places.Autocomplete(addressInput, {
                    fields: ['formatted_address', 'address_components', 'name'],
                    types: ['address'],
                    componentRestrictions: { country: 'za' }
                });

                autocomplete.addListener('place_changed', function () {
                    var place = autocomplete.getPlace();
                    var components = place.address_components || [];
                    var streetNumber = componentValue(components, 'street_number');
                    var route = componentValue(components, 'route');
                    var street = [streetNumber, route].filter(Boolean).join(' ').trim();
                    addressInput.value = street || place.name || (place.formatted_address || '').split(',')[0] || place.formatted_address || '';
                    if (cityInput) {
                        cityInput.value =
                            componentValue(components, 'locality') ||
                            componentValue(components, 'postal_town') ||
                            componentValue(components, 'administrative_area_level_2') ||
                            componentValue(components, 'sublocality') ||
                            '';
                    }
                    if (provinceInput) {
                        provinceInput.value = componentValue(components, 'administrative_area_level_1') || '';
                    }
                });
            }

            var script = document.createElement('script');
            script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(mapsKey) + '&libraries=places';
            script.async = true;
            script.defer = true;
            script.onload = initPlaces;
            document.head.appendChild(script);
        })();
    </script>
    @endif
</body>
</html>

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
        input:not([type="radio"]):not([type="checkbox"]):not([type="file"]), textarea, select {
            width: 100%; box-sizing: border-box; border: 1px solid #d1d5db; border-radius: 8px; padding: 10px 12px; font-size: 14px; background: #fff;
        }
        input[type="file"] { width: 100%; box-sizing: border-box; font-size: 14px; }
        textarea { min-height: 140px; resize: vertical; }
        .help { font-size: 12px; color: #6b7280; margin-top: 4px; }
        .btn { appearance: none; border: 0; background: {{ $brandPrimary ?? '#2563eb' }}; color: #fff; padding: 10px 16px; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn:hover { filter: brightness(0.9); }
        .success { margin-bottom: 14px; padding: 10px 12px; border-radius: 8px; background: #ecfdf5; border: 1px solid #10b981; color: #065f46; }
        .error { margin-bottom: 10px; padding: 10px 12px; border-radius: 8px; background: #fef2f2; border: 1px solid #ef4444; color: #991b1b; font-size: 14px; }
        .is-hidden { display: none !important; }
        .package-option {
            position: relative;
            display: block;
            border: 2px solid #d1d5db;
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 10px;
            background: #fff;
            cursor: pointer;
            font-weight: 400;
            transition: border-color .15s ease, background .15s ease, box-shadow .15s ease;
        }
        .package-option:hover { border-color: {{ $brandPrimary ?? '#2563eb' }}; background: #f8fbff; }
        .package-option:has(input[type="radio"]:checked),
        .package-option.selected {
            border-color: {{ $brandPrimary ?? '#2563eb' }};
            background: #eff6ff;
            box-shadow: 0 0 0 1px {{ $brandPrimary ?? '#2563eb' }};
        }
        .package-option input[type="radio"] {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            margin: 0;
            opacity: 0;
            cursor: pointer;
            z-index: 1;
        }
        .package-option .package-title { display: block; font-size: 14px; font-weight: 600; margin-bottom: 8px; color: #111827; position: relative; z-index: 0; }
        .package-option .package-title span { font-weight: 400; color: #374151; }
        .package-option ul { margin: 0; padding-left: 18px; color: #374151; font-size: 13px; line-height: 1.45; position: relative; z-index: 0; }
        .package-option:has(input[type="radio"]:checked) .package-title,
        .package-option.selected .package-title { color: {{ $brandPrimary ?? '#2563eb' }}; }
        /* Google Places dropdown must sit above the form card */
        .pac-container { z-index: 10000 !important; }
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
                    <input id="email" name="email" type="email" required value="{{ old('email') }}" maxlength="255" autocomplete="email" placeholder="name@example.com" pattern="[^\s@]+@[^\s@]+\.[^\s@]+" title="Enter a valid email address, e.g. name@example.com">
                </div>
                <div class="field">
                    <label for="cell">Cell</label>
                    <input id="cell" name="cell" type="tel" required value="{{ old('cell') }}" maxlength="16" inputmode="numeric" pattern="0[6-8][0-9]{8}" title="Enter a valid 10-digit South African mobile number, e.g. 0821234567" placeholder="0821234567" autocomplete="tel-national">
                    <div class="help">10-digit South African mobile number, e.g. 0821234567 (+27 also accepted).</div>
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
                        <input id="company_email" name="company_email" type="email" required value="{{ old('company_email') }}" maxlength="255" autocomplete="email" placeholder="hello@example.com" pattern="[^\s@]+@[^\s@]+\.[^\s@]+" title="Enter a valid email address">
                    </div>
                    <div class="field">
                        <label for="company_contact_number">Company Contact Number</label>
                        <input id="company_contact_number" name="company_contact_number" type="tel" required value="{{ old('company_contact_number') }}" maxlength="16" inputmode="numeric" pattern="0[1-9][0-9]{8}" title="Enter a 10-digit South African number, e.g. 0211234567" placeholder="0211234567" autocomplete="tel-national">
                        <div class="help">Enter a valid 10-digit South African number, e.g. 0211234567 or 0821234567 (+27 also accepted).</div>
                    </div>
                    <div class="field full">
                        <label for="website_status">Website</label>
                        <select id="website_status" name="website_status" required>
                            <option value="">Select an option</option>
                            <option value="have_website" @selected(old('website_status') === 'have_website')>I have a website</option>
                            <option value="need_website" @selected(old('website_status') === 'need_website')>I need a website</option>
                        </select>
                    </div>
                    <div class="field full {{ old('website_status') === 'have_website' ? '' : 'is-hidden' }}" id="company_website_field">
                        <label for="company_website">Website address</label>
                        <input id="company_website" name="company_website" type="text" value="{{ old('company_website') ? preg_replace('#^https?://#i', '', old('company_website')) : '' }}" maxlength="255" placeholder="www.example.com" inputmode="url" autocomplete="url">
                        <div class="help">Enter the address without http:// or https:// — e.g. www.example.com</div>
                    </div>
                    <div class="field full">
                        <label for="company_address">Company Address</label>
                        <input id="company_address" name="company_address" type="text" required value="{{ old('company_address') }}" maxlength="500" autocomplete="street-address">
                        <div class="help" id="company_address_help">
                            @if(! empty($googleMapsApiKey))
                                Start typing and select an address from Google Maps suggestions.
                            @else
                                Type the street address manually. (Google Maps suggestions are unavailable — configure a Maps API key in Google Integration.)
                            @endif
                        </div>
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
                        @php
                            $selectedPackage = old('selected_package');
                            $packageCards = $contractorPackages ?? \App\Models\Query::defaultPackageCards();
                        @endphp
                        @foreach ($packageCards as $package)
                            <label class="package-option {{ $selectedPackage === $package['code'] ? 'selected' : '' }}">
                                <input
                                    type="radio"
                                    name="selected_package"
                                    value="{{ $package['code'] }}"
                                    data-product-id="{{ $package['id'] ?? '' }}"
                                    required
                                    @checked($selectedPackage === $package['code'])
                                >
                                <span class="package-title"><strong>{{ $package['name'] }}</strong> <span>— {{ $package['price_label'] }}</span></span>
                                @if (!empty($package['features']))
                                    <ul>
                                        @foreach ($package['features'] as $feature)
                                            <li>{{ $feature }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </label>
                        @endforeach
                        <input type="hidden" name="selected_product_id" id="selected_product_id" value="{{ old('selected_product_id') }}">
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

    @if(! $submitted)
    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
        (function () {
            var form = document.querySelector('form');
            var statusEl = document.getElementById('website_status');
            var websiteField = document.getElementById('company_website_field');
            var websiteInput = document.getElementById('company_website');
            var cellInput = document.getElementById('cell');
            var contactInput = document.getElementById('company_contact_number');

            function normalizeSaPhone(input) {
                if (!input) {
                    return;
                }
                var digits = (input.value || '').replace(/\D+/g, '');
                if (digits.indexOf('27') === 0 && digits.length >= 11) {
                    digits = '0' + digits.slice(2);
                }
                input.value = digits.slice(0, 10);
            }

            function syncWebsiteField() {
                if (!websiteField) {
                    return;
                }
                var haveWebsite = !!(statusEl && statusEl.value === 'have_website');
                websiteField.classList.toggle('is-hidden', !haveWebsite);
                if (websiteInput) {
                    websiteInput.required = haveWebsite;
                    if (!haveWebsite) {
                        websiteInput.value = '';
                    }
                }
            }

            function syncPackageHighlight() {
                document.querySelectorAll('.package-option').forEach(function (el) {
                    var input = el.querySelector('input[type="radio"]');
                    el.classList.toggle('selected', !!(input && input.checked));
                });
                var checked = document.querySelector('input[name="selected_package"]:checked');
                var productInput = document.getElementById('selected_product_id');
                if (productInput) {
                    productInput.value = checked && checked.getAttribute('data-product-id')
                        ? checked.getAttribute('data-product-id')
                        : '';
                }
            }

            function stripWebsiteProtocol() {
                if (!websiteInput) {
                    return;
                }
                var value = (websiteInput.value || '').trim();
                if (!value) {
                    return;
                }
                websiteInput.value = value.replace(/^https?:\/\//i, '').replace(/^\/+/, '');
            }

            function ensureWebsiteProtocolForSubmit() {
                if (!websiteInput || !websiteInput.required) {
                    return;
                }
                stripWebsiteProtocol();
                var value = (websiteInput.value || '').trim();
                if (!value) {
                    return;
                }
                websiteInput.value = 'http://' + value;
            }

            if (cellInput) {
                cellInput.addEventListener('blur', function () { normalizeSaPhone(cellInput); });
                cellInput.addEventListener('input', function () { normalizeSaPhone(cellInput); });
            }

            if (contactInput) {
                contactInput.addEventListener('blur', function () { normalizeSaPhone(contactInput); });
                contactInput.addEventListener('input', function () { normalizeSaPhone(contactInput); });
            }

            if (websiteInput) {
                websiteInput.addEventListener('blur', stripWebsiteProtocol);
                websiteInput.addEventListener('input', stripWebsiteProtocol);
            }

            if (form) {
                form.addEventListener('submit', function () {
                    normalizeSaPhone(cellInput);
                    normalizeSaPhone(contactInput);
                    ensureWebsiteProtocolForSubmit();
                });
            }

            if (statusEl) {
                statusEl.addEventListener('change', syncWebsiteField);
                statusEl.addEventListener('input', syncWebsiteField);
                syncWebsiteField();
                stripWebsiteProtocol();
            }

            document.querySelectorAll('input[name="selected_package"]').forEach(function (radio) {
                radio.addEventListener('change', syncPackageHighlight);
                radio.addEventListener('click', syncPackageHighlight);
            });
            syncPackageHighlight();

            var mapsKey = @json($googleMapsApiKey ?? '');
            var addressInput = document.getElementById('company_address');
            if (!mapsKey || !addressInput) {
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

            function waitForImportLibrary(timeoutMs) {
                var start = Date.now();
                return new Promise(function (resolve, reject) {
                    (function tick() {
                        if (window.google && google.maps && typeof google.maps.importLibrary === 'function') {
                            resolve(google.maps);
                            return;
                        }
                        if (Date.now() - start > timeoutMs) {
                            reject(new Error('Timed out waiting for Google Maps'));
                            return;
                        }
                        requestAnimationFrame(tick);
                    })();
                });
            }

            function loadGoogleMaps(apiKey) {
                if (window.google && google.maps && typeof google.maps.importLibrary === 'function') {
                    return Promise.resolve(google.maps);
                }

                return new Promise(function (resolve, reject) {
                    var existing = document.getElementById('google-maps-js-api');
                    if (existing) {
                        waitForImportLibrary(15000).then(resolve).catch(reject);
                        return;
                    }

                    var script = document.createElement('script');
                    script.id = 'google-maps-js-api';
                    script.async = true;
                    script.defer = true;
                    script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(apiKey) + '&loading=async';
                    script.onload = function () {
                        waitForImportLibrary(15000).then(resolve).catch(reject);
                    };
                    script.onerror = function () {
                        reject(new Error('Failed to load Google Maps script'));
                    };
                    document.head.appendChild(script);
                });
            }

            function initPlaces(placesLib) {
                var cityInput = document.getElementById('company_city');
                var provinceInput = document.getElementById('company_province');
                var helpEl = document.getElementById('company_address_help');

                var autocomplete = new placesLib.Autocomplete(addressInput, {
                    fields: ['formatted_address', 'address_components', 'geometry', 'name'],
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

                if (helpEl) {
                    helpEl.textContent = 'Start typing and select an address from Google Maps suggestions.';
                }
            }

            loadGoogleMaps(mapsKey)
                .then(function (maps) {
                    return maps.importLibrary('places');
                })
                .then(initPlaces)
                .catch(function () {
                    var helpEl = document.getElementById('company_address_help');
                    if (helpEl) {
                        helpEl.textContent = 'Address suggestions could not be loaded. Type the street address manually.';
                    }
                });
        })();
    </script>
    @endif
</body>
</html>

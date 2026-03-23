<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppSettingsController extends Controller
{
    /**
     * Display WhatsApp settings
     */
    public function index(): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $settings = WhatsAppSettings::getCurrent();

        return Inertia::render('administration/WhatsAppSettings', [
            'settings' => $settings
                ? array_merge($settings->toArray(), [
                    'has_api_key' => filled($settings->api_key),
                    'has_api_secret' => filled($settings->api_secret),
                ])
                : null,
            'currentCompany' => $currentCompany,
        ]);
    }

    /**
     * Store or update WhatsApp settings
     */
    public function store(Request $request): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        if (! $currentCompany) {
            return redirect()->back()->with('error', 'No company selected');
        }

        $isActive = $request->boolean('is_active');
        $provider = $request->input('provider', 'twilio');

        $settings = WhatsAppSettings::getForCompany($currentCompany->id);

        $metaTokenRequired = $isActive && $provider === 'meta' && ! ($settings && filled($settings->api_key));
        $twilioSecretRequired = $isActive && $provider === 'twilio' && ! ($settings && filled($settings->api_secret));
        $twilioSidRequired = $isActive && $provider === 'twilio' && ! ($settings && filled($settings->account_sid));

        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:twilio,meta'],
            'api_key' => [$metaTokenRequired ? 'required' : 'nullable', 'string', 'max:1000'],
            'api_secret' => [$twilioSecretRequired ? 'required' : 'nullable', 'string', 'max:1000'],
            'account_sid' => [$twilioSidRequired ? 'required' : 'nullable', 'string', 'max:255'],
            'from_number' => [$isActive ? 'required' : 'nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
        ]);

        if ($settings) {
            if (! $isActive) {
                $validated['api_key'] = '';
                $validated['api_secret'] = '';
                $validated['account_sid'] = '';
                $validated['from_number'] = '';
            } else {
                if (empty($validated['api_key'])) {
                    unset($validated['api_key']);
                }
                if (empty($validated['api_secret'])) {
                    unset($validated['api_secret']);
                }
            }

            $settings->update($validated);
        } else {
            // Create new settings (only if active or if we have credentials)
            if ($isActive || ! empty($validated['api_key']) || ! empty($validated['from_number'])) {
                WhatsAppSettings::create([
                    'company_id' => $currentCompany->id,
                    'provider' => $validated['provider'],
                    'api_key' => $validated['api_key'] ?? '',
                    'api_secret' => $validated['api_secret'] ?? '',
                    'account_sid' => $validated['account_sid'] ?? '',
                    'from_number' => $validated['from_number'] ?? '',
                    'is_active' => $isActive,
                ]);
            }
        }

        return redirect()->back()->with('success', 'WhatsApp settings saved successfully');
    }

    /**
     * Update existing WhatsApp settings
     */
    public function update(Request $request, WhatsAppSettings $whatsAppSettings): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        if (! $currentCompany) {
            return redirect()->back()->with('error', 'No company selected');
        }

        $this->authorize('update', $whatsAppSettings);

        $isActive = $request->boolean('is_active');
        $provider = $request->input('provider', 'twilio');

        $metaTokenRequired = $isActive && $provider === 'meta' && ! filled($whatsAppSettings->api_key);
        $twilioSecretRequired = $isActive && $provider === 'twilio' && ! filled($whatsAppSettings->api_secret);
        $twilioSidRequired = $isActive && $provider === 'twilio' && ! filled($whatsAppSettings->account_sid);

        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:twilio,meta'],
            'api_key' => [$metaTokenRequired ? 'required' : 'nullable', 'string', 'max:1000'],
            'api_secret' => [$twilioSecretRequired ? 'required' : 'nullable', 'string', 'max:1000'],
            'account_sid' => [$twilioSidRequired ? 'required' : 'nullable', 'string', 'max:255'],
            'from_number' => [$isActive ? 'required' : 'nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
        ]);

        // If disabling WhatsApp, clear the credentials
        if (! $isActive) {
            $validated['api_key'] = '';
            $validated['api_secret'] = '';
            $validated['account_sid'] = '';
            $validated['from_number'] = '';
        } else {
            if (empty($validated['api_key'])) {
                unset($validated['api_key']);
            }
            if (empty($validated['api_secret'])) {
                unset($validated['api_secret']);
            }
        }

        $whatsAppSettings->update($validated);

        return redirect()->back()->with('success', 'WhatsApp settings updated successfully');
    }

    /**
     * Delete WhatsApp settings
     */
    public function destroy(WhatsAppSettings $whatsAppSettings): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        if (! $currentCompany) {
            return redirect()->back()->with('error', 'No company selected');
        }

        $this->authorize('delete', $whatsAppSettings);

        $whatsAppSettings->delete();

        return redirect()->back()->with('success', 'WhatsApp settings deleted successfully');
    }
}

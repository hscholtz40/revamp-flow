<?php

namespace App\Http\Controllers;

use App\Models\SMSSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SMSSettingsController extends Controller
{
    /**
     * Display SMS settings
     */
    public function index(): Response
    {
        $settings = SMSSettings::getActive();

        return Inertia::render('administration/SMSSettings', [
            'settings' => $settings
                ? array_merge($settings->toArray(), [
                    'has_bulksms_password' => filled($settings->bulksms_password),
                ])
                : null,
        ]);
    }

    /**
     * Store or update SMS settings
     */
    public function store(Request $request): RedirectResponse
    {
        $isActive = $request->boolean('is_active');

        $validated = $request->validate([
            'bulksms_username' => [$isActive ? 'required' : 'nullable', 'string', 'max:255'],
            'bulksms_password' => [$isActive ? 'required' : 'nullable', 'string', 'max:255'],
            'bulksms_sender_name' => ['nullable', 'string', 'max:11'],
            'is_active' => ['boolean'],
        ]);

        // Deactivate all existing settings
        SMSSettings::query()->update(['is_active' => false]);

        // Create new settings (only if active or if we have credentials)
        if ($isActive || ! empty($validated['bulksms_username']) || ! empty($validated['bulksms_password'])) {
            SMSSettings::create([
                'bulksms_username' => $validated['bulksms_username'] ?? '',
                'bulksms_password' => $validated['bulksms_password'] ?? '',
                'bulksms_sender_name' => $validated['bulksms_sender_name'],
                'is_active' => $isActive,
            ]);
        }

        return redirect()->back()->with('success', 'SMS settings saved successfully');
    }

    /**
     * Update existing SMS settings
     */
    public function update(Request $request, SMSSettings $smsSettings): RedirectResponse
    {
        $isActive = $request->boolean('is_active');
        $passwordRequired = $isActive && ! filled($smsSettings->bulksms_password);

        $validated = $request->validate([
            'bulksms_username' => [$isActive ? 'required' : 'nullable', 'string', 'max:255'],
            'bulksms_password' => [$passwordRequired ? 'required' : 'nullable', 'string', 'max:255'],
            'bulksms_sender_name' => ['nullable', 'string', 'max:11'],
            'is_active' => ['boolean'],
        ]);

        // If disabling SMS, clear the credentials
        if (! $isActive) {
            $validated['bulksms_username'] = '';
            $validated['bulksms_password'] = '';
            $validated['bulksms_sender_name'] = '';
        } elseif (empty($validated['bulksms_password'])) {
            unset($validated['bulksms_password']);
        }

        $smsSettings->update($validated);

        return redirect()->back()->with('success', 'SMS settings updated successfully');
    }

    /**
     * Delete SMS settings
     */
    public function destroy(SMSSettings $smsSettings): RedirectResponse
    {
        $smsSettings->delete();

        return redirect()->back()->with('success', 'SMS settings deleted successfully');
    }
}

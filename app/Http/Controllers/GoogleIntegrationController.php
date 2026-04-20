<?php

namespace App\Http\Controllers;

use App\Models\GoogleIntegrationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GoogleIntegrationController extends Controller
{
    public function show(): Response
    {
        $row = GoogleIntegrationSettings::record();

        return Inertia::render('administration/GoogleIntegration', [
            'maps_map_id' => $row->maps_map_id ?? '',
            'has_maps_api_key' => filled($row->maps_api_key),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'maps_api_key' => ['nullable', 'string', 'max:512'],
            'maps_map_id' => ['nullable', 'string', 'max:255'],
            'clear_maps_api_key' => ['sometimes', 'boolean'],
        ]);

        $row = GoogleIntegrationSettings::record();

        if ($request->boolean('clear_maps_api_key')) {
            $row->maps_api_key = null;
        } elseif (array_key_exists('maps_api_key', $validated) && is_string($validated['maps_api_key']) && trim($validated['maps_api_key']) !== '') {
            $row->maps_api_key = trim($validated['maps_api_key']);
        }

        if (array_key_exists('maps_map_id', $validated)) {
            $mid = trim((string) ($validated['maps_map_id'] ?? ''));
            $row->maps_map_id = $mid !== '' ? $mid : null;
        }

        $row->save();

        return redirect()->back()->with('success', 'Google integration settings saved.');
    }
}

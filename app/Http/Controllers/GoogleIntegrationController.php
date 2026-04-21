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

        return Inertia::render('administration/OtherIntegrations', [
            'maps_map_id' => $row->maps_map_id ?? '',
            'has_maps_api_key' => filled($row->maps_api_key),
            'has_openai_api_key' => filled($row->openai_api_key),
            'ai_enabled' => (bool) ($row->ai_enabled ?? false),
            'ai_admin_only' => (bool) ($row->ai_admin_only ?? true),
            'ai_prompt_logging_enabled' => (bool) ($row->ai_prompt_logging_enabled ?? true),
            'ai_daily_user_limit' => (int) ($row->ai_daily_user_limit ?? 50),
            'ai_daily_company_limit' => (int) ($row->ai_daily_company_limit ?? 500),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'maps_api_key' => ['nullable', 'string', 'max:512'],
            'maps_map_id' => ['nullable', 'string', 'max:255'],
            'clear_maps_api_key' => ['sometimes', 'boolean'],
            'openai_api_key' => ['nullable', 'string', 'max:512'],
            'clear_openai_api_key' => ['sometimes', 'boolean'],
            'ai_enabled' => ['sometimes', 'boolean'],
            'ai_admin_only' => ['sometimes', 'boolean'],
            'ai_prompt_logging_enabled' => ['sometimes', 'boolean'],
            'ai_daily_user_limit' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'ai_daily_company_limit' => ['nullable', 'integer', 'min:1', 'max:100000'],
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

        if ($request->boolean('clear_openai_api_key')) {
            $row->openai_api_key = null;
        } elseif (array_key_exists('openai_api_key', $validated) && is_string($validated['openai_api_key']) && trim($validated['openai_api_key']) !== '') {
            $row->openai_api_key = trim($validated['openai_api_key']);
        }

        if (array_key_exists('ai_enabled', $validated)) {
            $row->ai_enabled = (bool) $validated['ai_enabled'];
        }
        if (array_key_exists('ai_admin_only', $validated)) {
            $row->ai_admin_only = (bool) $validated['ai_admin_only'];
        }
        if (array_key_exists('ai_prompt_logging_enabled', $validated)) {
            $row->ai_prompt_logging_enabled = (bool) $validated['ai_prompt_logging_enabled'];
        }
        if (array_key_exists('ai_daily_user_limit', $validated)) {
            $row->ai_daily_user_limit = (int) ($validated['ai_daily_user_limit'] ?? 50);
        }
        if (array_key_exists('ai_daily_company_limit', $validated)) {
            $row->ai_daily_company_limit = (int) ($validated['ai_daily_company_limit'] ?? 500);
        }

        $row->save();

        return redirect()->back()->with('success', 'Integration settings saved.');
    }
}

<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class AppearanceController extends Controller
{
    /**
     * Show the appearance settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/Appearance', [
            'user' => $user,
        ]);
    }

    /**
     * Update the user's appearance settings.
     */
    public function update(Request $request): RedirectResponse
    {
        try {
            $user = $request->user();

            $validated = $request->validate([
                'theme_primary_hue' => ['nullable', 'integer', 'min:0', 'max:360'],
                'theme_primary_saturation' => ['nullable', 'integer', 'min:0', 'max:100'],
                'theme_primary_lightness' => ['nullable', 'integer', 'min:0', 'max:100'],
                'theme_primary_dark_mode_lightness' => ['nullable', 'integer', 'min:0', 'max:100'],
                'theme_secondary_hue' => ['nullable', 'integer', 'min:0', 'max:360'],
                'theme_secondary_saturation' => ['nullable', 'integer', 'min:0', 'max:100'],
                'theme_secondary_lightness' => ['nullable', 'integer', 'min:0', 'max:100'],
                'theme_secondary_dark_mode_lightness' => ['nullable', 'integer', 'min:0', 'max:100'],
                'theme_accent_hue' => ['nullable', 'integer', 'min:0', 'max:360'],
                'theme_accent_saturation' => ['nullable', 'integer', 'min:0', 'max:100'],
                'theme_accent_lightness' => ['nullable', 'integer', 'min:0', 'max:100'],
                'theme_accent_dark_mode_lightness' => ['nullable', 'integer', 'min:0', 'max:100'],
                'theme_chart_1_hue' => ['nullable', 'integer', 'min:0', 'max:360'],
                'theme_chart_1_saturation' => ['nullable', 'integer', 'min:0', 'max:100'],
                'theme_chart_1_lightness' => ['nullable', 'integer', 'min:0', 'max:100'],
                'theme_chart_2_hue' => ['nullable', 'integer', 'min:0', 'max:360'],
                'theme_chart_2_saturation' => ['nullable', 'integer', 'min:0', 'max:100'],
                'theme_chart_2_lightness' => ['nullable', 'integer', 'min:0', 'max:100'],
                'theme_chart_3_hue' => ['nullable', 'integer', 'min:0', 'max:360'],
                'theme_chart_3_saturation' => ['nullable', 'integer', 'min:0', 'max:100'],
                'theme_chart_3_lightness' => ['nullable', 'integer', 'min:0', 'max:100'],
                'theme_chart_4_hue' => ['nullable', 'integer', 'min:0', 'max:360'],
                'theme_chart_4_saturation' => ['nullable', 'integer', 'min:0', 'max:100'],
                'theme_chart_4_lightness' => ['nullable', 'integer', 'min:0', 'max:100'],
                'theme_chart_5_hue' => ['nullable', 'integer', 'min:0', 'max:360'],
                'theme_chart_5_saturation' => ['nullable', 'integer', 'min:0', 'max:100'],
                'theme_chart_5_lightness' => ['nullable', 'integer', 'min:0', 'max:100'],
                'theme_sidebar_primary_hue' => ['nullable', 'integer', 'min:0', 'max:360'],
                'theme_sidebar_primary_saturation' => ['nullable', 'integer', 'min:0', 'max:100'],
                'theme_sidebar_primary_lightness' => ['nullable', 'integer', 'min:0', 'max:100'],
                'theme_sidebar_primary_dark_mode_lightness' => ['nullable', 'integer', 'min:0', 'max:100'],
                'theme_sidebar_accent_hue' => ['nullable', 'integer', 'min:0', 'max:360'],
                'theme_sidebar_accent_saturation' => ['nullable', 'integer', 'min:0', 'max:100'],
                'theme_sidebar_accent_lightness' => ['nullable', 'integer', 'min:0', 'max:100'],
                'theme_background_light_mode_lightness' => ['nullable', 'integer', 'min:0', 'max:100'],
                'theme_background_dark_mode_lightness' => ['nullable', 'integer', 'min:0', 'max:100'],
                'theme_layout_sidebar_width' => ['nullable', 'string', 'max:20'],
                'theme_layout_sidebar_collapsed_width' => ['nullable', 'string', 'max:20'],
                'theme_layout_header_height' => ['nullable', 'string', 'max:20'],
                'theme_layout_border_radius' => ['nullable', 'string', 'max:20'],
                'theme_layout_spacing_unit' => ['nullable', 'string', 'max:20'],
            ]);

            $user->update($validated);

            return redirect()->back()->with('success', 'Appearance settings updated successfully.');
            
        } catch (\Exception $e) {
            Log::channel('daily')->error('Error in appearance update', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return redirect()->back()->withErrors(['error' => 'Failed to update: ' . $e->getMessage()]);
        }
    }
}
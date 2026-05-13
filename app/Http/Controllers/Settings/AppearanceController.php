<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AppearanceController extends Controller
{
    /**
     * Show the appearance settings page.
     */
    public function edit(Request $request): Response
    {
        $company = $request->user()->currentCompany;
        
        Log::channel('daily')->info('Appearance edit page loaded', [
            'company_id' => $company->id,
            'company_name' => $company->name,
            'logo_path' => $company->logo_path,
            'favicon_path' => $company->favicon_path,
            'has_logo_file_in_db' => !is_null($company->logo_path),
            'has_favicon_file_in_db' => !is_null($company->favicon_path),
        ]);

        return Inertia::render('settings/Appearance', [
            'company' => $company,
        ]);
    }

    /**
     * Update the company's appearance settings.
     */
    public function update(Request $request): RedirectResponse
    {
        try {
            $company = $request->user()->currentCompany;
            
            // Log the entire request for debugging
            Log::channel('daily')->info('=== START APPEARANCE UPDATE ===', [
                'company_id' => $company->id,
                'company_name' => $company->name,
                'timestamp' => now()->toDateTimeString(),
                'has_logo_file' => $request->hasFile('logo'),
                'has_favicon_file' => $request->hasFile('favicon'),
                'all_files' => array_keys($request->allFiles()),
                'all_input_keys' => array_keys($request->all()),
                'request_method' => $request->method(),
                'content_type' => $request->header('Content-Type'),
            ]);

            // Log file details if present
            if ($request->hasFile('logo')) {
                $logoFile = $request->file('logo');
                Log::channel('daily')->info('Logo file details', [
                    'original_name' => $logoFile->getClientOriginalName(),
                    'size' => $logoFile->getSize(),
                    'mime_type' => $logoFile->getMimeType(),
                    'extension' => $logoFile->getClientOriginalExtension(),
                ]);
            }

            if ($request->hasFile('favicon')) {
                $faviconFile = $request->file('favicon');
                Log::channel('daily')->info('Favicon file details', [
                    'original_name' => $faviconFile->getClientOriginalName(),
                    'size' => $faviconFile->getSize(),
                    'mime_type' => $faviconFile->getMimeType(),
                    'extension' => $faviconFile->getClientOriginalExtension(),
                ]);
            }

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
                'theme_background_light_mode_lightness' => ['nullable', 'integer', 'min:0', 'max:100'],
                'theme_background_dark_mode_lightness' => ['nullable', 'integer', 'min:0', 'max:100'],
                'theme_layout_sidebar_width' => ['nullable', 'string', 'max:20'],
                'theme_layout_sidebar_collapsed_width' => ['nullable', 'string', 'max:20'],
                'theme_layout_header_height' => ['nullable', 'string', 'max:20'],
                'theme_layout_border_radius' => ['nullable', 'string', 'max:20'],
                'theme_layout_spacing_unit' => ['nullable', 'string', 'max:20'],
                'logo' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,gif,svg,ico,webp', 'max:2048'],
                'favicon' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,gif,svg,ico,webp', 'max:2048'],
            ]);

            Log::channel('daily')->info('Validation passed', [
                'validated_fields' => array_keys($validated),
            ]);

            $updateData = [];

            // Handle logo upload
            if ($request->hasFile('logo')) {
                $logoFile = $request->file('logo');
                
                // Delete old logo if exists
                if ($company->logo_path && Storage::disk('public')->exists($company->logo_path)) {
                    Storage::disk('public')->delete($company->logo_path);
                    Log::channel('daily')->info('Deleted old logo', ['path' => $company->logo_path]);
                }
                
                // Store new logo
                $path = $logoFile->store('company-logos', 'public');
                $updateData['logo_path'] = $path;
                
                Log::channel('daily')->info('Uploaded new logo', [
                    'path' => $path,
                    'full_path' => storage_path('app/public/' . $path),
                    'file_exists' => file_exists(storage_path('app/public/' . $path))
                ]);
            }

            // Handle favicon upload
            if ($request->hasFile('favicon')) {
                $faviconFile = $request->file('favicon');
                
                // Delete old favicon if exists
                if ($company->favicon_path && Storage::disk('public')->exists($company->favicon_path)) {
                    Storage::disk('public')->delete($company->favicon_path);
                    Log::channel('daily')->info('Deleted old favicon', ['path' => $company->favicon_path]);
                }
                
                // Store new favicon
                $path = $faviconFile->store('company-favicons', 'public');
                $updateData['favicon_path'] = $path;
                
                Log::channel('daily')->info('Uploaded new favicon', [
                    'path' => $path,
                    'full_path' => storage_path('app/public/' . $path),
                    'file_exists' => file_exists(storage_path('app/public/' . $path))
                ]);
            }

            // Add all other validated fields to updateData (excluding logo and favicon)
            foreach ($validated as $key => $value) {
                if (!in_array($key, ['logo', 'favicon'])) {
                    $updateData[$key] = $value;
                }
            }

            Log::channel('daily')->info('Data to update', [
                'update_data_keys' => array_keys($updateData),
                'will_update_logo' => isset($updateData['logo_path']),
                'will_update_favicon' => isset($updateData['favicon_path']),
            ]);

            // Update company
            $updated = $company->update($updateData);
            
            Log::channel('daily')->info('Company update result', [
                'success' => $updated,
                'company_id' => $company->id,
                'new_logo_path' => $company->fresh()->logo_path,
                'new_favicon_path' => $company->fresh()->favicon_path,
            ]);

            // Verify the update was saved
            $freshCompany = $company->fresh();
            Log::channel('daily')->info('Final company state after update', [
                'logo_path_in_db' => $freshCompany->logo_path,
                'favicon_path_in_db' => $freshCompany->favicon_path,
                'logo_path_was_updated' => $freshCompany->logo_path !== null,
                'favicon_path_was_updated' => $freshCompany->favicon_path !== null,
            ]);

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
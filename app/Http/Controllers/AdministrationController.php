<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Backup;
use App\Models\Contact;
use App\Models\Group;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Services\InstanceLicenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class AdministrationController extends Controller
{
    public function __construct(
        private readonly InstanceLicenseService $licenseService
    ) {
    }

    public function index(): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        return Inertia::render('Administration', [
                'stats' => [
                    'users_count' => User::count(),
                    'groups_count' => Group::count(),
                    'contacts_count' => Contact::count(),
                    'products_count' => Product::count(),
                    'audit_logs_count' => $currentCompany 
                        ? AuditLog::where('company_id', $currentCompany->id)->count()
                        : AuditLog::count(),
                    'backups_count' => Backup::where('status', 'completed')->count(),
                    'teams_count' => $currentCompany
                        ? Team::where('company_id', $currentCompany->id)->count()
                        : Team::count(),
                ],
        ]);
    }

    public function upgradeDatabase(): RedirectResponse
    {
        try {
            // Run migrations
            Artisan::call('migrate', ['--force' => true]);
            
            $output = Artisan::output();
            
            Log::info('Database upgrade completed', [
                'user_id' => auth()->id(),
                'output' => $output,
            ]);

            return redirect()->back()->with('success', 'Database upgraded successfully. ' . trim($output));
        } catch (\Exception $e) {
            Log::error('Database upgrade failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', 'Database upgrade failed: ' . $e->getMessage());
        }
    }

    public function moduleVisibility(): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        return Inertia::render('administration/ModuleVisibility', [
            'visibleModules' => $currentCompany->getVisibleModules(),
        ]);
    }

    public function update(\Illuminate\Http\Request $request): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        $validated = $request->validate([
            'visible_modules' => ['nullable', 'array'],
            'visible_modules.*' => ['string'],
        ]);

        // Handle empty array vs null: empty array means hide all, null means show all
        // If visible_modules is not set or is null, set to null (show all)
        // If it's an empty array [], set to empty array (hide all)
        // If it has values, use those values
        $visibleModules = null;
        if (array_key_exists('visible_modules', $validated)) {
            if ($validated['visible_modules'] === null) {
                $visibleModules = null; // Show all
            } elseif (is_array($validated['visible_modules'])) {
                $visibleModules = empty($validated['visible_modules']) ? [] : $validated['visible_modules'];
            }
        }

        $currentCompany->visible_modules = $visibleModules;
        $currentCompany->save();
        
        // Refresh the model to ensure the data is up to date
        $currentCompany->refresh();

        Log::info('Module visibility updated', [
            'user_id' => auth()->id(),
            'company_id' => $currentCompany->id,
            'visible_modules' => $currentCompany->visible_modules,
        ]);

        return redirect()->back()->with('success', 'Module visibility updated successfully.');
    }

    public function license(): Response
    {
        $settings = $this->licenseService->getSettings();
        $validation = $this->licenseService->validate();

        return Inertia::render('administration/License', [
            'license' => [
                'license_key' => $settings->license_key,
                'status' => $settings->status,
                'message' => $settings->message,
                'licensed_url' => $settings->licensed_url,
                'limited_users' => $settings->limited_users,
                'standard_users' => $settings->standard_users,
                'last_validated_at' => $settings->last_validated_at?->toIso8601String(),
                'valid' => $validation['valid'],
                'validation_message' => $validation['message'],
            ],
            'canManageLicense' => (bool) auth()->user()?->isAdministrator(),
        ]);
    }

    public function updateLicense(Request $request): RedirectResponse
    {
        if (!auth()->user()?->isAdministrator()) {
            abort(403, 'Access denied. Administrator privileges required.');
        }

        $validated = $request->validate([
            'license_key' => ['required', 'string', 'max:64'],
        ]);

        $this->licenseService->saveLicenseKey($validated['license_key']);
        $validation = $this->licenseService->validate(true);

        if ($validation['valid']) {
            return redirect()->back()->with('success', 'License key validated successfully.');
        }

        return redirect()->back()->with('error', $validation['message']);
    }

    public function documentNumbering(): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        if (!$currentCompany) {
            abort(404, 'No active company selected.');
        }

        return Inertia::render('administration/DocumentNumbering', [
            'numbering' => [
                'invoice_prefix' => $currentCompany->invoice_number_prefix,
                'invoice_next' => $currentCompany->invoice_number_next,
                'quote_prefix' => $currentCompany->quote_number_prefix,
                'quote_next' => $currentCompany->quote_number_next,
                'jobcard_prefix' => $currentCompany->jobcard_number_prefix,
                'jobcard_next' => $currentCompany->jobcard_number_next,
                'credit_note_prefix' => $currentCompany->credit_note_number_prefix,
                'credit_note_next' => $currentCompany->credit_note_number_next,
                'purchase_order_prefix' => $currentCompany->purchase_order_number_prefix,
                'purchase_order_next' => $currentCompany->purchase_order_number_next,
            ],
            'company' => [
                'id' => $currentCompany->id,
                'name' => $currentCompany->name,
            ],
        ]);
    }

    public function updateDocumentNumbering(Request $request): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        if (!$currentCompany) {
            abort(404, 'No active company selected.');
        }

        $validated = $request->validate([
            'invoice_prefix' => ['nullable', 'string', 'max:50'],
            'invoice_next' => ['nullable', 'integer', 'min:1'],
            'quote_prefix' => ['nullable', 'string', 'max:50'],
            'quote_next' => ['nullable', 'integer', 'min:1'],
            'jobcard_prefix' => ['nullable', 'string', 'max:50'],
            'jobcard_next' => ['nullable', 'integer', 'min:1'],
            'credit_note_prefix' => ['nullable', 'string', 'max:50'],
            'credit_note_next' => ['nullable', 'integer', 'min:1'],
            'purchase_order_prefix' => ['nullable', 'string', 'max:50'],
            'purchase_order_next' => ['nullable', 'integer', 'min:1'],
        ]);

        $currentCompany->update([
            'invoice_number_prefix' => $this->normalizePrefix($validated['invoice_prefix'] ?? null),
            'invoice_number_next' => $validated['invoice_next'] ?? null,
            'quote_number_prefix' => $this->normalizePrefix($validated['quote_prefix'] ?? null),
            'quote_number_next' => $validated['quote_next'] ?? null,
            'jobcard_number_prefix' => $this->normalizePrefix($validated['jobcard_prefix'] ?? null),
            'jobcard_number_next' => $validated['jobcard_next'] ?? null,
            'credit_note_number_prefix' => $this->normalizePrefix($validated['credit_note_prefix'] ?? null),
            'credit_note_number_next' => $validated['credit_note_next'] ?? null,
            'purchase_order_number_prefix' => $this->normalizePrefix($validated['purchase_order_prefix'] ?? null),
            'purchase_order_number_next' => $validated['purchase_order_next'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Document numbering settings updated.');
    }

    private function normalizePrefix(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }
}

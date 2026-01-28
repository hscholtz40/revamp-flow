<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Backup;
use App\Models\Contact;
use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class AdministrationController extends Controller
{
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
}

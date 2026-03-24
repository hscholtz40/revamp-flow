<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\License;
use App\Services\CpanelService;
use App\Support\CompanyScopedRules;
use App\Support\SafeLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class LicenseController extends Controller
{
    /**
     * Display a listing of licenses.
     */
    public function index(Request $request): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();

        if (! $currentCompany) {
            return Inertia::render('licenses/Index', [
                'licenses' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15),
                'filters' => [
                    'search' => $request->input('search', ''),
                    'status' => $request->input('status', ''),
                ],
                'canViewFullLicenseKey' => auth()->user()->isAdministrator(),
            ]);
        }

        $search = $request->input('search');
        $status = $request->input('status');

        $licenses = License::where('company_id', $currentCompany->id)
            ->with('customer')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('license_key', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($cq) use ($search) {
                            $cq->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status, function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $maskKey = ! auth()->user()->isAdministrator();
        $licenses->through(fn (License $l) => $this->licenseToPageArray($l, $maskKey));

        return Inertia::render('licenses/Index', [
            'licenses' => $licenses,
            'filters' => [
                'search' => $search ?? '',
                'status' => $status ?? '',
            ],
            'canViewFullLicenseKey' => ! $maskKey,
        ]);
    }

    /**
     * Show the form for creating a new license.
     */
    public function create(): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();

        $customers = $currentCompany
            ? Customer::where('company_id', $currentCompany->id)->orderBy('name')->get(['id', 'name', 'email'])
            : collect();

        return Inertia::render('licenses/Create', [
            'customers' => $customers,
        ]);
    }

    /**
     * Store a newly created license.
     */
    public function store(Request $request): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();

        if (! $currentCompany) {
            return redirect()->back()
                ->withErrors(['message' => 'No company selected. Please select a company first.']);
        }

        $validated = $request->validate([
            'customer_id' => ['required', CompanyScopedRules::customer($currentCompany->id)],
            'url' => ['nullable', 'url', 'max:255'],
            'limited_users' => ['required', 'integer', 'min:0'],
            'standard_users' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:active,suspended,expired,revoked'],
            'notes' => ['nullable', 'string'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $validated['company_id'] = $currentCompany->id;
        $validated['license_key'] = License::generateLicenseKey();

        $license = License::create($validated);

        $message = auth()->user()->isAdministrator()
            ? 'License created successfully. Key: '.$license->license_key
            : 'License created successfully. Ask an administrator for the license key.';

        return redirect()->route('licenses.show', $license)
            ->with('success', $message);
    }

    /**
     * Display the specified license.
     */
    public function show(License $license): Response
    {
        $this->authorize('view', $license);

        $license->load('customer');

        $maskKey = ! auth()->user()->isAdministrator();

        return Inertia::render('licenses/Show', [
            'license' => $this->licenseToPageArray($license, $maskKey),
            'canManageLicenseInfrastructure' => auth()->user()->isAdministrator(),
            'canViewFullLicenseKey' => ! $maskKey,
        ]);
    }

    /**
     * Show the form for editing the specified license.
     */
    public function edit(License $license): Response
    {
        $this->authorize('view', $license);

        $currentCompany = auth()->user()->getCurrentCompany();

        $customers = Customer::where('company_id', $currentCompany->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $license->load('customer');

        $maskKey = ! auth()->user()->isAdministrator();

        return Inertia::render('licenses/Edit', [
            'license' => $this->licenseToPageArray($license, $maskKey),
            'customers' => $customers,
            'canViewFullLicenseKey' => ! $maskKey,
        ]);
    }

    /**
     * Update the specified license.
     */
    public function update(Request $request, License $license): RedirectResponse
    {
        $this->authorize('view', $license);

        $validated = $request->validate([
            'customer_id' => ['required', CompanyScopedRules::customer($license->company_id)],
            'url' => ['nullable', 'url', 'max:255'],
            'limited_users' => ['required', 'integer', 'min:0'],
            'standard_users' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:active,suspended,expired,revoked'],
            'notes' => ['nullable', 'string'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $license->update($validated);

        return redirect()->route('licenses.show', $license)
            ->with('success', 'License updated successfully.');
    }

    /**
     * Remove the specified license.
     */
    public function destroy(License $license): RedirectResponse
    {
        $this->authorize('view', $license);

        $license->delete();

        return redirect()->route('licenses.index')
            ->with('success', 'License deleted successfully.');
    }

    /**
     * Deploy a license instance to cPanel.
     */
    public function deploy(Request $request, License $license): RedirectResponse
    {
        $this->authorize('view', $license);

        $request->validate([
            'zip_file' => ['required', 'file', 'mimes:zip', 'max:512000'], // max 500MB
        ]);

        if (! $license->url) {
            return redirect()->back()
                ->withErrors(['message' => 'License must have a URL set before deploying.']);
        }

        if ($license->deployed_at) {
            return redirect()->back()
                ->withErrors(['message' => 'This license has already been deployed. Use Upgrade to update the instance.']);
        }

        $cpanel = new CpanelService;

        if (! $cpanel->isConfigured()) {
            return redirect()->back()
                ->withErrors(['message' => 'cPanel integration is not configured. Please set the CPANEL_* environment variables.']);
        }

        // Extract subdomain from the URL
        $parsedUrl = parse_url($license->url);
        $host = $parsedUrl['host'] ?? '';
        $subdomain = explode('.', $host)[0] ?? '';

        if (empty($subdomain)) {
            return redirect()->back()
                ->withErrors(['message' => 'Could not determine subdomain from the license URL.']);
        }

        // Store the uploaded file temporarily
        $zipFile = $request->file('zip_file');
        $zipPath = $zipFile->store('temp', 'local');
        $fullZipPath = storage_path('app/private/'.$zipPath);

        try {
            $result = $cpanel->deploy($subdomain, $fullZipPath, $license->url);

            // Clean up temp file
            if (file_exists($fullZipPath)) {
                unlink($fullZipPath);
            }

            if ($result['success']) {
                $license->update(['deployed_at' => now()]);

                Log::info('License deployed successfully', [
                    'license_id' => $license->id,
                    'subdomain' => $subdomain,
                ]);

                return redirect()->route('licenses.show', $license)
                    ->with('success', 'Instance deployed successfully to '.$license->url);
            }

            Log::error('License deployment failed', SafeLog::redactContext([
                'license_id' => $license->id,
                'success' => $result['success'] ?? null,
                'message_excerpt' => SafeLog::excerpt($result['message'] ?? '', 200),
            ]));

            return redirect()->back()
                ->withErrors(['message' => 'Deployment failed: '.$result['message']]);

        } catch (\Exception $e) {
            // Clean up temp file on failure
            if (file_exists($fullZipPath)) {
                unlink($fullZipPath);
            }

            Log::error('License deployment exception', [
                'license_id' => $license->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->withErrors(['message' => 'Deployment failed: '.$e->getMessage()]);
        }
    }

    /**
     * Upgrade a license instance on cPanel.
     */
    public function upgrade(Request $request, License $license): RedirectResponse
    {
        $this->authorize('view', $license);

        $request->validate([
            'zip_file' => ['required', 'file', 'mimes:zip', 'max:512000'], // max 500MB
        ]);

        if (! $license->url) {
            return redirect()->back()
                ->withErrors(['message' => 'License must have a URL set before upgrading.']);
        }

        $cpanel = new CpanelService;

        if (! $cpanel->isConfigured()) {
            return redirect()->back()
                ->withErrors(['message' => 'cPanel integration is not configured. Please set the CPANEL_* environment variables.']);
        }

        // Extract subdomain from the URL
        $parsedUrl = parse_url($license->url);
        $host = $parsedUrl['host'] ?? '';
        $subdomain = explode('.', $host)[0] ?? '';

        if (empty($subdomain)) {
            return redirect()->back()
                ->withErrors(['message' => 'Could not determine subdomain from the license URL.']);
        }

        // Store the uploaded file temporarily
        $zipFile = $request->file('zip_file');
        $zipPath = $zipFile->store('temp', 'local');
        $fullZipPath = storage_path('app/private/'.$zipPath);

        try {
            $result = $cpanel->upgrade($subdomain, $fullZipPath);

            // Clean up temp file
            if (file_exists($fullZipPath)) {
                unlink($fullZipPath);
            }

            if ($result['success']) {
                Log::info('License upgraded successfully', [
                    'license_id' => $license->id,
                    'subdomain' => $subdomain,
                ]);

                return redirect()->route('licenses.show', $license)
                    ->with('success', 'Instance upgraded successfully at '.$license->url);
            }

            Log::error('License upgrade failed', SafeLog::redactContext([
                'license_id' => $license->id,
                'success' => $result['success'] ?? null,
                'message_excerpt' => SafeLog::excerpt($result['message'] ?? '', 200),
            ]));

            return redirect()->back()
                ->withErrors(['message' => 'Upgrade failed: '.$result['message']]);

        } catch (\Exception $e) {
            // Clean up temp file on failure
            if (file_exists($fullZipPath)) {
                unlink($fullZipPath);
            }

            Log::error('License upgrade exception', [
                'license_id' => $license->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->withErrors(['message' => 'Upgrade failed: '.$e->getMessage()]);
        }
    }

    /**
     * Force SSL on the license's subdomain in cPanel.
     */
    public function forceSSL(License $license): RedirectResponse
    {
        $this->authorize('view', $license);

        if (! $license->url) {
            return redirect()->back()
                ->withErrors(['message' => 'License must have a URL set before enabling SSL.']);
        }

        $cpanel = new CpanelService;

        if (! $cpanel->isConfigured()) {
            return redirect()->back()
                ->withErrors(['message' => 'cPanel integration is not configured. Please set the CPANEL_* environment variables.']);
        }

        $parsedUrl = parse_url($license->url);
        $host = $parsedUrl['host'] ?? '';

        if (empty($host)) {
            return redirect()->back()
                ->withErrors(['message' => 'Could not determine domain from the license URL.']);
        }

        try {
            $result = $cpanel->forceSSL($host);

            if ($result['success']) {
                return redirect()->route('licenses.show', $license)
                    ->with('success', 'AutoSSL requested and HTTPS redirect enabled for '.$host);
            }

            return redirect()->back()
                ->withErrors(['message' => 'Force SSL failed: '.$result['message']]);

        } catch (\Exception $e) {
            Log::error('Force SSL exception', [
                'license_id' => $license->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->withErrors(['message' => 'Force SSL failed: '.$e->getMessage()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function licenseToPageArray(License $license, bool $maskLicenseKey): array
    {
        $license->loadMissing('customer');
        $data = $license->toArray();
        if ($maskLicenseKey) {
            $data['license_key'] = License::maskLicenseKey($license->license_key);
        }

        return $data;
    }
}

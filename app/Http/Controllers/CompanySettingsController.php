<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\PdfTemplate;
use App\Models\ReminderSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CompanySettingsController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();
        
        // Get companies the user has access to
        if ($user->companies()->count() === 0) {
            // User has access to all companies - show all active companies
            $companies = Company::where('is_active', true)
                ->orderBy('is_default', 'desc')
                ->orderBy('name')
                ->get();
            } else {
                // User has access to specific companies - only show those
                $companies = $user->companies()
                    ->where('is_active', true)
                    ->orderBy('is_default', 'desc')
                    ->orderBy('name')
                    ->get(['companies.id', 'companies.name', 'companies.logo_path', 'companies.is_default', 'companies.is_active']);
            }
        
        return Inertia::render('company-settings/Index', [
            'companies' => $companies,
            'currentCompany' => $user->getCurrentCompany(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('company-settings/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'vat_number' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
            'description' => ['nullable', 'string'],
            'invoice_footer' => ['nullable', 'string'],
            'jobcard_footer' => ['nullable', 'string'],
            'quote_footer' => ['nullable', 'string'],
            'default_invoice_terms' => ['nullable', 'string'],
            'default_quote_terms' => ['nullable', 'string'],
            'default_jobcard_terms' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_encryption' => ['nullable', 'string', 'in:tls,ssl'],
            'smtp_from_email' => ['nullable', 'email', 'max:255'],
            'smtp_from_name' => ['nullable', 'string', 'max:255'],
            'whatsapp_business_number' => ['nullable', 'string', 'max:20'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:255'],
            'bank_sort_code' => ['nullable', 'string', 'max:50'],
        ]);

        if ($request->hasFile('logo')) {
            $validated['logo_path'] = $request->file('logo')->store('company-logos', 'public');
        }

        $company = Company::create($validated);

        // Create default reminder settings for the new company
        $company->getReminderSettings();
        
        // Create default purchase order PDF template
        $this->createDefaultPurchaseOrderTemplate($company);

        // If this company is set as default, update others
        if ($validated['is_default'] ?? false) {
            $company->setAsDefault();
        }

        return redirect()->route('company-settings.index')->with('success', 'Company created successfully');
    }

    public function show(Company $company): Response
    {
        // Check if user has access to this company
        if (!auth()->user()->hasAccessToCompany($company->id)) {
            abort(403, 'You do not have access to this company.');
        }

        return Inertia::render('company-settings/Show', [
            'company' => $company,
        ]);
    }

    public function edit(Company $company): Response
    {
        // Check if user has access to this company
        if (!auth()->user()->hasAccessToCompany($company->id)) {
            abort(403, 'You do not have access to this company.');
        }

        $reminderSettings = $company->getReminderSettings();

        return Inertia::render('company-settings/Edit', [
            'company' => $company,
            'reminderSettings' => $reminderSettings,
        ]);
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        // Check if user has access to this company
        if (!auth()->user()->hasAccessToCompany($company->id)) {
            abort(403, 'You do not have access to this company.');
        }

        // Debug: Log the incoming request data
        \Log::info('Company update request data:', [
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'has_logo' => $request->hasFile('logo'),
            'all_data' => $request->all(),
            'content_type' => $request->header('Content-Type'),
            'method' => $request->method()
        ]);
        
        // Handle case where multipart/form-data causes empty data
        if (empty($request->all()) && $request->hasFile('logo')) {
            \Log::info('Empty request data detected, using existing company data');
            // Use existing company data and only update the logo
            $validated = [
                'name' => $company->name,
                'email' => $company->email,
                'phone' => $company->phone,
                'address' => $company->address,
                'city' => $company->city,
                'state' => $company->state,
                'postal_code' => $company->postal_code,
                'country' => $company->country,
                'website' => $company->website,
                'description' => $company->description,
                'invoice_footer' => $company->invoice_footer,
                'jobcard_footer' => $company->jobcard_footer,
                'quote_footer' => $company->quote_footer,
                'default_invoice_terms' => $company->default_invoice_terms,
                'default_quote_terms' => $company->default_quote_terms,
                'default_jobcard_terms' => $company->default_jobcard_terms,
                'is_active' => $company->is_active,
                'is_default' => $company->is_default,
            ];
            
            if ($request->hasFile('logo')) {
                $validated['logo_path'] = $request->file('logo')->store('company-logos', 'public');
            }
            
            $company->update($validated);
            return redirect()->route('company-settings.index')->with('success', 'Company updated successfully.');
        }
        
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'vat_number' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
            'description' => ['nullable', 'string'],
            'invoice_footer' => ['nullable', 'string'],
            'jobcard_footer' => ['nullable', 'string'],
            'quote_footer' => ['nullable', 'string'],
            'default_invoice_terms' => ['nullable', 'string'],
            'default_quote_terms' => ['nullable', 'string'],
            'default_jobcard_terms' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_encryption' => ['nullable', 'string', 'in:tls,ssl'],
            'smtp_from_email' => ['nullable', 'email', 'max:255'],
            'smtp_from_name' => ['nullable', 'string', 'max:255'],
            'whatsapp_business_number' => ['nullable', 'string', 'max:20'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:255'],
            'bank_sort_code' => ['nullable', 'string', 'max:50'],
        ]);

        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($company->logo_path && Storage::exists($company->logo_path)) {
                Storage::delete($company->logo_path);
            }
            $validated['logo_path'] = $request->file('logo')->store('company-logos', 'public');
        }

        // Only update SMTP password if a new one is provided
        if (empty($validated['smtp_password'])) {
            unset($validated['smtp_password']);
        }

        $company->update($validated);

        // If this company is set as default, update others
        if ($validated['is_default'] ?? false) {
            $company->setAsDefault();
        }

        return redirect()->route('company-settings.index')->with('success', 'Company updated successfully');
    }

    public function destroy(Company $company): RedirectResponse
    {
        // Check if user has access to this company
        if (!auth()->user()->hasAccessToCompany($company->id)) {
            abort(403, 'You do not have access to this company.');
        }

        // Don't allow deleting the last company
        if (Company::count() <= 1) {
            return redirect()->route('company-settings.index')
                ->with('error', 'Cannot delete the last company. At least one company must exist.');
        }

        // Delete logo file if exists
        if ($company->logo_path && Storage::exists($company->logo_path)) {
            Storage::delete($company->logo_path);
        }

        $company->delete();

        return redirect()->route('company-settings.index')->with('success', 'Company deleted successfully');
    }

    public function setDefault(Company $company): RedirectResponse
    {
        $company->setAsDefault();

        return redirect()->route('company-settings.index')->with('success', 'Default company updated');
    }

    public function switch(Company $company): RedirectResponse
    {
        $user = auth()->user();
        
        // Check if user has access to this company
        if (!$user->hasAccessToCompany($company->id)) {
            return redirect()->back()->with('error', 'You do not have access to this company.');
        }

        // Check if company is active
        if (!$company->is_active) {
            return redirect()->back()->with('error', 'This company is not active.');
        }

        // Set the current company for the authenticated user
        $user->update(['current_company_id' => $company->id]);

        return redirect()->back()->with('success', "Switched to {$company->name}");
    }

    public function uploadLogo(Request $request, Company $company): RedirectResponse
    {
        // Check if user has access to this company
        if (!auth()->user()->hasAccessToCompany($company->id)) {
            abort(403, 'You do not have access to this company.');
        }

        $validated = $request->validate([
            'logo' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
        ]);

        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($company->logo_path && Storage::exists($company->logo_path)) {
                Storage::delete($company->logo_path);
            }
            $validated['logo_path'] = $request->file('logo')->store('company-logos', 'public');
        }

        $company->update($validated);
        
        return redirect()->route('company-settings.index')->with('success', 'Company logo updated successfully.');
    }

    /**
     * Update reminder settings for a company.
     */
    public function updateReminderSettings(Request $request, Company $company): RedirectResponse
    {
        // Check if user has access to this company
        if (!auth()->user()->hasAccessToCompany($company->id)) {
            abort(403, 'You do not have access to this company.');
        }

        $validated = $request->validate([
            'automation_enabled' => ['boolean'],
            'overdue_invoice_email_enabled' => ['boolean'],
            'overdue_invoice_sms_enabled' => ['boolean'],
            'overdue_invoice_days_after_due' => ['integer', 'min:0'],
            'overdue_invoice_frequency_days' => ['integer', 'min:1'],
            'overdue_invoice_email_template' => ['nullable', 'string'],
            'overdue_invoice_sms_template' => ['nullable', 'string'],
            'overdue_invoice_whatsapp_enabled' => ['boolean'],
            'overdue_invoice_whatsapp_template' => ['nullable', 'string'],
            'overdue_invoice_whatsapp_template_name' => ['nullable', 'string', 'max:255'],
            'expiring_quote_email_enabled' => ['boolean'],
            'expiring_quote_sms_enabled' => ['boolean'],
            'expiring_quote_whatsapp_enabled' => ['boolean'],
            'expiring_quote_days_before' => ['integer', 'min:0'],
            'expiring_quote_email_template' => ['nullable', 'string'],
            'expiring_quote_sms_template' => ['nullable', 'string'],
            'expiring_quote_whatsapp_template' => ['nullable', 'string'],
            'expiring_quote_whatsapp_template_name' => ['nullable', 'string', 'max:255'],
            'payment_received_email_enabled' => ['boolean'],
            'payment_received_sms_enabled' => ['boolean'],
            'payment_received_whatsapp_enabled' => ['boolean'],
            'payment_received_email_template' => ['nullable', 'string'],
            'payment_received_sms_template' => ['nullable', 'string'],
            'payment_received_whatsapp_template' => ['nullable', 'string'],
            'payment_received_whatsapp_template_name' => ['nullable', 'string', 'max:255'],
            'invoice_created_email_enabled' => ['boolean'],
            'invoice_created_sms_enabled' => ['boolean'],
            'invoice_created_whatsapp_enabled' => ['boolean'],
            'invoice_created_email_template' => ['nullable', 'string'],
            'invoice_created_sms_template' => ['nullable', 'string'],
            'invoice_created_whatsapp_template' => ['nullable', 'string'],
            'invoice_created_whatsapp_template_name' => ['nullable', 'string', 'max:255'],
            'quote_created_email_enabled' => ['boolean'],
            'quote_created_sms_enabled' => ['boolean'],
            'quote_created_whatsapp_enabled' => ['boolean'],
            'quote_created_email_template' => ['nullable', 'string'],
            'quote_created_sms_template' => ['nullable', 'string'],
            'quote_created_whatsapp_template' => ['nullable', 'string'],
            'quote_created_whatsapp_template_name' => ['nullable', 'string', 'max:255'],
            'jobcard_created_email_enabled' => ['boolean'],
            'jobcard_created_sms_enabled' => ['boolean'],
            'jobcard_created_whatsapp_enabled' => ['boolean'],
            'jobcard_created_email_template' => ['nullable', 'string'],
            'jobcard_created_sms_template' => ['nullable', 'string'],
            'jobcard_created_whatsapp_template' => ['nullable', 'string'],
            'jobcard_created_whatsapp_template_name' => ['nullable', 'string', 'max:255'],
            'jobcard_status_updated_email_enabled' => ['boolean'],
            'jobcard_status_updated_sms_enabled' => ['boolean'],
            'jobcard_status_updated_whatsapp_enabled' => ['boolean'],
            'jobcard_status_updated_email_template' => ['nullable', 'string'],
            'jobcard_status_updated_sms_template' => ['nullable', 'string'],
            'jobcard_status_updated_whatsapp_template' => ['nullable', 'string'],
            'jobcard_status_updated_whatsapp_template_name' => ['nullable', 'string', 'max:255'],
            'overdue_invoice_whatsapp_template_variables' => ['nullable', 'array'],
            'expiring_quote_whatsapp_template_variables' => ['nullable', 'array'],
            'payment_received_whatsapp_template_variables' => ['nullable', 'array'],
            'invoice_created_whatsapp_template_variables' => ['nullable', 'array'],
            'quote_created_whatsapp_template_variables' => ['nullable', 'array'],
            'jobcard_created_whatsapp_template_variables' => ['nullable', 'array'],
            'jobcard_status_updated_whatsapp_template_variables' => ['nullable', 'array'],
        ]);

        $reminderSettings = $company->getReminderSettings();
        $reminderSettings->update($validated);

        return redirect()->back()->with('success', 'Reminder settings updated successfully.');
    }
    
    /**
     * Create default purchase order PDF template for a company
     */
    protected function createDefaultPurchaseOrderTemplate(Company $company): void
    {
        // Check if default template already exists
        $existing = PdfTemplate::where('company_id', $company->id)
            ->where('module', 'purchase-order')
            ->where('is_default', true)
            ->first();
        
        if ($existing) {
            return;
        }
        
        // Get templates from the command class
        $command = new \App\Console\Commands\CreateDefaultPurchaseOrderTemplates();
        $htmlTemplate = $command->getDefaultHtmlTemplate();
        $cssStyles = $command->getDefaultCssStyles();
        
        PdfTemplate::create([
            'company_id' => $company->id,
            'module' => 'purchase-order',
            'name' => 'Default Purchase Order Template',
            'html_template' => $htmlTemplate,
            'css_styles' => $cssStyles,
            'is_active' => true,
            'is_default' => true,
        ]);
    }
}

<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Jobcard;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\ReminderLog;
use App\Models\ReminderSettings;
use App\Models\SMSSettings;
use App\Models\WhatsAppSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ReminderService
{
    protected ?BulkSMSService $smsService = null;
    protected ?WhatsAppService $whatsappService = null;

    public function __construct()
    {
        $this->smsService = $this->getSMSService();
        $this->whatsappService = $this->getWhatsAppService();
        
        if ($this->smsService) {
            Log::info('ReminderService initialized with SMS service');
        } else {
            Log::warning('ReminderService initialized without SMS service - SMS settings may not be configured');
        }
        
        if ($this->whatsappService) {
            Log::info('ReminderService initialized with WhatsApp service');
        } else {
            Log::warning('ReminderService initialized without WhatsApp service - WhatsApp settings may not be configured');
        }
    }

    /**
     * Get SMS service instance if configured.
     */
    protected function getSMSService(): ?BulkSMSService
    {
        $smsSettings = SMSSettings::getActive();
        if ($smsSettings && SMSSettings::isConfigured()) {
            return new BulkSMSService(
                $smsSettings->bulksms_username,
                $smsSettings->bulksms_password,
                $smsSettings->bulksms_sender_name
            );
        }
        return null;
    }

    /**
     * Get WhatsApp service instance if configured.
     * 
     * @param int|null $companyId Optional company ID to get settings for a specific company
     */
    protected function getWhatsAppService(?int $companyId = null): ?WhatsAppService
    {
        $whatsappSettings = $companyId 
            ? WhatsAppSettings::getForCompany($companyId)
            : WhatsAppSettings::getCurrent();
            
        if ($whatsappSettings && $whatsappSettings->is_active && $whatsappSettings->api_key && $whatsappSettings->from_number) {
            return new WhatsAppService(
                $whatsappSettings->provider,
                $whatsappSettings->api_key,
                $whatsappSettings->api_secret,
                $whatsappSettings->account_sid,
                $whatsappSettings->from_number,
                null, // default_template_name - no longer used
                'en' // default_template_language - default fallback
            );
        }
        return null;
    }

    /**
     * Configure SMTP settings for email sending.
     * Uses company SMTP settings if available, otherwise falls back to user settings.
     * 
     * @return bool Returns true if SMTP was configured, false otherwise
     */
    protected function configureSmtp(Company $company): bool
    {
        Config::set('mail.reply_to.address', $company->email ?: null);
        Config::set('mail.reply_to.name', $company->name ?: null);

        // Use company SMTP settings if configured
        if ($company->hasSmtpConfigured()) {
            Config::set('mail.mailers.smtp.host', $company->smtp_host);
            Config::set('mail.mailers.smtp.port', $company->smtp_port ?? 587);
            Config::set('mail.mailers.smtp.username', $company->smtp_username);
            Config::set('mail.mailers.smtp.password', $company->smtp_password);
            Config::set('mail.mailers.smtp.encryption', $company->smtp_encryption ?? 'tls');
            Config::set('mail.from.address', $company->smtp_from_email ?? $company->smtp_username ?? $company->email);
            Config::set('mail.from.name', $company->smtp_from_name ?? $company->name);
            
            Log::info('Using company SMTP settings', [
                'company_id' => $company->id,
                'smtp_host' => $company->smtp_host,
            ]);
            
            return true;
        } else {
            // Fallback to user SMTP settings
            $user = $company->users()->first();
            if ($user && $user->smtp_host && $user->smtp_username && $user->smtp_password) {
                Config::set('mail.mailers.smtp.host', $user->smtp_host);
                Config::set('mail.mailers.smtp.port', $user->smtp_port ?? 587);
                Config::set('mail.mailers.smtp.username', $user->smtp_username);
                Config::set('mail.mailers.smtp.password', $user->smtp_password);
                Config::set('mail.mailers.smtp.encryption', $user->smtp_encryption ?? 'tls');
                Config::set('mail.from.address', $user->smtp_from_email ?? $user->email);
                Config::set('mail.from.name', $user->smtp_from_name ?? $user->name);
                
                Log::info('Using user SMTP settings as fallback', [
                    'company_id' => $company->id,
                    'user_id' => $user->id,
                    'smtp_host' => $user->smtp_host,
                ]);
                
                return true;
            }
        }
        
        Log::warning('No SMTP settings configured for company', [
            'company_id' => $company->id,
        ]);
        
        return false;
    }

    protected function applyCompanyReplyTo($mail, Company $company): void
    {
        if (!empty($company->email)) {
            $mail->replyTo($company->email, $company->name ?? null);
        }
    }

    protected function getRecipientEmail($document): ?string
    {
        return $document->email ?: $document->customer?->email;
    }

    protected function getRecipientPhone($document): ?string
    {
        return $document->phone ?: $document->customer?->phone;
    }

    /**
     * Process all automated reminders for all companies.
     */
    public function processAllReminders(): void
    {
        $companies = Company::where('is_active', true)->get();

        foreach ($companies as $company) {
            $settings = $company->getReminderSettings();
            
            if (!$settings->automation_enabled) {
                continue;
            }

            $this->processCompanyReminders($company, $settings);
        }
    }

    /**
     * Process reminders for a specific company.
     */
    public function processCompanyReminders(Company $company, ReminderSettings $settings): void
    {
        // Process overdue invoice reminders
        if ($settings->overdue_invoice_email_enabled || $settings->overdue_invoice_sms_enabled || $settings->overdue_invoice_whatsapp_enabled) {
            $this->processOverdueInvoiceReminders($company, $settings);
        }

        // Process expiring quote reminders
        if ($settings->expiring_quote_email_enabled || $settings->expiring_quote_sms_enabled || $settings->expiring_quote_whatsapp_enabled) {
            $this->processExpiringQuoteReminders($company, $settings);
        }
    }

    /**
     * Process overdue invoice reminders.
     */
    protected function processOverdueInvoiceReminders(Company $company, ReminderSettings $settings): void
    {
        $daysAfterDue = $settings->overdue_invoice_days_after_due;
        $frequencyDays = $settings->overdue_invoice_frequency_days;

        // Get invoices that are overdue by at least X days
        $overdueDate = Carbon::now()->subDays($daysAfterDue);
        
        $invoices = Invoice::where('company_id', $company->id)
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->where('due_date', '<=', $overdueDate)
            ->with('customer', 'company')
            ->get();

        foreach ($invoices as $invoice) {
            // Check if we should send a reminder based on frequency
            $lastReminder = ReminderLog::where('company_id', $company->id)
                ->where('reminder_type', 'overdue_invoice')
                ->where('remindable_type', Invoice::class)
                ->where('remindable_id', $invoice->id)
                ->where('status', 'sent')
                ->orderBy('sent_at', 'desc')
                ->first();

            // If there's a last reminder, check if enough days have passed
            if ($lastReminder) {
                $daysSinceLastReminder = Carbon::now()->diffInDays($lastReminder->sent_at);
                if ($daysSinceLastReminder < $frequencyDays) {
                    continue; // Skip this invoice, not enough time has passed
                }
            }

            // Check if invoice is still overdue
            if (!$invoice->isOverdue()) {
                continue;
            }

            // Send email reminder if enabled
            if ($settings->overdue_invoice_email_enabled && $this->getRecipientEmail($invoice)) {
                $this->sendOverdueInvoiceEmail($invoice, $settings);
            }

            // Send SMS reminder if enabled
            if ($settings->overdue_invoice_sms_enabled && $this->getRecipientPhone($invoice) && $this->smsService) {
                $this->sendOverdueInvoiceSMS($invoice, $settings);
            }

            // Send WhatsApp reminder if enabled
            if ($settings->overdue_invoice_whatsapp_enabled && $invoice->customer->phone) {
                // Get WhatsApp service for the invoice's company
                $whatsappService = $this->getWhatsAppService($invoice->company_id);
                
                if ($whatsappService) {
                    $this->sendOverdueInvoiceWhatsApp($invoice, $settings, $whatsappService);
                }
            }
        }
    }

    /**
     * Process expiring quote reminders.
     */
    protected function processExpiringQuoteReminders(Company $company, ReminderSettings $settings): void
    {
        $daysBefore = $settings->expiring_quote_days_before;
        $expiryDate = Carbon::now()->addDays($daysBefore);

        $quotes = Quote::where('company_id', $company->id)
            ->where('status', 'sent')
            ->whereDate('expiry_date', '<=', $expiryDate)
            ->whereDate('expiry_date', '>=', Carbon::now())
            ->with('customer', 'company')
            ->get();

        foreach ($quotes as $quote) {
            // Check if we've already sent a reminder for this quote
            $existingReminder = ReminderLog::where('company_id', $company->id)
                ->where('reminder_type', 'expiring_quote')
                ->where('remindable_type', Quote::class)
                ->where('remindable_id', $quote->id)
                ->where('status', 'sent')
                ->exists();

            if ($existingReminder) {
                continue; // Already sent a reminder
            }

            // Send email reminder if enabled
            if ($settings->expiring_quote_email_enabled && $this->getRecipientEmail($quote)) {
                $this->sendExpiringQuoteEmail($quote, $settings);
            }

            // Send SMS reminder if enabled
            if ($settings->expiring_quote_sms_enabled && $this->getRecipientPhone($quote) && $this->smsService) {
                $this->sendExpiringQuoteSMS($quote, $settings);
            }

            // Send WhatsApp reminder if enabled
            if ($settings->expiring_quote_whatsapp_enabled && $quote->customer->phone) {
                // Get WhatsApp service for the quote's company
                $whatsappService = $this->getWhatsAppService($quote->company_id);
                
                if ($whatsappService) {
                    $this->sendExpiringQuoteWhatsApp($quote, $settings, $whatsappService);
                }
            }
        }
    }

    /**
     * Send overdue invoice email reminder.
     */
    protected function sendOverdueInvoiceEmail(Invoice $invoice, ReminderSettings $settings): void
    {
        try {
            $recipientEmail = $this->getRecipientEmail($invoice);
            $template = $settings->getDefaultOverdueInvoiceEmailTemplate();
            $message = $this->replaceTemplateVariables($template, [
                'customer_name' => $invoice->customer->name,
                'invoice_number' => $invoice->invoice_number,
                'invoice_total' => 'R' . number_format($invoice->total, 2),
                'due_date' => $invoice->due_date->format('Y-m-d'),
                'company_name' => $invoice->company->name,
            ]);

            // Configure SMTP settings
            if (!$this->configureSmtp($invoice->company)) {
                throw new \Exception('SMTP settings not configured for company');
            }

            Mail::mailer('smtp')->raw($message, function ($mail) use ($invoice, $recipientEmail) {
                $mail->to($recipientEmail)
                    ->subject("Reminder: Invoice {$invoice->invoice_number} is Overdue");
                $this->applyCompanyReplyTo($mail, $invoice->company);
            });

            $this->logReminder($invoice->company, 'overdue_invoice', 'email', $invoice, $recipientEmail, null, $message, 'sent');

        } catch (\Exception $e) {
            Log::error('Failed to send overdue invoice email', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            $this->logReminder($invoice->company, 'overdue_invoice', 'email', $invoice, $recipientEmail ?? null, null, '', 'failed', $e->getMessage());
        }
    }

    /**
     * Send overdue invoice SMS reminder.
     */
    protected function sendOverdueInvoiceSMS(Invoice $invoice, ReminderSettings $settings): void
    {
        try {
            $recipientPhone = $this->getRecipientPhone($invoice);
            $template = $settings->getDefaultOverdueInvoiceSmsTemplate();
            $message = $this->replaceTemplateVariables($template, [
                'customer_name' => $invoice->customer->name,
                'invoice_number' => $invoice->invoice_number,
                'invoice_total' => 'R' . number_format($invoice->total, 2),
                'due_date' => $invoice->due_date->format('Y-m-d'),
                'company_name' => $invoice->company->name,
            ]);

            $result = $this->smsService->sendSMS($recipientPhone, $message);

            $this->logReminder(
                $invoice->company,
                'overdue_invoice',
                'sms',
                $invoice,
                null,
                $recipientPhone,
                $message,
                $result['success'] ? 'sent' : 'failed',
                $result['success'] ? null : $result['message']
            );

        } catch (\Exception $e) {
            Log::error('Failed to send overdue invoice SMS', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            $this->logReminder($invoice->company, 'overdue_invoice', 'sms', $invoice, null, $recipientPhone ?? null, '', 'failed', $e->getMessage());
        }
    }

    /**
     * Send overdue invoice WhatsApp reminder.
     */
    protected function sendOverdueInvoiceWhatsApp(Invoice $invoice, ReminderSettings $settings, WhatsAppService $whatsappService): void
    {
        try {

            // Get template name from settings (required for Meta WhatsApp)
            $templateName = $settings->overdue_invoice_whatsapp_template_name;
            if (empty($templateName)) {
                Log::error('WhatsApp template name not configured for overdue invoice', [
                    'invoice_id' => $invoice->id,
                ]);
                $this->logReminder($invoice->company, 'overdue_invoice', 'whatsapp', $invoice, null, $invoice->customer->phone ?? null, '', 'failed', 'WhatsApp template name not configured');
                return;
            }

            // Get selected variables from settings (JSON array of variable names)
            $selectedVariables = $settings->overdue_invoice_whatsapp_template_variables ?? [];

            // Available variables for this context
            $availableVariables = [
                'customer_name' => $invoice->customer->name,
                'invoice_number' => $invoice->invoice_number,
                'invoice_total' => 'R' . number_format($invoice->total, 2),
                'due_date' => $invoice->due_date->format('Y-m-d'),
                'company_name' => $invoice->company->name,
            ];

            // Filter to only include selected variables
            $parameters = [];
            foreach ($selectedVariables as $varName) {
                if (isset($availableVariables[$varName])) {
                    $parameters[$varName] = $availableVariables[$varName];
                }
            }

            $result = $whatsappService->sendMessage($invoice->customer->phone, $templateName, $parameters);

            $this->logReminder(
                $invoice->company,
                'overdue_invoice',
                'whatsapp',
                $invoice,
                null,
                $invoice->customer->phone,
                json_encode($parameters), // Log the parameters sent
                $result['success'] ? 'sent' : 'failed',
                $result['success'] ? null : ($result['message'] ?? 'Unknown error')
            );

        } catch (\Exception $e) {
            Log::error('Failed to send overdue invoice WhatsApp', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            $this->logReminder($invoice->company, 'overdue_invoice', 'whatsapp', $invoice, null, $invoice->customer->phone ?? null, '', 'failed', $e->getMessage());
        }
    }

    /**
     * Send expiring quote email reminder.
     */
    protected function sendExpiringQuoteEmail(Quote $quote, ReminderSettings $settings): void
    {
        try {
            $recipientEmail = $this->getRecipientEmail($quote);
            $template = $settings->getDefaultExpiringQuoteEmailTemplate();
            $message = $this->replaceTemplateVariables($template, [
                'customer_name' => $quote->customer->name,
                'quote_number' => $quote->quote_number,
                'quote_total' => 'R' . number_format($quote->total, 2),
                'expiry_date' => $quote->expiry_date->format('Y-m-d'),
                'company_name' => $quote->company->name,
            ]);

            if (!$this->configureSmtp($quote->company)) {
                throw new \Exception('SMTP settings not configured for company');
            }

            Mail::mailer('smtp')->raw($message, function ($mail) use ($quote, $recipientEmail) {
                $mail->to($recipientEmail)
                    ->subject("Reminder: Quote {$quote->quote_number} Expires Soon");
                $this->applyCompanyReplyTo($mail, $quote->company);
            });

            $this->logReminder($quote->company, 'expiring_quote', 'email', $quote, $recipientEmail, null, $message, 'sent');

        } catch (\Exception $e) {
            Log::error('Failed to send expiring quote email', [
                'quote_id' => $quote->id,
                'error' => $e->getMessage(),
            ]);
            $this->logReminder($quote->company, 'expiring_quote', 'email', $quote, $recipientEmail ?? null, null, '', 'failed', $e->getMessage());
        }
    }

    /**
     * Send expiring quote SMS reminder.
     */
    protected function sendExpiringQuoteSMS(Quote $quote, ReminderSettings $settings): void
    {
        try {
            $recipientPhone = $this->getRecipientPhone($quote);
            $template = $settings->getDefaultExpiringQuoteSmsTemplate();
            $message = $this->replaceTemplateVariables($template, [
                'customer_name' => $quote->customer->name,
                'quote_number' => $quote->quote_number,
                'quote_total' => 'R' . number_format($quote->total, 2),
                'expiry_date' => $quote->expiry_date->format('Y-m-d'),
                'company_name' => $quote->company->name,
            ]);

            $result = $this->smsService->sendSMS($recipientPhone, $message);

            $this->logReminder(
                $quote->company,
                'expiring_quote',
                'sms',
                $quote,
                null,
                $recipientPhone,
                $message,
                $result['success'] ? 'sent' : 'failed',
                $result['success'] ? null : $result['message']
            );

        } catch (\Exception $e) {
            Log::error('Failed to send expiring quote SMS', [
                'quote_id' => $quote->id,
                'error' => $e->getMessage(),
            ]);
            $this->logReminder($quote->company, 'expiring_quote', 'sms', $quote, null, $recipientPhone ?? null, '', 'failed', $e->getMessage());
        }
    }

    /**
     * Send expiring quote WhatsApp reminder.
     */
    protected function sendExpiringQuoteWhatsApp(Quote $quote, ReminderSettings $settings, WhatsAppService $whatsappService): void
    {
        try {

            // Get template name from settings (required for Meta WhatsApp)
            $templateName = $settings->expiring_quote_whatsapp_template_name;
            if (empty($templateName)) {
                Log::error('WhatsApp template name not configured for expiring quote', [
                    'quote_id' => $quote->id,
                ]);
                $this->logReminder($quote->company, 'expiring_quote', 'whatsapp', $quote, null, $quote->customer->phone ?? null, '', 'failed', 'WhatsApp template name not configured');
                return;
            }

            // Get selected variables from settings (JSON array of variable names)
            $selectedVariables = $settings->expiring_quote_whatsapp_template_variables ?? [];

            // Available variables for this context
            $availableVariables = [
                'customer_name' => $quote->customer->name,
                'quote_number' => $quote->quote_number,
                'quote_total' => 'R' . number_format($quote->total, 2),
                'expiry_date' => $quote->expiry_date->format('Y-m-d'),
                'company_name' => $quote->company->name,
            ];

            // Filter to only include selected variables
            $parameters = [];
            foreach ($selectedVariables as $varName) {
                if (isset($availableVariables[$varName])) {
                    $parameters[$varName] = $availableVariables[$varName];
                }
            }

            $result = $whatsappService->sendMessage($quote->customer->phone, $templateName, $parameters);

            $this->logReminder(
                $quote->company,
                'expiring_quote',
                'whatsapp',
                $quote,
                null,
                $quote->customer->phone,
                json_encode($parameters), // Log the parameters sent
                $result['success'] ? 'sent' : 'failed',
                $result['success'] ? null : ($result['message'] ?? 'Unknown error')
            );

        } catch (\Exception $e) {
            Log::error('Failed to send expiring quote WhatsApp', [
                'quote_id' => $quote->id,
                'error' => $e->getMessage(),
            ]);
            $this->logReminder($quote->company, 'expiring_quote', 'whatsapp', $quote, null, $quote->customer->phone ?? null, '', 'failed', $e->getMessage());
        }
    }

    /**
     * Replace template variables with actual values.
     */
    protected function replaceTemplateVariables(string $template, array $variables): string
    {
        $message = $template;
        foreach ($variables as $key => $value) {
            $message = str_replace("{{{$key}}}", $value, $message);
        }
        return $message;
    }

    /**
     * Send invoice created confirmation (triggered when invoice is created).
     */
    public function sendInvoiceCreatedConfirmation(Invoice $invoice): void
    {
        $settings = $invoice->company->getReminderSettings();
        
        if (!$settings->automation_enabled) {
            return;
        }

        $invoice->load('customer');

        // Send email confirmation if enabled
        if ($settings->invoice_created_email_enabled && $this->getRecipientEmail($invoice)) {
            $this->sendInvoiceCreatedEmail($invoice, $settings);
        }

        // Send SMS confirmation if enabled
        if ($settings->invoice_created_sms_enabled && $this->getRecipientPhone($invoice) && $this->smsService) {
            $this->sendInvoiceCreatedSMS($invoice, $settings);
        }

        // Send WhatsApp confirmation if enabled
        if ($settings->invoice_created_whatsapp_enabled && $invoice->customer->phone) {
            // Get WhatsApp service for the invoice's company
            $whatsappService = $this->getWhatsAppService($invoice->company_id);
            
            if ($whatsappService) {
                $this->sendInvoiceCreatedWhatsApp($invoice, $settings, $whatsappService);
            }
        }
    }

    /**
     * Send quote created confirmation (triggered when quote is created).
     */
    public function sendQuoteCreatedConfirmation(Quote $quote): void
    {
        $settings = $quote->company->getReminderSettings();
        
        if (!$settings->automation_enabled) {
            return;
        }

        $quote->load('customer');

        // Send email confirmation if enabled
        if ($settings->quote_created_email_enabled && $this->getRecipientEmail($quote)) {
            $this->sendQuoteCreatedEmail($quote, $settings);
        }

        // Send SMS confirmation if enabled
        if ($settings->quote_created_sms_enabled && $this->getRecipientPhone($quote) && $this->smsService) {
            $this->sendQuoteCreatedSMS($quote, $settings);
        }

        // Send WhatsApp confirmation if enabled
        if ($settings->quote_created_whatsapp_enabled && $quote->customer->phone) {
            // Get WhatsApp service for the quote's company
            $whatsappService = $this->getWhatsAppService($quote->company_id);
            
            if ($whatsappService) {
                $this->sendQuoteCreatedWhatsApp($quote, $settings, $whatsappService);
            }
        }
    }

    /**
     * Send payment received confirmation (triggered when payment is created).
     */
    public function sendPaymentReceivedConfirmation(Payment $payment): void
    {
        $invoice = $payment->invoice;
        $invoice->load('customer', 'company');
        $settings = $invoice->company->getReminderSettings();
        
        if (!$settings->automation_enabled) {
            Log::info('Payment received confirmation skipped - automation disabled', [
                'payment_id' => $payment->id,
                'company_id' => $invoice->company->id,
            ]);
            return;
        }

        // Ensure SMS service is initialized (in case it wasn't in constructor)
        if (!$this->smsService) {
            $this->smsService = $this->getSMSService();
        }

        Log::info('Processing payment received confirmation', [
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer->id,
            'email_enabled' => $settings->payment_received_email_enabled,
            'sms_enabled' => $settings->payment_received_sms_enabled,
            'customer_email' => $this->getRecipientEmail($invoice),
            'customer_phone' => $this->getRecipientPhone($invoice),
            'sms_service_available' => $this->smsService !== null,
        ]);

        // Send email confirmation if enabled
        if ($settings->payment_received_email_enabled && $this->getRecipientEmail($invoice)) {
            $this->sendPaymentReceivedEmail($payment, $settings);
        }

        // Send SMS confirmation if enabled
        if ($settings->payment_received_sms_enabled) {
            if (!$this->getRecipientPhone($invoice)) {
                Log::warning('Payment received SMS skipped - customer has no phone', [
                    'payment_id' => $payment->id,
                    'customer_id' => $invoice->customer->id,
                ]);
            } elseif (!$this->smsService) {
                Log::warning('Payment received SMS skipped - SMS service not available after initialization', [
                    'payment_id' => $payment->id,
                    'sms_settings_check' => SMSSettings::isConfigured(),
                ]);
            } else {
                Log::info('Calling sendPaymentReceivedSMS', [
                    'payment_id' => $payment->id,
                    'customer_phone' => $this->getRecipientPhone($invoice),
                ]);
                $this->sendPaymentReceivedSMS($payment, $settings);
            }
        }

        // Send WhatsApp confirmation if enabled
        if ($settings->payment_received_whatsapp_enabled && $invoice->customer->phone) {
            // Get WhatsApp service for the invoice's company
            $whatsappService = $this->getWhatsAppService($invoice->company_id);
            
            if ($whatsappService) {
                $this->sendPaymentReceivedWhatsApp($payment, $settings, $whatsappService);
            }
        }
    }

    /**
     * Send invoice created email confirmation.
     */
    protected function sendInvoiceCreatedEmail(Invoice $invoice, ReminderSettings $settings): void
    {
        try {
            $recipientEmail = $this->getRecipientEmail($invoice);
            $template = $settings->invoice_created_email_template ?? 
                "Dear {{customer_name}},\n\n" .
                "A new invoice {{invoice_number}} has been created for you.\n\n" .
                "Invoice Total: {{invoice_total}}\n" .
                "Due Date: {{due_date}}\n\n" .
                "Thank you,\n" .
                "{{company_name}}";

            $message = $this->replaceTemplateVariables($template, [
                'customer_name' => $invoice->customer->name,
                'invoice_number' => $invoice->invoice_number,
                'invoice_total' => 'R' . number_format($invoice->total, 2),
                'due_date' => $invoice->due_date->format('Y-m-d'),
                'company_name' => $invoice->company->name,
            ]);

            $this->configureSmtp($invoice->company);

            Mail::mailer('smtp')->raw($message, function ($mail) use ($invoice, $recipientEmail) {
                $mail->to($recipientEmail)
                    ->subject("New Invoice {$invoice->invoice_number}");
                $this->applyCompanyReplyTo($mail, $invoice->company);
            });

            $this->logReminder($invoice->company, 'invoice_created', 'email', $invoice, $recipientEmail, null, $message, 'sent');

        } catch (\Exception $e) {
            Log::error('Failed to send invoice created email', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            $this->logReminder($invoice->company, 'invoice_created', 'email', $invoice, $recipientEmail ?? null, null, '', 'failed', $e->getMessage());
        }
    }

    /**
     * Send invoice created SMS confirmation.
     */
    protected function sendInvoiceCreatedSMS(Invoice $invoice, ReminderSettings $settings): void
    {
        try {
            $recipientPhone = $this->getRecipientPhone($invoice);
            // Ensure SMS service is available
            if (!$this->smsService) {
                $this->smsService = $this->getSMSService();
            }
            
            if (!$this->smsService) {
                Log::error('SMS service not available for invoice created SMS', [
                    'invoice_id' => $invoice->id,
                ]);
                $this->logReminder($invoice->company, 'invoice_created', 'sms', $invoice, null, $recipientPhone ?? null, '', 'failed', 'SMS service not available');
                return;
            }

            $template = $settings->invoice_created_sms_template ?? 
                "Hi {{customer_name}}, New invoice {{invoice_number}} ({{invoice_total}}) created. Due: {{due_date}}. {{company_name}}";

            $message = $this->replaceTemplateVariables($template, [
                'customer_name' => $invoice->customer->name,
                'invoice_number' => $invoice->invoice_number,
                'invoice_total' => 'R' . number_format($invoice->total, 2),
                'due_date' => $invoice->due_date->format('Y-m-d'),
                'company_name' => $invoice->company->name,
            ]);

            $result = $this->smsService->sendSMS($recipientPhone, $message);

            $this->logReminder(
                $invoice->company,
                'invoice_created',
                'sms',
                $invoice,
                null,
                $recipientPhone,
                $message,
                $result['success'] ? 'sent' : 'failed',
                $result['success'] ? null : $result['message']
            );

        } catch (\Exception $e) {
            Log::error('Failed to send invoice created SMS', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            $this->logReminder($invoice->company, 'invoice_created', 'sms', $invoice, null, $recipientPhone ?? null, '', 'failed', $e->getMessage());
        }
    }

    /**
     * Send invoice created WhatsApp confirmation.
     */
    protected function sendInvoiceCreatedWhatsApp(Invoice $invoice, ReminderSettings $settings, WhatsAppService $whatsappService): void
    {
        try {

            // Get template name from settings (required for Meta WhatsApp)
            $templateName = $settings->invoice_created_whatsapp_template_name;
            if (empty($templateName)) {
                Log::error('WhatsApp template name not configured for invoice created', [
                    'invoice_id' => $invoice->id,
                ]);
                $this->logReminder($invoice->company, 'invoice_created', 'whatsapp', $invoice, null, $invoice->customer->phone ?? null, '', 'failed', 'WhatsApp template name not configured');
                return;
            }

            // Get selected variables from settings (JSON array of variable names)
            $selectedVariables = $settings->invoice_created_whatsapp_template_variables ?? [];
            if (!is_array($selectedVariables)) {
                $selectedVariables = json_decode($selectedVariables, true) ?? [];
            }

            // Build available variables
            $availableVariables = [
                'customer_name' => $invoice->customer->name,
                'invoice_number' => $invoice->invoice_number,
                'invoice_total' => 'R' . number_format($invoice->total, 2),
                'due_date' => $invoice->due_date->format('Y-m-d'),
                'company_name' => $invoice->company->name,
            ];

            // Filter to only include selected variables
            $parameters = [];
            foreach ($selectedVariables as $varName) {
                if (isset($availableVariables[$varName])) {
                    $parameters[$varName] = $availableVariables[$varName];
                }
            }

            $result = $whatsappService->sendMessage($invoice->customer->phone, $templateName, $parameters);

            $this->logReminder(
                $invoice->company,
                'invoice_created',
                'whatsapp',
                $invoice,
                null,
                $invoice->customer->phone,
                json_encode($parameters),
                $result['success'] ? 'sent' : 'failed',
                $result['success'] ? null : ($result['message'] ?? 'Unknown error')
            );

        } catch (\Exception $e) {
            Log::error('Failed to send invoice created WhatsApp', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            $this->logReminder($invoice->company, 'invoice_created', 'whatsapp', $invoice, null, $invoice->customer->phone ?? null, '', 'failed', $e->getMessage());
        }
    }

    /**
     * Send quote created email confirmation.
     */
    protected function sendQuoteCreatedEmail(Quote $quote, ReminderSettings $settings): void
    {
        try {
            $recipientEmail = $this->getRecipientEmail($quote);
            $template = $settings->quote_created_email_template ?? 
                "Dear {{customer_name}},\n\n" .
                "A new quote {{quote_number}} has been created for you.\n\n" .
                "Quote Total: {{quote_total}}\n" .
                "Expiry Date: {{expiry_date}}\n\n" .
                "Thank you,\n" .
                "{{company_name}}";

            $message = $this->replaceTemplateVariables($template, [
                'customer_name' => $quote->customer->name,
                'quote_number' => $quote->quote_number,
                'quote_total' => 'R' . number_format($quote->total, 2),
                'expiry_date' => $quote->expiry_date ? $quote->expiry_date->format('Y-m-d') : 'N/A',
                'company_name' => $quote->company->name,
            ]);

            if (!$this->configureSmtp($quote->company)) {
                throw new \Exception('SMTP settings not configured for company');
            }

            Mail::mailer('smtp')->raw($message, function ($mail) use ($quote, $recipientEmail) {
                $mail->to($recipientEmail)
                    ->subject("New Quote {$quote->quote_number}");
                $this->applyCompanyReplyTo($mail, $quote->company);
            });

            $this->logReminder($quote->company, 'quote_created', 'email', $quote, $recipientEmail, null, $message, 'sent');

        } catch (\Exception $e) {
            Log::error('Failed to send quote created email', [
                'quote_id' => $quote->id,
                'error' => $e->getMessage(),
            ]);
            $this->logReminder($quote->company, 'quote_created', 'email', $quote, $recipientEmail ?? null, null, '', 'failed', $e->getMessage());
        }
    }

    /**
     * Send quote created SMS confirmation.
     */
    protected function sendQuoteCreatedSMS(Quote $quote, ReminderSettings $settings): void
    {
        try {
            $recipientPhone = $this->getRecipientPhone($quote);
            // Ensure SMS service is available
            if (!$this->smsService) {
                $this->smsService = $this->getSMSService();
            }
            
            if (!$this->smsService) {
                Log::error('SMS service not available for quote created SMS', [
                    'quote_id' => $quote->id,
                ]);
                $this->logReminder($quote->company, 'quote_created', 'sms', $quote, null, $recipientPhone ?? null, '', 'failed', 'SMS service not available');
                return;
            }

            $template = $settings->quote_created_sms_template ?? 
                "Hi {{customer_name}}, New quote {{quote_number}} ({{quote_total}}) created. Expires: {{expiry_date}}. {{company_name}}";

            $message = $this->replaceTemplateVariables($template, [
                'customer_name' => $quote->customer->name,
                'quote_number' => $quote->quote_number,
                'quote_total' => 'R' . number_format($quote->total, 2),
                'expiry_date' => $quote->expiry_date ? $quote->expiry_date->format('Y-m-d') : 'N/A',
                'company_name' => $quote->company->name,
            ]);

            $result = $this->smsService->sendSMS($recipientPhone, $message);

            $this->logReminder(
                $quote->company,
                'quote_created',
                'sms',
                $quote,
                null,
                $recipientPhone,
                $message,
                $result['success'] ? 'sent' : 'failed',
                $result['success'] ? null : $result['message']
            );

        } catch (\Exception $e) {
            Log::error('Failed to send quote created SMS', [
                'quote_id' => $quote->id,
                'error' => $e->getMessage(),
            ]);
            $this->logReminder($quote->company, 'quote_created', 'sms', $quote, null, $recipientPhone ?? null, '', 'failed', $e->getMessage());
        }
    }

    /**
     * Send quote created WhatsApp confirmation.
     */
    protected function sendQuoteCreatedWhatsApp(Quote $quote, ReminderSettings $settings, WhatsAppService $whatsappService): void
    {
        try {

            // Get template name from settings (required for Meta WhatsApp)
            $templateName = $settings->quote_created_whatsapp_template_name;
            if (empty($templateName)) {
                Log::error('WhatsApp template name not configured for quote created', [
                    'quote_id' => $quote->id,
                ]);
                $this->logReminder($quote->company, 'quote_created', 'whatsapp', $quote, null, $quote->customer->phone ?? null, '', 'failed', 'WhatsApp template name not configured');
                return;
            }

            // Get selected variables from settings (JSON array of variable names)
            $selectedVariables = $settings->quote_created_whatsapp_template_variables ?? [];

            // Available variables for this context
            $availableVariables = [
                'customer_name' => $quote->customer->name,
                'quote_number' => $quote->quote_number,
                'quote_total' => 'R' . number_format($quote->total, 2),
                'expiry_date' => $quote->expiry_date ? $quote->expiry_date->format('Y-m-d') : 'N/A',
                'company_name' => $quote->company->name,
            ];

            // Filter to only include selected variables
            $parameters = [];
            foreach ($selectedVariables as $varName) {
                if (isset($availableVariables[$varName])) {
                    $parameters[$varName] = $availableVariables[$varName];
                }
            }

            $result = $whatsappService->sendMessage($quote->customer->phone, $templateName, $parameters);

            $this->logReminder(
                $quote->company,
                'quote_created',
                'whatsapp',
                $quote,
                null,
                $quote->customer->phone,
                json_encode($parameters), // Log the parameters sent
                $result['success'] ? 'sent' : 'failed',
                $result['success'] ? null : ($result['message'] ?? 'Unknown error')
            );

        } catch (\Exception $e) {
            Log::error('Failed to send quote created WhatsApp', [
                'quote_id' => $quote->id,
                'error' => $e->getMessage(),
            ]);
            $this->logReminder($quote->company, 'quote_created', 'whatsapp', $quote, null, $quote->customer->phone ?? null, '', 'failed', $e->getMessage());
        }
    }

    /**
     * Send payment received email confirmation.
     */
    protected function sendPaymentReceivedEmail(Payment $payment, ReminderSettings $settings): void
    {
        try {
            $invoice = $payment->invoice;
            $invoice->load('customer', 'company');
            $recipientEmail = $this->getRecipientEmail($invoice);

            $template = $settings->payment_received_email_template ?? 
                "Dear {{customer_name}},\n\n" .
                "We have received your payment of {{payment_amount}} for invoice {{invoice_number}}.\n\n" .
                "Payment Date: {{payment_date}}\n" .
                "Payment Method: {{payment_method}}\n\n" .
                "Thank you for your payment.\n\n" .
                "{{company_name}}";

            $message = $this->replaceTemplateVariables($template, [
                'customer_name' => $invoice->customer->name,
                'invoice_number' => $invoice->invoice_number,
                'payment_amount' => 'R' . number_format($payment->amount, 2),
                'payment_date' => $payment->payment_date->format('Y-m-d'),
                'payment_method' => ucfirst($payment->payment_method),
                'company_name' => $invoice->company->name,
            ]);

            $this->configureSmtp($invoice->company);

            Mail::mailer('smtp')->raw($message, function ($mail) use ($invoice, $recipientEmail) {
                $mail->to($recipientEmail)
                    ->subject("Payment Received - Invoice {$invoice->invoice_number}");
                $this->applyCompanyReplyTo($mail, $invoice->company);
            });

            $this->logReminder($invoice->company, 'payment_received', 'email', $payment, $recipientEmail, null, $message, 'sent');

        } catch (\Exception $e) {
            Log::error('Failed to send payment received email', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            $invoice = $payment->invoice;
            $this->logReminder($invoice->company, 'payment_received', 'email', $payment, $this->getRecipientEmail($invoice) ?? null, null, '', 'failed', $e->getMessage());
        }
    }

    /**
     * Send payment received SMS confirmation.
     */
    protected function sendPaymentReceivedSMS(Payment $payment, ReminderSettings $settings): void
    {
        try {
            $invoice = $payment->invoice;
            $invoice->load('customer', 'company');
            $recipientPhone = $this->getRecipientPhone($invoice);

            Log::info('Attempting to send payment received SMS', [
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'customer_phone' => $recipientPhone,
                'sms_service_available' => $this->smsService !== null,
            ]);

            // Double-check SMS service (should already be checked in calling method)
            if (!$this->smsService) {
                Log::error('SMS service is null in sendPaymentReceivedSMS', [
                    'payment_id' => $payment->id,
                ]);
                $this->logReminder($invoice->company, 'payment_received', 'sms', $payment, null, $recipientPhone ?? null, '', 'failed', 'SMS service not available');
                return;
            }

            if (!$recipientPhone) {
                Log::error('Customer phone is empty in sendPaymentReceivedSMS', [
                    'payment_id' => $payment->id,
                    'customer_id' => $invoice->customer->id,
                ]);
                $this->logReminder($invoice->company, 'payment_received', 'sms', $payment, null, null, '', 'failed', 'Customer has no phone number');
                return;
            }

            $template = $settings->payment_received_sms_template ?? 
                "{{company_name}}: Thank you. Ref: {{invoice_number}}";

            $message = $this->replaceTemplateVariables($template, [
                'customer_name' => $invoice->customer->name,
                'invoice_number' => $invoice->invoice_number,
                'payment_amount' => 'R' . number_format($payment->amount, 2),
                'payment_date' => $payment->payment_date->format('Y-m-d'),
                'payment_method' => ucfirst($payment->payment_method),
                'company_name' => $invoice->company->name,
            ]);

            // Ensure SMS service is available (re-check in case it wasn't initialized)
            if (!$this->smsService) {
                $this->smsService = $this->getSMSService();
            }

            if (!$this->smsService) {
                Log::error('SMS service is still null after re-initialization', [
                    'payment_id' => $payment->id,
                ]);
                $this->logReminder($invoice->company, 'payment_received', 'sms', $payment, null, $recipientPhone ?? null, '', 'failed', 'SMS service not available after re-initialization');
                return;
            }

            Log::info('Calling SMS service to send payment received SMS', [
                'payment_id' => $payment->id,
                'phone' => $recipientPhone,
                'message_length' => strlen($message),
            ]);

            $result = $this->smsService->sendSMS($recipientPhone, $message);

            Log::info('SMS service response for payment received', [
                'payment_id' => $payment->id,
                'success' => $result['success'] ?? false,
                'message' => $result['message'] ?? 'No message',
                'full_result' => $result,
            ]);

            if (!isset($result['success'])) {
                Log::error('SMS service returned invalid response format', [
                    'payment_id' => $payment->id,
                    'result' => $result,
                ]);
                $result['success'] = false;
                $result['message'] = 'Invalid response from SMS service';
            }

            $this->logReminder(
                $invoice->company,
                'payment_received',
                'sms',
                $payment,
                null,
                $recipientPhone,
                $message,
                $result['success'] ? 'sent' : 'failed',
                $result['success'] ? null : ($result['message'] ?? 'Unknown error')
            );

            if (!$result['success']) {
                Log::error('Payment received SMS failed', [
                    'payment_id' => $payment->id,
                    'error' => $result['message'] ?? 'Unknown error',
                    'result' => $result,
                ]);
            } else {
                Log::info('Payment received SMS sent successfully', [
                    'payment_id' => $payment->id,
                    'phone' => $invoice->customer->phone,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to send payment received SMS', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            $invoice = $payment->invoice;
            $this->logReminder($invoice->company, 'payment_received', 'sms', $payment, null, $invoice->customer->phone ?? null, '', 'failed', $e->getMessage());
        }
    }

    /**
     * Send payment received WhatsApp confirmation.
     */
    protected function sendPaymentReceivedWhatsApp(Payment $payment, ReminderSettings $settings, WhatsAppService $whatsappService): void
    {
        try {
            $invoice = $payment->invoice;
            $invoice->load('customer', 'company');

            if (!$invoice->customer->phone) {
                Log::error('Customer phone is empty for payment received WhatsApp', [
                    'payment_id' => $payment->id,
                    'customer_id' => $invoice->customer->id,
                ]);
                $this->logReminder($invoice->company, 'payment_received', 'whatsapp', $payment, null, null, '', 'failed', 'Customer has no phone number');
                return;
            }

            // Get template name from settings (required for Meta WhatsApp)
            $templateName = $settings->payment_received_whatsapp_template_name;
            if (empty($templateName)) {
                Log::error('WhatsApp template name not configured for payment received', [
                    'payment_id' => $payment->id,
                ]);
                $this->logReminder($invoice->company, 'payment_received', 'whatsapp', $payment, null, $invoice->customer->phone ?? null, '', 'failed', 'WhatsApp template name not configured');
                return;
            }

            // Get selected variables from settings (JSON array of variable names)
            $selectedVariables = $settings->payment_received_whatsapp_template_variables ?? [];

            // Available variables for this context
            $availableVariables = [
                'customer_name' => $invoice->customer->name,
                'invoice_number' => $invoice->invoice_number,
                'payment_amount' => 'R' . number_format($payment->amount, 2),
                'payment_date' => $payment->payment_date->format('Y-m-d'),
                'payment_method' => ucfirst($payment->payment_method),
                'company_name' => $invoice->company->name,
            ];

            // Filter to only include selected variables
            $parameters = [];
            foreach ($selectedVariables as $varName) {
                if (isset($availableVariables[$varName])) {
                    $parameters[$varName] = $availableVariables[$varName];
                }
            }

            $result = $whatsappService->sendMessage($invoice->customer->phone, $templateName, $parameters);

            $this->logReminder(
                $invoice->company,
                'payment_received',
                'whatsapp',
                $payment,
                null,
                $invoice->customer->phone,
                json_encode($parameters), // Log the parameters sent
                $result['success'] ? 'sent' : 'failed',
                $result['success'] ? null : ($result['message'] ?? 'Unknown error')
            );

        } catch (\Exception $e) {
            Log::error('Failed to send payment received WhatsApp', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            $invoice = $payment->invoice;
            $this->logReminder($invoice->company, 'payment_received', 'whatsapp', $payment, null, $invoice->customer->phone ?? null, '', 'failed', $e->getMessage());
        }
    }

    /**
     * Log a reminder attempt.
     */
    protected function logReminder(
        Company $company,
        string $reminderType,
        string $channel,
        $remindable,
        ?string $email,
        ?string $phone,
        string $message,
        string $status,
        ?string $errorMessage = null
    ): void {
        ReminderLog::create([
            'company_id' => $company->id,
            'reminder_type' => $reminderType,
            'channel' => $channel,
            'remindable_type' => get_class($remindable),
            'remindable_id' => $remindable->id,
            'recipient_email' => $email,
            'recipient_phone' => $phone,
            'message_sent' => $message,
            'status' => $status,
            'error_message' => $errorMessage,
            'sent_at' => now(),
        ]);
    }

    /**
     * Send jobcard created confirmation (triggered when jobcard is created).
     */
    public function sendJobcardCreatedConfirmation(Jobcard $jobcard): void
    {
        $settings = $jobcard->company->getReminderSettings();
        
        if (!$settings->automation_enabled) {
            return;
        }

        $jobcard->load('customer', 'company');

        // Send email confirmation if enabled
        if ($settings->jobcard_created_email_enabled && $this->getRecipientEmail($jobcard)) {
            $this->sendJobcardCreatedEmail($jobcard, $settings);
        }

        // Send SMS confirmation if enabled
        if ($settings->jobcard_created_sms_enabled) {
            // Ensure SMS service is initialized
            if (!$this->smsService) {
                $this->smsService = $this->getSMSService();
            }
            
            if (!$this->getRecipientPhone($jobcard)) {
                Log::warning('Jobcard created SMS skipped - customer has no phone', [
                    'jobcard_id' => $jobcard->id,
                    'customer_id' => $jobcard->customer->id,
                ]);
            } elseif (!$this->smsService) {
                Log::warning('Jobcard created SMS skipped - SMS service not available', [
                    'jobcard_id' => $jobcard->id,
                ]);
            } else {
                $this->sendJobcardCreatedSMS($jobcard, $settings);
            }
        }

        // Send WhatsApp confirmation if enabled
        if ($settings->jobcard_created_whatsapp_enabled && $jobcard->customer->phone) {
            // Get WhatsApp service for the jobcard's company
            $whatsappService = $this->getWhatsAppService($jobcard->company_id);
            
            if ($whatsappService) {
                $this->sendJobcardCreatedWhatsApp($jobcard, $settings, $whatsappService);
            }
        }
    }

    /**
     * Send jobcard status updated confirmation (triggered when jobcard status changes).
     */
    public function sendJobcardStatusUpdatedConfirmation(Jobcard $jobcard, string $oldStatus): void
    {
        $settings = $jobcard->company->getReminderSettings();
        
        if (!$settings->automation_enabled) {
            return;
        }

        $jobcard->load('customer', 'company');

        // Send email confirmation if enabled
        if ($settings->jobcard_status_updated_email_enabled && $this->getRecipientEmail($jobcard)) {
            $this->sendJobcardStatusUpdatedEmail($jobcard, $settings, $oldStatus);
        }

        // Send SMS confirmation if enabled
        if ($settings->jobcard_status_updated_sms_enabled) {
            // Ensure SMS service is initialized
            if (!$this->smsService) {
                $this->smsService = $this->getSMSService();
            }
            
            if (!$this->getRecipientPhone($jobcard)) {
                Log::warning('Jobcard status updated SMS skipped - customer has no phone', [
                    'jobcard_id' => $jobcard->id,
                    'customer_id' => $jobcard->customer->id,
                ]);
            } elseif (!$this->smsService) {
                Log::warning('Jobcard status updated SMS skipped - SMS service not available', [
                    'jobcard_id' => $jobcard->id,
                ]);
            } else {
                $this->sendJobcardStatusUpdatedSMS($jobcard, $settings, $oldStatus);
            }
        }

        // Send WhatsApp confirmation if enabled
        if ($settings->jobcard_status_updated_whatsapp_enabled && $jobcard->customer->phone) {
            // Get WhatsApp service for the jobcard's company
            $whatsappService = $this->getWhatsAppService($jobcard->company_id);
            
            if ($whatsappService) {
                $this->sendJobcardStatusUpdatedWhatsApp($jobcard, $settings, $oldStatus, $whatsappService);
            }
        }
    }

    /**
     * Send jobcard created email confirmation.
     */
    protected function sendJobcardCreatedEmail(Jobcard $jobcard, ReminderSettings $settings): void
    {
        try {
            $recipientEmail = $this->getRecipientEmail($jobcard);
            $template = $settings->jobcard_created_email_template ?? 
                "Dear {{customer_name}},\n\n" .
                "A new jobcard {{job_number}} has been created for you.\n\n" .
                "Title: {{jobcard_title}}\n" .
                "Status: {{status}}\n" .
                "Due Date: {{due_date}}\n\n" .
                "Thank you,\n" .
                "{{company_name}}";

            $message = $this->replaceTemplateVariables($template, [
                'customer_name' => $jobcard->customer->name,
                'job_number' => $jobcard->job_number,
                'jobcard_title' => $jobcard->title,
                'status' => ucfirst(str_replace('_', ' ', $jobcard->status)),
                'due_date' => $jobcard->due_date ? $jobcard->due_date->format('Y-m-d') : 'N/A',
                'company_name' => $jobcard->company->name,
            ]);

            if (!$this->configureSmtp($jobcard->company)) {
                throw new \Exception('SMTP settings not configured for company');
            }

            Mail::mailer('smtp')->raw($message, function ($mail) use ($jobcard, $recipientEmail) {
                $mail->to($recipientEmail)
                    ->subject("New Jobcard {$jobcard->job_number}");
                $this->applyCompanyReplyTo($mail, $jobcard->company);
            });

            $this->logReminder($jobcard->company, 'jobcard_created', 'email', $jobcard, $recipientEmail, null, $message, 'sent');

        } catch (\Exception $e) {
            Log::error('Failed to send jobcard created email', [
                'jobcard_id' => $jobcard->id,
                'error' => $e->getMessage(),
            ]);
            $this->logReminder($jobcard->company, 'jobcard_created', 'email', $jobcard, $recipientEmail ?? null, null, '', 'failed', $e->getMessage());
        }
    }

    /**
     * Send jobcard created SMS confirmation.
     */
    protected function sendJobcardCreatedSMS(Jobcard $jobcard, ReminderSettings $settings): void
    {
        try {
            $recipientPhone = $this->getRecipientPhone($jobcard);
            // Ensure SMS service is available
            if (!$this->smsService) {
                $this->smsService = $this->getSMSService();
            }
            
            if (!$this->smsService) {
                Log::error('SMS service not available for jobcard created SMS', [
                    'jobcard_id' => $jobcard->id,
                ]);
                $this->logReminder($jobcard->company, 'jobcard_created', 'sms', $jobcard, null, $recipientPhone ?? null, '', 'failed', 'SMS service not available');
                return;
            }

            $template = $settings->jobcard_created_sms_template ?? 
                "Hi {{customer_name}}, New jobcard {{job_number}} created. Status: {{status}}. {{company_name}}";

            $message = $this->replaceTemplateVariables($template, [
                'customer_name' => $jobcard->customer->name,
                'job_number' => $jobcard->job_number,
                'jobcard_title' => $jobcard->title,
                'status' => ucfirst(str_replace('_', ' ', $jobcard->status)),
                'due_date' => $jobcard->due_date ? $jobcard->due_date->format('Y-m-d') : 'N/A',
                'company_name' => $jobcard->company->name,
            ]);

            $result = $this->smsService->sendSMS($recipientPhone, $message);

            $this->logReminder(
                $jobcard->company,
                'jobcard_created',
                'sms',
                $jobcard,
                null,
                $recipientPhone,
                $message,
                $result['success'] ? 'sent' : 'failed',
                $result['success'] ? null : ($result['message'] ?? 'Unknown error')
            );

        } catch (\Exception $e) {
            Log::error('Failed to send jobcard created SMS', [
                'jobcard_id' => $jobcard->id,
                'error' => $e->getMessage(),
            ]);
            $this->logReminder($jobcard->company, 'jobcard_created', 'sms', $jobcard, null, $recipientPhone ?? null, '', 'failed', $e->getMessage());
        }
    }

    /**
     * Send jobcard created WhatsApp confirmation.
     */
    protected function sendJobcardCreatedWhatsApp(Jobcard $jobcard, ReminderSettings $settings, WhatsAppService $whatsappService): void
    {
        try {

            // Get template name from settings (required for Meta WhatsApp)
            $templateName = $settings->jobcard_created_whatsapp_template_name;
            if (empty($templateName)) {
                Log::error('WhatsApp template name not configured for jobcard created', [
                    'jobcard_id' => $jobcard->id,
                ]);
                $this->logReminder($jobcard->company, 'jobcard_created', 'whatsapp', $jobcard, null, $jobcard->customer->phone ?? null, '', 'failed', 'WhatsApp template name not configured');
                return;
            }

            // Get selected variables from settings (JSON array of variable names)
            $selectedVariables = $settings->jobcard_created_whatsapp_template_variables ?? [];

            // Available variables for this context
            $availableVariables = [
                'customer_name' => $jobcard->customer->name,
                'job_number' => $jobcard->job_number,
                'jobcard_title' => $jobcard->title,
                'status' => ucfirst(str_replace('_', ' ', $jobcard->status)),
                'due_date' => $jobcard->due_date ? $jobcard->due_date->format('Y-m-d') : 'N/A',
                'company_name' => $jobcard->company->name,
            ];

            // Filter to only include selected variables
            $parameters = [];
            foreach ($selectedVariables as $varName) {
                if (isset($availableVariables[$varName])) {
                    $parameters[$varName] = $availableVariables[$varName];
                }
            }

            $result = $whatsappService->sendMessage($jobcard->customer->phone, $templateName, $parameters);

            $this->logReminder(
                $jobcard->company,
                'jobcard_created',
                'whatsapp',
                $jobcard,
                null,
                $jobcard->customer->phone,
                json_encode($parameters), // Log the parameters sent
                $result['success'] ? 'sent' : 'failed',
                $result['success'] ? null : ($result['message'] ?? 'Unknown error')
            );

        } catch (\Exception $e) {
            Log::error('Failed to send jobcard created WhatsApp', [
                'jobcard_id' => $jobcard->id,
                'error' => $e->getMessage(),
            ]);
            $this->logReminder($jobcard->company, 'jobcard_created', 'whatsapp', $jobcard, null, $jobcard->customer->phone ?? null, '', 'failed', $e->getMessage());
        }
    }

    /**
     * Send jobcard status updated email confirmation.
     */
    protected function sendJobcardStatusUpdatedEmail(Jobcard $jobcard, ReminderSettings $settings, string $oldStatus): void
    {
        try {
            $recipientEmail = $this->getRecipientEmail($jobcard);
            $template = $settings->jobcard_status_updated_email_template ?? 
                "Dear {{customer_name}},\n\n" .
                "The status of jobcard {{job_number}} has been updated.\n\n" .
                "Previous Status: {{old_status}}\n" .
                "New Status: {{new_status}}\n" .
                "Title: {{jobcard_title}}\n\n" .
                "Thank you,\n" .
                "{{company_name}}";

            $message = $this->replaceTemplateVariables($template, [
                'customer_name' => $jobcard->customer->name,
                'job_number' => $jobcard->job_number,
                'jobcard_title' => $jobcard->title,
                'old_status' => ucfirst(str_replace('_', ' ', $oldStatus)),
                'new_status' => ucfirst(str_replace('_', ' ', $jobcard->status)),
                'company_name' => $jobcard->company->name,
            ]);

            if (!$this->configureSmtp($jobcard->company)) {
                throw new \Exception('SMTP settings not configured for company');
            }

            Mail::mailer('smtp')->raw($message, function ($mail) use ($jobcard, $recipientEmail) {
                $mail->to($recipientEmail)
                    ->subject("Jobcard {$jobcard->job_number} Status Updated");
                $this->applyCompanyReplyTo($mail, $jobcard->company);
            });

            $this->logReminder($jobcard->company, 'jobcard_status_updated', 'email', $jobcard, $recipientEmail, null, $message, 'sent');

        } catch (\Exception $e) {
            Log::error('Failed to send jobcard status updated email', [
                'jobcard_id' => $jobcard->id,
                'error' => $e->getMessage(),
            ]);
            $this->logReminder($jobcard->company, 'jobcard_status_updated', 'email', $jobcard, $recipientEmail ?? null, null, '', 'failed', $e->getMessage());
        }
    }

    /**
     * Send jobcard status updated SMS confirmation.
     */
    protected function sendJobcardStatusUpdatedSMS(Jobcard $jobcard, ReminderSettings $settings, string $oldStatus): void
    {
        try {
            $recipientPhone = $this->getRecipientPhone($jobcard);
            // Ensure SMS service is available
            if (!$this->smsService) {
                $this->smsService = $this->getSMSService();
            }
            
            if (!$this->smsService) {
                Log::error('SMS service not available for jobcard status updated SMS', [
                    'jobcard_id' => $jobcard->id,
                ]);
                $this->logReminder($jobcard->company, 'jobcard_status_updated', 'sms', $jobcard, null, $recipientPhone ?? null, '', 'failed', 'SMS service not available');
                return;
            }

            $template = $settings->jobcard_status_updated_sms_template ?? 
                "Hi {{customer_name}}, Jobcard {{job_number}} status updated to {{new_status}}. {{company_name}}";

            $message = $this->replaceTemplateVariables($template, [
                'customer_name' => $jobcard->customer->name,
                'job_number' => $jobcard->job_number,
                'jobcard_title' => $jobcard->title,
                'old_status' => ucfirst(str_replace('_', ' ', $oldStatus)),
                'new_status' => ucfirst(str_replace('_', ' ', $jobcard->status)),
                'company_name' => $jobcard->company->name,
            ]);

            $result = $this->smsService->sendSMS($recipientPhone, $message);

            $this->logReminder(
                $jobcard->company,
                'jobcard_status_updated',
                'sms',
                $jobcard,
                null,
                $recipientPhone,
                $message,
                $result['success'] ? 'sent' : 'failed',
                $result['success'] ? null : ($result['message'] ?? 'Unknown error')
            );

        } catch (\Exception $e) {
            Log::error('Failed to send jobcard status updated SMS', [
                'jobcard_id' => $jobcard->id,
                'error' => $e->getMessage(),
            ]);
            $this->logReminder($jobcard->company, 'jobcard_status_updated', 'sms', $jobcard, null, $recipientPhone ?? null, '', 'failed', $e->getMessage());
        }
    }

    /**
     * Send jobcard status updated WhatsApp confirmation.
     */
    protected function sendJobcardStatusUpdatedWhatsApp(Jobcard $jobcard, ReminderSettings $settings, string $oldStatus, WhatsAppService $whatsappService): void
    {
        try {

            // Get template name from settings (required for Meta WhatsApp)
            $templateName = $settings->jobcard_status_updated_whatsapp_template_name;
            if (empty($templateName)) {
                Log::error('WhatsApp template name not configured for jobcard status updated', [
                    'jobcard_id' => $jobcard->id,
                ]);
                $this->logReminder($jobcard->company, 'jobcard_status_updated', 'whatsapp', $jobcard, null, $jobcard->customer->phone ?? null, '', 'failed', 'WhatsApp template name not configured');
                return;
            }

            // Get selected variables from settings (JSON array of variable names)
            $selectedVariables = $settings->jobcard_status_updated_whatsapp_template_variables ?? [];

            // Available variables for this context
            $availableVariables = [
                'customer_name' => $jobcard->customer->name,
                'job_number' => $jobcard->job_number,
                'jobcard_title' => $jobcard->title,
                'old_status' => ucfirst(str_replace('_', ' ', $oldStatus)),
                'new_status' => ucfirst(str_replace('_', ' ', $jobcard->status)),
                'company_name' => $jobcard->company->name,
            ];

            // Filter to only include selected variables
            $parameters = [];
            foreach ($selectedVariables as $varName) {
                if (isset($availableVariables[$varName])) {
                    $parameters[$varName] = $availableVariables[$varName];
                }
            }

            $result = $whatsappService->sendMessage($jobcard->customer->phone, $templateName, $parameters);

            $this->logReminder(
                $jobcard->company,
                'jobcard_status_updated',
                'whatsapp',
                $jobcard,
                null,
                $jobcard->customer->phone,
                json_encode($parameters), // Log the parameters sent
                $result['success'] ? 'sent' : 'failed',
                $result['success'] ? null : ($result['message'] ?? 'Unknown error')
            );

        } catch (\Exception $e) {
            Log::error('Failed to send jobcard status updated WhatsApp', [
                'jobcard_id' => $jobcard->id,
                'error' => $e->getMessage(),
            ]);
            $this->logReminder($jobcard->company, 'jobcard_status_updated', 'whatsapp', $jobcard, null, $jobcard->customer->phone ?? null, '', 'failed', $e->getMessage());
        }
    }
}


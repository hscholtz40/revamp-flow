<?php

namespace App\Models;

use App\Traits\ScopedToCurrentCompanyRouteBinding;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderSettings extends Model
{
    use HasFactory, ScopedToCurrentCompanyRouteBinding;

    protected $fillable = [
        'company_id',
        'automation_enabled',
        'overdue_invoice_email_enabled',
        'overdue_invoice_sms_enabled',
        'overdue_invoice_days_after_due',
        'overdue_invoice_frequency_days',
        'overdue_invoice_email_template',
        'overdue_invoice_sms_template',
        'expiring_quote_email_enabled',
        'expiring_quote_sms_enabled',
        'expiring_quote_days_before',
        'expiring_quote_email_template',
        'expiring_quote_sms_template',
        'payment_received_email_enabled',
        'payment_received_sms_enabled',
        'payment_received_email_template',
        'payment_received_sms_template',
        'invoice_created_email_enabled',
        'invoice_created_sms_enabled',
        'invoice_created_email_template',
        'invoice_created_sms_template',
        'quote_created_email_enabled',
        'quote_created_sms_enabled',
        'quote_created_email_template',
        'quote_created_sms_template',
        'jobcard_created_email_enabled',
        'jobcard_created_sms_enabled',
        'jobcard_created_email_template',
        'jobcard_created_sms_template',
        'jobcard_status_updated_email_enabled',
        'jobcard_status_updated_sms_enabled',
        'jobcard_status_updated_email_template',
        'jobcard_status_updated_sms_template',
        'overdue_invoice_whatsapp_enabled',
        'overdue_invoice_whatsapp_template',
        'expiring_quote_whatsapp_enabled',
        'expiring_quote_whatsapp_template',
        'payment_received_whatsapp_enabled',
        'payment_received_whatsapp_template',
        'invoice_created_whatsapp_enabled',
        'invoice_created_whatsapp_template',
        'quote_created_whatsapp_enabled',
        'quote_created_whatsapp_template',
        'jobcard_created_whatsapp_enabled',
        'jobcard_created_whatsapp_template',
        'jobcard_status_updated_whatsapp_enabled',
        'jobcard_status_updated_whatsapp_template',
        'overdue_invoice_whatsapp_template_name',
        'expiring_quote_whatsapp_template_name',
        'payment_received_whatsapp_template_name',
        'invoice_created_whatsapp_template_name',
        'quote_created_whatsapp_template_name',
        'jobcard_created_whatsapp_template_name',
        'jobcard_status_updated_whatsapp_template_name',
        'overdue_invoice_whatsapp_template_variables',
        'expiring_quote_whatsapp_template_variables',
        'payment_received_whatsapp_template_variables',
        'invoice_created_whatsapp_template_variables',
        'quote_created_whatsapp_template_variables',
        'jobcard_created_whatsapp_template_variables',
        'jobcard_status_updated_whatsapp_template_variables',
    ];

    protected $casts = [
        'automation_enabled' => 'boolean',
        'overdue_invoice_email_enabled' => 'boolean',
        'overdue_invoice_sms_enabled' => 'boolean',
        'expiring_quote_email_enabled' => 'boolean',
        'expiring_quote_sms_enabled' => 'boolean',
        'payment_received_email_enabled' => 'boolean',
        'payment_received_sms_enabled' => 'boolean',
        'invoice_created_email_enabled' => 'boolean',
        'invoice_created_sms_enabled' => 'boolean',
        'quote_created_email_enabled' => 'boolean',
        'quote_created_sms_enabled' => 'boolean',
        'jobcard_created_email_enabled' => 'boolean',
        'jobcard_created_sms_enabled' => 'boolean',
        'jobcard_status_updated_email_enabled' => 'boolean',
        'jobcard_status_updated_sms_enabled' => 'boolean',
        'overdue_invoice_whatsapp_enabled' => 'boolean',
        'expiring_quote_whatsapp_enabled' => 'boolean',
        'payment_received_whatsapp_enabled' => 'boolean',
        'invoice_created_whatsapp_enabled' => 'boolean',
        'quote_created_whatsapp_enabled' => 'boolean',
        'jobcard_created_whatsapp_enabled' => 'boolean',
        'jobcard_status_updated_whatsapp_enabled' => 'boolean',
        'overdue_invoice_whatsapp_template_variables' => 'array',
        'expiring_quote_whatsapp_template_variables' => 'array',
        'payment_received_whatsapp_template_variables' => 'array',
        'invoice_created_whatsapp_template_variables' => 'array',
        'quote_created_whatsapp_template_variables' => 'array',
        'jobcard_created_whatsapp_template_variables' => 'array',
        'jobcard_status_updated_whatsapp_template_variables' => 'array',
    ];

    /**
     * Get the company that owns these reminder settings.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get or create reminder settings for a company.
     */
    public static function getForCompany(int $companyId): self
    {
        return static::firstOrCreate(
            ['company_id' => $companyId],
            [
                'automation_enabled' => false,
                'overdue_invoice_days_after_due' => 1,
                'overdue_invoice_frequency_days' => 7,
                'expiring_quote_days_before' => 3,
            ]
        );
    }

    /**
     * Get default email template for overdue invoice.
     */
    public function getDefaultOverdueInvoiceEmailTemplate(): string
    {
        return $this->overdue_invoice_email_template ??
            "Dear {{customer_name}},\n\n".
            "This is a reminder that invoice {{invoice_number}} for the amount of {{invoice_total}} is overdue.\n\n".
            "Due Date: {{due_date}}\n".
            "Amount Due: {{invoice_total}}\n\n".
            "Please arrange payment at your earliest convenience.\n\n".
            "Thank you,\n".
            '{{company_name}}';
    }

    /**
     * Get default SMS template for overdue invoice.
     */
    public function getDefaultOverdueInvoiceSmsTemplate(): string
    {
        return $this->overdue_invoice_sms_template ??
            'Hi {{customer_name}}, Invoice {{invoice_number}} ({{invoice_total}}) is overdue. Due: {{due_date}}. Please arrange payment. {{company_name}}';
    }

    /**
     * Get default email template for expiring quote.
     */
    public function getDefaultExpiringQuoteEmailTemplate(): string
    {
        return $this->expiring_quote_email_template ??
            "Dear {{customer_name}},\n\n".
            "This is a reminder that quote {{quote_number}} will expire on {{expiry_date}}.\n\n".
            "Quote Total: {{quote_total}}\n\n".
            "If you would like to proceed with this quote, please let us know before the expiry date.\n\n".
            "Thank you,\n".
            '{{company_name}}';
    }

    /**
     * Get default SMS template for expiring quote.
     */
    public function getDefaultExpiringQuoteSmsTemplate(): string
    {
        return $this->expiring_quote_sms_template ??
            'Hi {{customer_name}}, Quote {{quote_number}} ({{quote_total}}) expires on {{expiry_date}}. Please contact us to proceed. {{company_name}}';
    }
}

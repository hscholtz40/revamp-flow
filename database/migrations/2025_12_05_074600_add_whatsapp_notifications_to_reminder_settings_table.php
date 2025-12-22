<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reminder_settings', function (Blueprint $table) {
            // Overdue Invoice WhatsApp
            $table->boolean('overdue_invoice_whatsapp_enabled')->default(false)->after('overdue_invoice_sms_template');
            $table->text('overdue_invoice_whatsapp_template')->nullable();
            
            // Expiring Quote WhatsApp
            $table->boolean('expiring_quote_whatsapp_enabled')->default(false)->after('expiring_quote_sms_template');
            $table->text('expiring_quote_whatsapp_template')->nullable();
            
            // Payment Received WhatsApp
            $table->boolean('payment_received_whatsapp_enabled')->default(false)->after('payment_received_sms_template');
            $table->text('payment_received_whatsapp_template')->nullable();
            
            // Invoice Created WhatsApp
            $table->boolean('invoice_created_whatsapp_enabled')->default(false)->after('invoice_created_sms_template');
            $table->text('invoice_created_whatsapp_template')->nullable();
            
            // Quote Created WhatsApp
            $table->boolean('quote_created_whatsapp_enabled')->default(false)->after('quote_created_sms_template');
            $table->text('quote_created_whatsapp_template')->nullable();
            
            // Jobcard Created WhatsApp
            $table->boolean('jobcard_created_whatsapp_enabled')->default(false)->after('jobcard_created_sms_template');
            $table->text('jobcard_created_whatsapp_template')->nullable();
            
            // Jobcard Status Updated WhatsApp
            $table->boolean('jobcard_status_updated_whatsapp_enabled')->default(false)->after('jobcard_status_updated_sms_template');
            $table->text('jobcard_status_updated_whatsapp_template')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reminder_settings', function (Blueprint $table) {
            $table->dropColumn([
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
            ]);
        });
    }
};

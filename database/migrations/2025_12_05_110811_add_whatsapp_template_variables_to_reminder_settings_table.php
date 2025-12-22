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
            // Add JSON fields to store selected variables for each WhatsApp notification type
            $table->json('overdue_invoice_whatsapp_template_variables')->nullable()->after('overdue_invoice_whatsapp_template_name');
            $table->json('expiring_quote_whatsapp_template_variables')->nullable()->after('expiring_quote_whatsapp_template_name');
            $table->json('payment_received_whatsapp_template_variables')->nullable()->after('payment_received_whatsapp_template_name');
            $table->json('invoice_created_whatsapp_template_variables')->nullable()->after('invoice_created_whatsapp_template_name');
            $table->json('quote_created_whatsapp_template_variables')->nullable()->after('quote_created_whatsapp_template_name');
            $table->json('jobcard_created_whatsapp_template_variables')->nullable()->after('jobcard_created_whatsapp_template_name');
            $table->json('jobcard_status_updated_whatsapp_template_variables')->nullable()->after('jobcard_status_updated_whatsapp_template_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reminder_settings', function (Blueprint $table) {
            $table->dropColumn([
                'overdue_invoice_whatsapp_template_variables',
                'expiring_quote_whatsapp_template_variables',
                'payment_received_whatsapp_template_variables',
                'invoice_created_whatsapp_template_variables',
                'quote_created_whatsapp_template_variables',
                'jobcard_created_whatsapp_template_variables',
                'jobcard_status_updated_whatsapp_template_variables',
            ]);
        });
    }
};

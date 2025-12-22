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
            // Add template name fields for each WhatsApp notification type
            $table->string('overdue_invoice_whatsapp_template_name')->nullable()->after('overdue_invoice_whatsapp_template');
            $table->string('expiring_quote_whatsapp_template_name')->nullable()->after('expiring_quote_whatsapp_template');
            $table->string('payment_received_whatsapp_template_name')->nullable()->after('payment_received_whatsapp_template');
            $table->string('invoice_created_whatsapp_template_name')->nullable()->after('invoice_created_whatsapp_template');
            $table->string('quote_created_whatsapp_template_name')->nullable()->after('quote_created_whatsapp_template');
            $table->string('jobcard_created_whatsapp_template_name')->nullable()->after('jobcard_created_whatsapp_template');
            $table->string('jobcard_status_updated_whatsapp_template_name')->nullable()->after('jobcard_status_updated_whatsapp_template');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reminder_settings', function (Blueprint $table) {
            $table->dropColumn([
                'overdue_invoice_whatsapp_template_name',
                'expiring_quote_whatsapp_template_name',
                'payment_received_whatsapp_template_name',
                'invoice_created_whatsapp_template_name',
                'quote_created_whatsapp_template_name',
                'jobcard_created_whatsapp_template_name',
                'jobcard_status_updated_whatsapp_template_name',
            ]);
        });
    }
};

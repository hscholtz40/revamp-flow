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
        Schema::create('reminder_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            
            // General automation settings
            $table->boolean('automation_enabled')->default(false);
            
            // Overdue Invoice Reminders
            $table->boolean('overdue_invoice_email_enabled')->default(false);
            $table->boolean('overdue_invoice_sms_enabled')->default(false);
            $table->integer('overdue_invoice_days_after_due')->default(1); // Days after due date to send reminder
            $table->integer('overdue_invoice_frequency_days')->default(7); // How often to send reminders (every X days)
            $table->text('overdue_invoice_email_template')->nullable();
            $table->text('overdue_invoice_sms_template')->nullable();
            
            // Expiring Quote Reminders
            $table->boolean('expiring_quote_email_enabled')->default(false);
            $table->boolean('expiring_quote_sms_enabled')->default(false);
            $table->integer('expiring_quote_days_before')->default(3); // Days before expiry to send reminder
            $table->text('expiring_quote_email_template')->nullable();
            $table->text('expiring_quote_sms_template')->nullable();
            
            // Payment Received Confirmation
            $table->boolean('payment_received_email_enabled')->default(false);
            $table->boolean('payment_received_sms_enabled')->default(false);
            $table->text('payment_received_email_template')->nullable();
            $table->text('payment_received_sms_template')->nullable();
            
            // Invoice Sent Confirmation
            $table->boolean('invoice_sent_email_enabled')->default(false);
            $table->boolean('invoice_sent_sms_enabled')->default(false);
            $table->text('invoice_sent_email_template')->nullable();
            $table->text('invoice_sent_sms_template')->nullable();
            
            // Quote Sent Confirmation
            $table->boolean('quote_sent_email_enabled')->default(false);
            $table->boolean('quote_sent_sms_enabled')->default(false);
            $table->text('quote_sent_email_template')->nullable();
            $table->text('quote_sent_sms_template')->nullable();
            
            $table->timestamps();
            
            $table->unique('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reminder_settings');
    }
};

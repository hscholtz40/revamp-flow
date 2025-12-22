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
        // Use raw SQL for better compatibility across database systems
        \DB::statement('ALTER TABLE `reminder_settings` CHANGE `invoice_sent_email_enabled` `invoice_created_email_enabled` BOOLEAN DEFAULT FALSE');
        \DB::statement('ALTER TABLE `reminder_settings` CHANGE `invoice_sent_sms_enabled` `invoice_created_sms_enabled` BOOLEAN DEFAULT FALSE');
        \DB::statement('ALTER TABLE `reminder_settings` CHANGE `invoice_sent_email_template` `invoice_created_email_template` TEXT NULL');
        \DB::statement('ALTER TABLE `reminder_settings` CHANGE `invoice_sent_sms_template` `invoice_created_sms_template` TEXT NULL');
        
        \DB::statement('ALTER TABLE `reminder_settings` CHANGE `quote_sent_email_enabled` `quote_created_email_enabled` BOOLEAN DEFAULT FALSE');
        \DB::statement('ALTER TABLE `reminder_settings` CHANGE `quote_sent_sms_enabled` `quote_created_sms_enabled` BOOLEAN DEFAULT FALSE');
        \DB::statement('ALTER TABLE `reminder_settings` CHANGE `quote_sent_email_template` `quote_created_email_template` TEXT NULL');
        \DB::statement('ALTER TABLE `reminder_settings` CHANGE `quote_sent_sms_template` `quote_created_sms_template` TEXT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Use raw SQL for better compatibility across database systems
        \DB::statement('ALTER TABLE `reminder_settings` CHANGE `invoice_created_email_enabled` `invoice_sent_email_enabled` BOOLEAN DEFAULT FALSE');
        \DB::statement('ALTER TABLE `reminder_settings` CHANGE `invoice_created_sms_enabled` `invoice_sent_sms_enabled` BOOLEAN DEFAULT FALSE');
        \DB::statement('ALTER TABLE `reminder_settings` CHANGE `invoice_created_email_template` `invoice_sent_email_template` TEXT NULL');
        \DB::statement('ALTER TABLE `reminder_settings` CHANGE `invoice_created_sms_template` `invoice_sent_sms_template` TEXT NULL');
        
        \DB::statement('ALTER TABLE `reminder_settings` CHANGE `quote_created_email_enabled` `quote_sent_email_enabled` BOOLEAN DEFAULT FALSE');
        \DB::statement('ALTER TABLE `reminder_settings` CHANGE `quote_created_sms_enabled` `quote_sent_sms_enabled` BOOLEAN DEFAULT FALSE');
        \DB::statement('ALTER TABLE `reminder_settings` CHANGE `quote_created_email_template` `quote_sent_email_template` TEXT NULL');
        \DB::statement('ALTER TABLE `reminder_settings` CHANGE `quote_created_sms_template` `quote_sent_sms_template` TEXT NULL');
    }
};

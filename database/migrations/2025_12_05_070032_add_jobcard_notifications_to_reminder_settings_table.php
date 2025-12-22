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
            // Jobcard Created Notifications
            $table->boolean('jobcard_created_email_enabled')->default(false)->after('quote_created_sms_template');
            $table->boolean('jobcard_created_sms_enabled')->default(false);
            $table->text('jobcard_created_email_template')->nullable();
            $table->text('jobcard_created_sms_template')->nullable();
            
            // Jobcard Status Updated Notifications
            $table->boolean('jobcard_status_updated_email_enabled')->default(false);
            $table->boolean('jobcard_status_updated_sms_enabled')->default(false);
            $table->text('jobcard_status_updated_email_template')->nullable();
            $table->text('jobcard_status_updated_sms_template')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reminder_settings', function (Blueprint $table) {
            $table->dropColumn([
                'jobcard_created_email_enabled',
                'jobcard_created_sms_enabled',
                'jobcard_created_email_template',
                'jobcard_created_sms_template',
                'jobcard_status_updated_email_enabled',
                'jobcard_status_updated_sms_enabled',
                'jobcard_status_updated_email_template',
                'jobcard_status_updated_sms_template',
            ]);
        });
    }
};

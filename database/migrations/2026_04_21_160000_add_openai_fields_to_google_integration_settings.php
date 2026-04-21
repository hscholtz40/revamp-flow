<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('google_integration_settings')) {
            return;
        }
        Schema::table('google_integration_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('google_integration_settings', 'openai_api_key')) {
                $table->text('openai_api_key')->nullable()->after('maps_map_id');
            }
            if (! Schema::hasColumn('google_integration_settings', 'ai_enabled')) {
                $table->boolean('ai_enabled')->default(false)->after('openai_api_key');
            }
            if (! Schema::hasColumn('google_integration_settings', 'ai_admin_only')) {
                $table->boolean('ai_admin_only')->default(true)->after('ai_enabled');
            }
            if (! Schema::hasColumn('google_integration_settings', 'ai_prompt_logging_enabled')) {
                $table->boolean('ai_prompt_logging_enabled')->default(true)->after('ai_admin_only');
            }
            if (! Schema::hasColumn('google_integration_settings', 'ai_daily_user_limit')) {
                $table->unsignedInteger('ai_daily_user_limit')->default(50)->after('ai_prompt_logging_enabled');
            }
            if (! Schema::hasColumn('google_integration_settings', 'ai_daily_company_limit')) {
                $table->unsignedInteger('ai_daily_company_limit')->default(500)->after('ai_daily_user_limit');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('google_integration_settings')) {
            return;
        }
        Schema::table('google_integration_settings', function (Blueprint $table) {
            $columns = [
                'openai_api_key',
                'ai_enabled',
                'ai_admin_only',
                'ai_prompt_logging_enabled',
                'ai_daily_user_limit',
                'ai_daily_company_limit',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('google_integration_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

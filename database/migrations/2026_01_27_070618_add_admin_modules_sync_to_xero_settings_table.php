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
        Schema::table('xero_settings', function (Blueprint $table) {
            $table->boolean('sync_tax_rates_from_xero')->default(false)->after('sync_quotes_from_xero');
            $table->boolean('sync_bank_accounts_from_xero')->default(false)->after('sync_tax_rates_from_xero');
            $table->boolean('sync_chart_of_accounts_from_xero')->default(false)->after('sync_bank_accounts_from_xero');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('xero_settings', function (Blueprint $table) {
            $table->dropColumn([
                'sync_tax_rates_from_xero',
                'sync_bank_accounts_from_xero',
                'sync_chart_of_accounts_from_xero',
            ]);
        });
    }
};

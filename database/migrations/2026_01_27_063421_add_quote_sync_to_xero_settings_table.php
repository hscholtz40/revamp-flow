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
            $table->boolean('sync_quotes_to_xero')->default(false)->after('sync_suppliers_from_xero');
            $table->boolean('sync_quotes_from_xero')->default(false)->after('sync_quotes_to_xero');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('xero_settings', function (Blueprint $table) {
            $table->dropColumn(['sync_quotes_to_xero', 'sync_quotes_from_xero']);
        });
    }
};

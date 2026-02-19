<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('xero_settings', function (Blueprint $table) {
            $table->boolean('sync_credit_notes_to_xero')->default(false);
            $table->boolean('sync_credit_notes_from_xero')->default(false);
            $table->boolean('sync_purchase_orders_to_xero')->default(false);
            $table->boolean('sync_purchase_orders_from_xero')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('xero_settings', function (Blueprint $table) {
            $table->dropColumn([
                'sync_credit_notes_to_xero',
                'sync_credit_notes_from_xero',
                'sync_purchase_orders_to_xero',
                'sync_purchase_orders_from_xero',
            ]);
        });
    }
};

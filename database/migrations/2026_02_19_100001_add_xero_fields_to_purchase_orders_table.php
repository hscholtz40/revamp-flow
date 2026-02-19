<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('xero_purchase_order_id')->nullable()->index()->after('id');
            $table->timestamp('xero_updated_at')->nullable();
            $table->timestamp('xero_created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['xero_purchase_order_id', 'xero_updated_at', 'xero_created_at']);
        });
    }
};

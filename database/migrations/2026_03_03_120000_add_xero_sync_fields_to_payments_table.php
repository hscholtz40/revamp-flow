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
        Schema::table('payments', function (Blueprint $table) {
            $table->string('xero_payment_id')->nullable()->after('company_id');
            $table->timestamp('xero_synced_at')->nullable()->after('xero_payment_id');
            $table->index('xero_payment_id');
            $table->index('xero_synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['xero_payment_id']);
            $table->dropIndex(['xero_synced_at']);
            $table->dropColumn(['xero_payment_id', 'xero_synced_at']);
        });
    }
};

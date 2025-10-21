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
            // Payment account codes for different payment methods
            $table->string('payment_account_cash')->nullable()->comment('Account code for cash payments');
            $table->string('payment_account_card')->nullable()->comment('Account code for card payments');
            $table->string('payment_account_eft')->nullable()->comment('Account code for EFT/bank transfer payments');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('xero_settings', function (Blueprint $table) {
            $table->dropColumn([
                'payment_account_cash',
                'payment_account_card', 
                'payment_account_eft'
            ]);
        });
    }
};

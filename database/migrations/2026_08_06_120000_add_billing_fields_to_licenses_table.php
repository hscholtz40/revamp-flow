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
        Schema::table('licenses', function (Blueprint $table) {
            $table->string('billing_cycle', 20)->nullable()->after('expires_at');
            $table->string('pricing_model', 20)->nullable()->after('billing_cycle');
            $table->decimal('price_standard_monthly', 12, 2)->nullable()->after('pricing_model');
            $table->decimal('price_limited_monthly', 12, 2)->nullable()->after('price_standard_monthly');
            $table->decimal('price_standard_annual', 12, 2)->nullable()->after('price_limited_monthly');
            $table->decimal('price_limited_annual', 12, 2)->nullable()->after('price_standard_annual');
            $table->decimal('fixed_amount_monthly', 12, 2)->nullable()->after('price_limited_annual');
            $table->decimal('fixed_amount_annual', 12, 2)->nullable()->after('fixed_amount_monthly');
            $table->boolean('auto_email_invoice')->default(true)->after('fixed_amount_annual');
            $table->date('next_invoice_date')->nullable()->after('auto_email_invoice');
            $table->timestamp('last_invoiced_at')->nullable()->after('next_invoice_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn([
                'billing_cycle',
                'pricing_model',
                'price_standard_monthly',
                'price_limited_monthly',
                'price_standard_annual',
                'price_limited_annual',
                'fixed_amount_monthly',
                'fixed_amount_annual',
                'auto_email_invoice',
                'next_invoice_date',
                'last_invoiced_at',
            ]);
        });
    }
};

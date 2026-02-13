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
        Schema::table('jobcard_line_items', function (Blueprint $table) {
            $table->foreignId('tax_rate_id')->nullable()->after('total')->constrained('tax_rates')->nullOnDelete();
            $table->decimal('tax_amount', 10, 2)->default(0)->after('tax_rate_id');
        });

        Schema::table('invoice_line_items', function (Blueprint $table) {
            $table->foreignId('tax_rate_id')->nullable()->after('total')->constrained('tax_rates')->nullOnDelete();
            $table->decimal('tax_amount', 10, 2)->default(0)->after('tax_rate_id');
        });

        Schema::table('quote_line_items', function (Blueprint $table) {
            $table->foreignId('tax_rate_id')->nullable()->after('total')->constrained('tax_rates')->nullOnDelete();
            $table->decimal('tax_amount', 10, 2)->default(0)->after('tax_rate_id');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->foreignId('tax_rate_id')->nullable()->after('total')->constrained('tax_rates')->nullOnDelete();
            $table->decimal('tax_amount', 10, 2)->default(0)->after('tax_rate_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jobcard_line_items', function (Blueprint $table) {
            $table->dropForeign(['tax_rate_id']);
            $table->dropColumn(['tax_rate_id', 'tax_amount']);
        });

        Schema::table('invoice_line_items', function (Blueprint $table) {
            $table->dropForeign(['tax_rate_id']);
            $table->dropColumn(['tax_rate_id', 'tax_amount']);
        });

        Schema::table('quote_line_items', function (Blueprint $table) {
            $table->dropForeign(['tax_rate_id']);
            $table->dropColumn(['tax_rate_id', 'tax_amount']);
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropForeign(['tax_rate_id']);
            $table->dropColumn(['tax_rate_id', 'tax_amount']);
        });
    }
};

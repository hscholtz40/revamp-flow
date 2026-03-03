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
        Schema::table('companies', function (Blueprint $table) {
            $table->string('invoice_number_prefix')->nullable()->after('bank_sort_code');
            $table->unsignedBigInteger('invoice_number_next')->nullable()->after('invoice_number_prefix');

            $table->string('quote_number_prefix')->nullable()->after('invoice_number_next');
            $table->unsignedBigInteger('quote_number_next')->nullable()->after('quote_number_prefix');

            $table->string('jobcard_number_prefix')->nullable()->after('quote_number_next');
            $table->unsignedBigInteger('jobcard_number_next')->nullable()->after('jobcard_number_prefix');

            $table->string('credit_note_number_prefix')->nullable()->after('jobcard_number_next');
            $table->unsignedBigInteger('credit_note_number_next')->nullable()->after('credit_note_number_prefix');

            $table->string('purchase_order_number_prefix')->nullable()->after('credit_note_number_next');
            $table->unsignedBigInteger('purchase_order_number_next')->nullable()->after('purchase_order_number_prefix');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_number_prefix',
                'invoice_number_next',
                'quote_number_prefix',
                'quote_number_next',
                'jobcard_number_prefix',
                'jobcard_number_next',
                'credit_note_number_prefix',
                'credit_note_number_next',
                'purchase_order_number_prefix',
                'purchase_order_number_next',
            ]);
        });
    }
};

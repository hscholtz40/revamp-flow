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
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('order_number')->nullable()->after('quote_number');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('order_number')->nullable()->after('invoice_number');
        });

        Schema::table('jobcards', function (Blueprint $table) {
            $table->string('order_number')->nullable()->after('job_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn('order_number');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('order_number');
        });

        Schema::table('jobcards', function (Blueprint $table) {
            $table->dropColumn('order_number');
        });
    }
};

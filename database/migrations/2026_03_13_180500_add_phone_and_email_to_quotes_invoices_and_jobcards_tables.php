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
            $table->string('email')->nullable()->after('customer_id');
            $table->string('phone')->nullable()->after('email');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('email')->nullable()->after('customer_id');
            $table->string('phone')->nullable()->after('email');
        });

        Schema::table('jobcards', function (Blueprint $table) {
            $table->string('email')->nullable()->after('customer_id');
            $table->string('phone')->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn(['email', 'phone']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['email', 'phone']);
        });

        Schema::table('jobcards', function (Blueprint $table) {
            $table->dropColumn(['email', 'phone']);
        });
    }
};

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
            $table->foreignId('contact_id')->nullable()->after('customer_id')->constrained('contacts')->nullOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->after('customer_id')->constrained('contacts')->nullOnDelete();
        });

        Schema::table('jobcards', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->after('customer_id')->constrained('contacts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
        });

        Schema::table('jobcards', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
        });
    }
};

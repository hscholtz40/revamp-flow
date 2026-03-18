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
            $table->string('source_type')->nullable()->after('invoice_id');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->index(['source_type', 'source_id'], 'quotes_source_index');
        });

        Schema::table('jobcards', function (Blueprint $table) {
            $table->string('source_type')->nullable()->after('invoice_id');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->index(['source_type', 'source_id'], 'jobcards_source_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jobcards', function (Blueprint $table) {
            $table->dropIndex('jobcards_source_index');
            $table->dropColumn(['source_type', 'source_id']);
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->dropIndex('quotes_source_index');
            $table->dropColumn(['source_type', 'source_id']);
        });
    }
};

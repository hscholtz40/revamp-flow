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
            $table->text('default_invoice_terms')->nullable()->after('quote_footer');
            $table->text('default_quote_terms')->nullable()->after('default_invoice_terms');
            $table->text('default_jobcard_terms')->nullable()->after('default_quote_terms');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['default_invoice_terms', 'default_quote_terms', 'default_jobcard_terms']);
        });
    }
};

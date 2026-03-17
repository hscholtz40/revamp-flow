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
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->boolean('is_default_rounding')->default(false)->after('is_default_purchasing');
            $table->index(['company_id', 'is_default_rounding']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'is_default_rounding']);
            $table->dropColumn('is_default_rounding');
        });
    }
};

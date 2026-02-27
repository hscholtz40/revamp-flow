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
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('is_default_sales')->default(false)->after('notes');
            $table->index(['company_id', 'is_default_sales']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'is_default_sales']);
            $table->dropColumn('is_default_sales');
        });
    }
};


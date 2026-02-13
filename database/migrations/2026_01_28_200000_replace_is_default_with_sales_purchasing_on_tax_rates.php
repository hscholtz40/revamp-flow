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
        Schema::table('tax_rates', function (Blueprint $table) {
            $table->boolean('is_default_sales')->default(false)->after('is_default');
            $table->boolean('is_default_purchasing')->default(false)->after('is_default_sales');
        });

        // Migrate existing is_default values to is_default_sales (backward compat)
        \DB::table('tax_rates')->where('is_default', true)->update([
            'is_default_sales' => true,
        ]);

        Schema::table('tax_rates', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'is_default']);
            $table->dropColumn('is_default');
            $table->index(['company_id', 'is_default_sales']);
            $table->index(['company_id', 'is_default_purchasing']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tax_rates', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('is_active');
        });

        \DB::table('tax_rates')->where('is_default_sales', true)->update([
            'is_default' => true,
        ]);

        Schema::table('tax_rates', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'is_default_sales']);
            $table->dropIndex(['company_id', 'is_default_purchasing']);
            $table->dropColumn(['is_default_sales', 'is_default_purchasing']);
            $table->index(['company_id', 'is_default']);
        });
    }
};

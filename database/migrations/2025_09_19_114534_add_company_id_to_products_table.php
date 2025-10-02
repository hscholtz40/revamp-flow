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
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id');
        });
        
        // Get the default company
        $defaultCompany = \App\Models\Company::getDefault();
        
        if ($defaultCompany) {
            // Update all existing products to use the default company
            \App\Models\Product::whereNull('company_id')->update(['company_id' => $defaultCompany->id]);
        }
        
        // Now make the column required and add the foreign key constraint
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable(false)->change();
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};

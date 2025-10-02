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
        // Get the default company
        $defaultCompany = \App\Models\Company::getDefault();
        
        if ($defaultCompany) {
            // Update all existing customers to use the default company
            \App\Models\Customer::whereNull('company_id')->update(['company_id' => $defaultCompany->id]);
        }
        
        // Add the foreign key constraint
        Schema::table('customers', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
        });
    }
};

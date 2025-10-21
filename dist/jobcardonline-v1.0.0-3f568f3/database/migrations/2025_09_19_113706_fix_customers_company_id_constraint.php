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
        // Populate any null company_id values with the default company (if available)
        $defaultCompany = \App\Models\Company::getDefault();
        if ($defaultCompany) {
            \App\Models\Customer::whereNull('company_id')->update(['company_id' => $defaultCompany->id]);
        }

        // Ensure the column exists and then add the foreign key constraint
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'company_id')) {
                $table->foreignId('company_id')->nullable();
            }
        });

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

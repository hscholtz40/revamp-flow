<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, populate any null company_id values with the default company, if one exists
        $defaultCompany = \App\Models\Company::getDefault();
        if ($defaultCompany) {
            DB::table('xero_settings')->whereNull('company_id')->update(['company_id' => $defaultCompany->id]);
        }
        
        // Ensure the column exists
        Schema::table('xero_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('xero_settings', 'company_id')) {
                $table->foreignId('company_id')->nullable();
            }
        });

        // Add only the unique constraint here to avoid duplicate FK creation on fresh installs
        Schema::table('xero_settings', function (Blueprint $table) {
            $table->unique('company_id'); // Each company can only have one Xero settings record
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('xero_settings', function (Blueprint $table) {
            $table->dropUnique(['company_id']);
        });
    }
};

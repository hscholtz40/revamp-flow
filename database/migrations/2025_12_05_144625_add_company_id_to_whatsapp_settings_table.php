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
        Schema::table('whatsapp_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_settings', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('id');
            }
        });
        
        // Populate any null company_id values with the default company, if one exists
        $defaultCompany = \App\Models\Company::getDefault();
        if ($defaultCompany) {
            \DB::table('whatsapp_settings')->whereNull('company_id')->update(['company_id' => $defaultCompany->id]);
        }
        
        // Make company_id required and add foreign key constraint
        Schema::table('whatsapp_settings', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->unique('company_id'); // Each company can only have one WhatsApp settings record
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_settings', function (Blueprint $table) {
            $table->dropUnique(['company_id']);
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};

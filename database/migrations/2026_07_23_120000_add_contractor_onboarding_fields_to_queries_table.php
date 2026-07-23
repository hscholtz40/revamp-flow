<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queries', function (Blueprint $table) {
            $table->string('website_status')->nullable()->after('company_website');
            $table->string('company_city')->nullable()->after('company_address');
            $table->string('company_province')->nullable()->after('company_city');
            $table->string('selected_package')->nullable()->after('website_status');
        });
    }

    public function down(): void
    {
        Schema::table('queries', function (Blueprint $table) {
            $table->dropColumn([
                'website_status',
                'company_city',
                'company_province',
                'selected_package',
            ]);
        });
    }
};

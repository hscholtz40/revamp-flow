<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('locale_timezone', 64)->default('Africa/Johannesburg');
            $table->string('locale_date_format', 32)->default('dd/mm/yyyy');
            $table->string('locale_time_format', 16)->default('24h');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['locale_timezone', 'locale_date_format', 'locale_time_format']);
        });
    }
};

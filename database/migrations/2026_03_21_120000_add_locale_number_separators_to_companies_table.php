<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('locale_decimal_separator', 8)->default('.');
            $table->string('locale_thousands_separator', 8)->default(',');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['locale_decimal_separator', 'locale_thousands_separator']);
        });
    }
};

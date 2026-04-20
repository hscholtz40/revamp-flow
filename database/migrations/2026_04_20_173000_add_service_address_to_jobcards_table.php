<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobcards', function (Blueprint $table) {
            $table->string('service_address', 500)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('jobcards', function (Blueprint $table) {
            $table->dropColumn('service_address');
        });
    }
};

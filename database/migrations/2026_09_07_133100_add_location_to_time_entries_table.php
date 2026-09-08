<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('started_at');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->decimal('location_accuracy', 8, 2)->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'location_accuracy']);
        });
    }
};

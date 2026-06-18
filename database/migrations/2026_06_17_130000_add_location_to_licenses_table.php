<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            // Location of the licensed contractor, used for geo-proximity matching.
            $table->decimal('latitude', 10, 7)->nullable()->after('url');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('location_address')->nullable()->after('longitude');
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropIndex(['latitude', 'longitude']);
            $table->dropColumn(['latitude', 'longitude', 'location_address']);
        });
    }
};

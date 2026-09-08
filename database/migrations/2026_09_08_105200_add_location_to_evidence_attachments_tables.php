<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobcard_attachments', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('description');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->decimal('location_accuracy', 8, 2)->nullable()->after('longitude');
        });

        Schema::table('purchase_order_attachments', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('description');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->decimal('location_accuracy', 8, 2)->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('jobcard_attachments', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'location_accuracy']);
        });

        Schema::table('purchase_order_attachments', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'location_accuracy']);
        });
    }
};

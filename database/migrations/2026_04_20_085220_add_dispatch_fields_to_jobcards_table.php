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
        Schema::table('jobcards', function (Blueprint $table) {
            $table->timestamp('scheduled_start_at')->nullable()->after('completed_date');
            $table->timestamp('scheduled_end_at')->nullable()->after('scheduled_start_at');
            $table->unsignedInteger('dispatch_order')->nullable()->after('scheduled_end_at');
            $table->json('route_meta')->nullable()->after('dispatch_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jobcards', function (Blueprint $table) {
            $table->dropColumn([
                'scheduled_start_at',
                'scheduled_end_at',
                'dispatch_order',
                'route_meta',
            ]);
        });
    }
};

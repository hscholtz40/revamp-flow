<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobcards', function (Blueprint $table) {
            $table->string('priority', 20)->default('normal')->after('status');
            $table->unsignedInteger('estimated_duration_minutes')->nullable()->after('scheduled_end_at');
        });
    }

    public function down(): void
    {
        Schema::table('jobcards', function (Blueprint $table) {
            $table->dropColumn(['priority', 'estimated_duration_minutes']);
        });
    }
};

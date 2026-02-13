<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobcards', function (Blueprint $table) {
            $table->foreignId('assigned_to_team_id')->nullable()->after('assigned_to_user_id')->constrained('teams')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('jobcards', function (Blueprint $table) {
            $table->dropForeign(['assigned_to_team_id']);
            $table->dropColumn('assigned_to_team_id');
        });
    }
};

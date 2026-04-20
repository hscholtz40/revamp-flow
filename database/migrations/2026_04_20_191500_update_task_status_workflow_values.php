<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('tasks')->where('status', 'pending')->update(['status' => 'new']);
        DB::table('tasks')->where('status', 'in_progress')->update(['status' => 'accepted']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('tasks')->where('status', 'new')->update(['status' => 'pending']);
        DB::table('tasks')->where('status', 'accepted')->update(['status' => 'in_progress']);
    }
};

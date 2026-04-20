<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE `jobcards` MODIFY `status` ENUM('new','needs_scheduling','scheduled','dispatched','accepted','en_route','on_site','paused','waiting_for_parts','needs_follow_up','emergency','completed','cancelled') NOT NULL DEFAULT 'new'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('jobcards')->where('status', 'emergency')->update(['status' => 'needs_follow_up']);

        DB::statement("ALTER TABLE `jobcards` MODIFY `status` ENUM('new','needs_scheduling','scheduled','dispatched','accepted','en_route','on_site','paused','waiting_for_parts','needs_follow_up','completed','cancelled') NOT NULL DEFAULT 'new'");
    }
};

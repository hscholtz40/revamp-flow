<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const STATUSES = [
        'new',
        'needs_scheduling',
        'scheduled',
        'dispatched',
        'accepted',
        'en_route',
        'on_site',
        'paused',
        'waiting_for_parts',
        'needs_follow_up',
        'completed',
        'cancelled',
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `jobcards` MODIFY `status` ENUM('draft','pending','in_progress','new','needs_scheduling','scheduled','dispatched','accepted','en_route','on_site','paused','waiting_for_parts','needs_follow_up','completed','cancelled') NOT NULL DEFAULT 'draft'");
        }

        DB::table('jobcards')
            ->where('status', 'draft')
            ->update(['status' => 'new']);

        DB::table('jobcards')
            ->where('status', 'pending')
            ->update(['status' => 'needs_scheduling']);

        DB::table('jobcards')
            ->where('status', 'in_progress')
            ->update(['status' => 'on_site']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `jobcards` MODIFY `status` ENUM('new','needs_scheduling','scheduled','dispatched','accepted','en_route','on_site','paused','waiting_for_parts','needs_follow_up','completed','cancelled') NOT NULL DEFAULT 'new'");
        }
    }

    public function down(): void
    {
        DB::table('jobcards')
            ->where('status', 'new')
            ->update(['status' => 'draft']);

        DB::table('jobcards')
            ->where('status', 'needs_scheduling')
            ->update(['status' => 'pending']);

        DB::table('jobcards')
            ->whereIn('status', ['scheduled', 'dispatched', 'accepted', 'en_route', 'on_site', 'paused', 'waiting_for_parts', 'needs_follow_up'])
            ->update(['status' => 'in_progress']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `jobcards` MODIFY `status` ENUM('draft','pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'draft'");
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * TIMESTAMP columns follow the MySQL session timezone on write/read.
     * DATETIME stores the UTC wall clock Laravel sends (no conversion).
     */
    public function up(): void
    {
        // MySQL/MariaDB-only: SET time_zone, MODIFY column type and DATE_ADD are
        // MySQL syntax. No-op on other drivers (e.g. sqlite used in tests).
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("SET time_zone = '+00:00'");
        DB::statement('ALTER TABLE location_pings MODIFY recorded_at DATETIME NOT NULL');

        // Legacy TIMESTAMP rows written under SAST (+02:00) session were stored ~2h early.
        // Only repair rows that still look ~2h behind current UTC (not already-correct UTC rows).
        DB::table('location_pings')
            ->where('recorded_at', '<', now()->subHours(3))
            ->where('recorded_at', '>=', now()->subDay())
            ->update([
                'recorded_at' => DB::raw('DATE_ADD(recorded_at, INTERVAL 2 HOUR)'),
            ]);
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("SET time_zone = '+00:00'");
        DB::statement('ALTER TABLE location_pings MODIFY recorded_at TIMESTAMP NOT NULL');
    }
};

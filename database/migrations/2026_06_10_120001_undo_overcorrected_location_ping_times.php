<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The prior migration added 2h to every row; pings already stored in UTC were shifted forward.
     * Undo only rows that are more than 30 minutes in the future (impossible for real GPS ingests).
     */
    public function up(): void
    {
        // MySQL/MariaDB-only data repair (DATE_SUB/INTERVAL). No-op elsewhere (e.g. sqlite in tests).
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::table('location_pings')
            ->where('recorded_at', '>', now()->addMinutes(30))
            ->update([
                'recorded_at' => DB::raw('DATE_SUB(recorded_at, INTERVAL 2 HOUR)'),
            ]);
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::table('location_pings')
            ->where('recorded_at', '<', now()->subMinutes(30))
            ->update([
                'recorded_at' => DB::raw('DATE_ADD(recorded_at, INTERVAL 2 HOUR)'),
            ]);
    }
};

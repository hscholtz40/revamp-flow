<?php

use App\Support\JobcardStatuses;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            'ALTER TABLE `jobcards` MODIFY `status` ENUM('.JobcardStatuses::mysqlEnumDefinition().") NOT NULL DEFAULT 'new'"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('jobcards')
            ->whereIn('status', [
                'purchase_order_sent_on_dispatch',
                'order_received',
                'stock_checked',
                'delivery_scheduled',
                'delivered',
            ])
            ->update(['status' => 'dispatched']);

        DB::statement("ALTER TABLE `jobcards` MODIFY `status` ENUM('new','needs_scheduling','scheduled','dispatched','accepted','en_route','on_site','paused','waiting_for_parts','needs_follow_up','emergency','completed','cancelled') NOT NULL DEFAULT 'new'");
    }
};

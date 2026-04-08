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
        if (DB::getDriverName() === 'sqlite') {
            // SQLite does not support MySQL-style ALTER ... MODIFY syntax used below.
            return;
        }

        DB::statement('ALTER TABLE purchase_order_items MODIFY product_id BIGINT UNSIGNED NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE purchase_order_items MODIFY product_id BIGINT UNSIGNED NOT NULL');
    }
};

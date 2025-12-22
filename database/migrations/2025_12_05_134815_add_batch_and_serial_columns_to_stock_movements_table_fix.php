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
        Schema::table('stock_movements', function (Blueprint $table) {
            // Check if columns don't exist before adding them
            if (!Schema::hasColumn('stock_movements', 'product_batch_id')) {
                $table->foreignId('product_batch_id')->nullable()->after('product_id')->constrained('product_batches')->onDelete('set null');
            }
            if (!Schema::hasColumn('stock_movements', 'product_serial_number_id')) {
                $table->foreignId('product_serial_number_id')->nullable()->after('product_batch_id')->constrained('product_serial_numbers')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            if (Schema::hasColumn('stock_movements', 'product_batch_id')) {
                $table->dropForeign(['product_batch_id']);
                $table->dropColumn('product_batch_id');
            }
            if (Schema::hasColumn('stock_movements', 'product_serial_number_id')) {
                $table->dropForeign(['product_serial_number_id']);
                $table->dropColumn('product_serial_number_id');
            }
        });
    }
};

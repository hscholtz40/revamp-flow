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
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->foreignId('product_batch_id')->nullable()->after('product_id')->constrained('product_batches')->onDelete('set null');
            $table->json('serial_number_ids')->nullable()->after('product_batch_id'); // Array of serial number IDs received
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropForeign(['product_batch_id']);
            $table->dropColumn(['product_batch_id', 'serial_number_ids']);
        });
    }
};

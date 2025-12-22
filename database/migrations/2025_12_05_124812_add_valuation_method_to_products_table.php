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
        Schema::table('products', function (Blueprint $table) {
            $table->enum('valuation_method', ['fifo', 'lifo', 'average_cost'])->default('fifo')->after('cost');
            $table->boolean('track_batches')->default(false)->after('track_stock');
            $table->boolean('track_serial_numbers')->default(false)->after('track_batches');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['valuation_method', 'track_batches', 'track_serial_numbers']);
        });
    }
};

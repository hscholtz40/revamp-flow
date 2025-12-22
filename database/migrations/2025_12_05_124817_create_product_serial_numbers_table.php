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
        Schema::create('product_serial_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('serial_number')->unique(); // Serial number
            $table->foreignId('batch_id')->nullable()->constrained('product_batches')->onDelete('set null');
            $table->enum('status', ['available', 'sold', 'returned', 'damaged', 'scrapped'])->default('available');
            $table->foreignId('stock_movement_id')->nullable()->constrained()->onDelete('set null'); // Last movement
            $table->foreignId('invoice_id')->nullable()->constrained()->onDelete('set null'); // If sold
            $table->foreignId('jobcard_id')->nullable()->constrained()->onDelete('set null'); // If used in jobcard
            $table->date('purchase_date')->nullable();
            $table->date('sale_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('company_id');
            $table->index('product_id');
            $table->index('serial_number');
            $table->index('status');
            $table->index('batch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_serial_numbers');
    }
};

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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['product', 'service'])->default('product');
            $table->string('sku')->unique()->nullable(); // Stock Keeping Unit
            $table->decimal('price', 10, 2);
            $table->decimal('cost', 10, 2)->nullable(); // Cost price for profit calculation
            $table->string('unit')->default('piece'); // piece, hour, kg, etc.
            $table->integer('stock_quantity')->default(0); // For products only
            $table->integer('min_stock_level')->default(0); // For inventory management
            $table->boolean('track_stock')->default(true); // Whether to track inventory
            $table->boolean('is_active')->default(true);
            $table->string('category')->nullable();
            $table->json('tags')->nullable(); // For flexible tagging
            $table->string('image_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('type'); // 'in', 'out', 'adjustment', 'transfer'
            $table->integer('quantity'); // Positive for 'in', negative for 'out'
            $table->decimal('unit_cost', 10, 2)->nullable(); // Cost per unit at time of movement
            $table->text('reference')->nullable(); // Reference to invoice, purchase order, etc.
            $table->string('reference_type')->nullable(); // 'purchase_order', 'invoice', 'adjustment', etc.
            $table->foreignId('reference_id')->nullable(); // ID of the reference
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // Who made the movement
            $table->integer('stock_before')->default(0); // Stock level before this movement
            $table->integer('stock_after')->default(0); // Stock level after this movement
            $table->timestamps();
            
            $table->index('company_id');
            $table->index('product_id');
            $table->index(['reference_type', 'reference_id']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};

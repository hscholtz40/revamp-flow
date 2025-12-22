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
            // Add supplier relationship
            if (!Schema::hasColumn('products', 'supplier_id')) {
                $table->foreignId('supplier_id')->nullable()->after('category')->constrained()->onDelete('set null');
            }
            
            // Add barcode
            if (!Schema::hasColumn('products', 'barcode')) {
                $table->string('barcode')->nullable()->after('supplier_id');
            }
            
            // Add low stock threshold (if min_stock_level doesn't exist or we want both)
            if (!Schema::hasColumn('products', 'low_stock_threshold')) {
                $table->integer('low_stock_threshold')->default(10)->after('stock_quantity');
            }
            
            // Add unit of measure (if unit doesn't exist)
            if (!Schema::hasColumn('products', 'unit_of_measure')) {
                $table->string('unit_of_measure')->default('unit')->after('low_stock_threshold');
            }
            
            // Add cost_price and selling_price (if cost and price don't exist with these names)
            if (!Schema::hasColumn('products', 'cost_price')) {
                $table->decimal('cost_price', 10, 2)->default(0)->after('unit_of_measure');
            }
            
            if (!Schema::hasColumn('products', 'selling_price')) {
                $table->decimal('selling_price', 10, 2)->default(0)->after('cost_price');
            }
            
            // Add indexes
            if (Schema::hasColumn('products', 'supplier_id')) {
                $table->index('supplier_id');
            }
            if (Schema::hasColumn('products', 'barcode')) {
                $table->index('barcode');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'supplier_id')) {
                $table->dropForeign(['supplier_id']);
                $table->dropColumn('supplier_id');
            }
            if (Schema::hasColumn('products', 'barcode')) {
                $table->dropColumn('barcode');
            }
            if (Schema::hasColumn('products', 'low_stock_threshold')) {
                $table->dropColumn('low_stock_threshold');
            }
            if (Schema::hasColumn('products', 'unit_of_measure')) {
                $table->dropColumn('unit_of_measure');
            }
            if (Schema::hasColumn('products', 'cost_price')) {
                $table->dropColumn('cost_price');
            }
            if (Schema::hasColumn('products', 'selling_price')) {
                $table->dropColumn('selling_price');
            }
        });
    }
};

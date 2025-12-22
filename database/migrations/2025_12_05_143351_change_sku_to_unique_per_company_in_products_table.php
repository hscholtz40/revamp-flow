<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        
        // First, handle any potential duplicate SKUs within the same company
        // by making them unique (add ID suffix to duplicates)
        if ($driver === 'sqlite') {
            // SQLite doesn't support UPDATE with JOIN, use a subquery approach
            $duplicates = DB::select("
                SELECT p1.id, p1.company_id, p1.sku, p2.min_id
                FROM products p1
                INNER JOIN (
                    SELECT company_id, sku, MIN(id) as min_id
                    FROM products
                    WHERE sku IS NOT NULL
                    GROUP BY company_id, sku
                    HAVING COUNT(*) > 1
                ) p2 ON p1.company_id = p2.company_id 
                    AND p1.sku = p2.sku 
                    AND p1.id != p2.min_id
            ");
            
            foreach ($duplicates as $duplicate) {
                DB::table('products')
                    ->where('id', $duplicate->id)
                    ->update(['sku' => $duplicate->sku . '-' . $duplicate->id]);
            }
        } else {
            // MySQL/MariaDB syntax
            DB::statement('
                UPDATE products p1
                INNER JOIN (
                    SELECT company_id, sku, MIN(id) as min_id
                    FROM products
                    WHERE sku IS NOT NULL
                    GROUP BY company_id, sku
                    HAVING COUNT(*) > 1
                ) p2 ON p1.company_id = p2.company_id 
                    AND p1.sku = p2.sku 
                    AND p1.id != p2.min_id
                SET p1.sku = CONCAT(p1.sku, "-", p1.id)
            ');
        }
        
        Schema::table('products', function (Blueprint $table) {
            // Drop the global unique constraint on SKU if it exists
            if (Schema::hasIndex('products', 'products_sku_unique')) {
                $table->dropUnique(['sku']);
            }
            
            // Add composite unique constraint on (company_id, sku)
            // This allows the same SKU to exist in different companies
            // Note: NULL values are allowed and won't conflict
            $table->unique(['company_id', 'sku'], 'products_company_sku_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Drop the composite unique constraint
            $table->dropUnique('products_company_sku_unique');
            
            // Restore the global unique constraint on SKU
            $table->unique('sku');
        });
    }
};

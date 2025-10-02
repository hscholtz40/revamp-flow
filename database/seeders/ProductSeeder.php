<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            // Products
            [
                'name' => 'Premium Laptop',
                'description' => 'High-performance laptop with 16GB RAM and 512GB SSD',
                'type' => 'product',
                'sku' => 'LAPTOP-001',
                'price' => 1299.99,
                'cost' => 899.99,
                'unit' => 'piece',
                'stock_quantity' => 25,
                'min_stock_level' => 5,
                'track_stock' => true,
                'is_active' => true,
                'category' => 'Electronics',
                'tags' => ['laptop', 'computer', 'premium'],
            ],
            [
                'name' => 'Wireless Mouse',
                'description' => 'Ergonomic wireless mouse with USB receiver',
                'type' => 'product',
                'sku' => 'MOUSE-001',
                'price' => 29.99,
                'cost' => 15.99,
                'unit' => 'piece',
                'stock_quantity' => 100,
                'min_stock_level' => 20,
                'track_stock' => true,
                'is_active' => true,
                'category' => 'Electronics',
                'tags' => ['mouse', 'wireless', 'accessory'],
            ],
            [
                'name' => 'Office Chair',
                'description' => 'Ergonomic office chair with lumbar support',
                'type' => 'product',
                'sku' => 'CHAIR-001',
                'price' => 199.99,
                'cost' => 120.00,
                'unit' => 'piece',
                'stock_quantity' => 15,
                'min_stock_level' => 3,
                'track_stock' => true,
                'is_active' => true,
                'category' => 'Furniture',
                'tags' => ['chair', 'office', 'ergonomic'],
            ],
            [
                'name' => 'Coffee Beans',
                'description' => 'Premium roasted coffee beans, 1kg bag',
                'type' => 'product',
                'sku' => 'COFFEE-001',
                'price' => 24.99,
                'cost' => 12.50,
                'unit' => 'kg',
                'stock_quantity' => 50,
                'min_stock_level' => 10,
                'track_stock' => true,
                'is_active' => true,
                'category' => 'Food & Beverage',
                'tags' => ['coffee', 'beans', 'premium'],
            ],
            [
                'name' => 'Notebook Set',
                'description' => 'Set of 3 premium notebooks with leather cover',
                'type' => 'product',
                'sku' => 'NOTEBOOK-001',
                'price' => 39.99,
                'cost' => 22.00,
                'unit' => 'set',
                'stock_quantity' => 30,
                'min_stock_level' => 5,
                'track_stock' => true,
                'is_active' => true,
                'category' => 'Office Supplies',
                'tags' => ['notebook', 'writing', 'premium'],
            ],

            // Services
            [
                'name' => 'Website Development',
                'description' => 'Custom website development and design services',
                'type' => 'service',
                'sku' => 'WEB-DEV-001',
                'price' => 150.00,
                'cost' => 75.00,
                'unit' => 'hour',
                'stock_quantity' => 0,
                'min_stock_level' => 0,
                'track_stock' => false,
                'is_active' => true,
                'category' => 'IT Services',
                'tags' => ['web', 'development', 'design'],
            ],
            [
                'name' => 'Consulting Services',
                'description' => 'Business consulting and strategy development',
                'type' => 'service',
                'sku' => 'CONSULT-001',
                'price' => 200.00,
                'cost' => 100.00,
                'unit' => 'hour',
                'stock_quantity' => 0,
                'min_stock_level' => 0,
                'track_stock' => false,
                'is_active' => true,
                'category' => 'Consulting',
                'tags' => ['consulting', 'business', 'strategy'],
            ],
            [
                'name' => 'Technical Support',
                'description' => '24/7 technical support and maintenance',
                'type' => 'service',
                'sku' => 'SUPPORT-001',
                'price' => 75.00,
                'cost' => 35.00,
                'unit' => 'hour',
                'stock_quantity' => 0,
                'min_stock_level' => 0,
                'track_stock' => false,
                'is_active' => true,
                'category' => 'IT Services',
                'tags' => ['support', 'technical', 'maintenance'],
            ],
            [
                'name' => 'Training Workshop',
                'description' => 'Professional training and workshop sessions',
                'type' => 'service',
                'sku' => 'TRAINING-001',
                'price' => 500.00,
                'cost' => 200.00,
                'unit' => 'day',
                'stock_quantity' => 0,
                'min_stock_level' => 0,
                'track_stock' => false,
                'is_active' => true,
                'category' => 'Education',
                'tags' => ['training', 'workshop', 'education'],
            ],
            [
                'name' => 'Marketing Campaign',
                'description' => 'Complete digital marketing campaign management',
                'type' => 'service',
                'sku' => 'MARKETING-001',
                'price' => 2500.00,
                'cost' => 1000.00,
                'unit' => 'month',
                'stock_quantity' => 0,
                'min_stock_level' => 0,
                'track_stock' => false,
                'is_active' => true,
                'category' => 'Marketing',
                'tags' => ['marketing', 'digital', 'campaign'],
            ],
        ];

        foreach ($products as $productData) {
            Product::create($productData);
        }
    }
}

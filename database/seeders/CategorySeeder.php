<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Electronics',
                'description' => 'Electronic devices and accessories',
                'color' => '#3B82F6',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Furniture',
                'description' => 'Office and home furniture',
                'color' => '#10B981',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Food & Beverage',
                'description' => 'Food items and beverages',
                'color' => '#F59E0B',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Office Supplies',
                'description' => 'Office equipment and supplies',
                'color' => '#8B5CF6',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'IT Services',
                'description' => 'Information technology services',
                'color' => '#06B6D4',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Consulting',
                'description' => 'Business consulting services',
                'color' => '#84CC16',
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'Education',
                'description' => 'Training and educational services',
                'color' => '#F97316',
                'is_active' => true,
                'sort_order' => 7,
            ],
            [
                'name' => 'Marketing',
                'description' => 'Marketing and advertising services',
                'color' => '#EC4899',
                'is_active' => true,
                'sort_order' => 8,
            ],
        ];

        foreach ($categories as $categoryData) {
            Category::create($categoryData);
        }
    }
}

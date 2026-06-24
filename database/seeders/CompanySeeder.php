<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = [
            [
                'name' => 'Revamp (Change in Admin)',
                'email' => 'admin@revamp.house',
                'phone' => '+27721234567',
                'address' => '',
                'city' => '',
                'state' => '',
                'postal_code' => '',
                'country' => '',
                'website' => 'https://revamp.house',
                'description' => 'Leading provider of innovative business solutions',
                'is_active' => true,
                'is_default' => true,
            ],
        ];

        foreach ($companies as $companyData) {
            Company::firstOrCreate(
                ['name' => $companyData['name']],
                $companyData
            );
        }
    }
}

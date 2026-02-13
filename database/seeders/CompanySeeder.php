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
                'name' => 'Nexora Software',
                'email' => 'info@nexorasoftware.co.za',
                'phone' => '+27728755541',
                'address' => '',
                'city' => '',
                'state' => '',
                'postal_code' => '',
                'country' => '',
                'website' => 'https://nexorasoftware.co.za',
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

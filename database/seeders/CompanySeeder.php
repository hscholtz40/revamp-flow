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
                'name' => 'Acme Corporation',
                'email' => 'info@acme.com',
                'phone' => '+1 (555) 123-4567',
                'address' => '123 Business Street',
                'city' => 'New York',
                'state' => 'NY',
                'postal_code' => '10001',
                'country' => 'United States',
                'website' => 'https://acme.com',
                'description' => 'Leading provider of innovative business solutions',
                'is_active' => true,
                'is_default' => true,
            ],
            [
                'name' => 'TechStart Solutions',
                'email' => 'hello@techstart.com',
                'phone' => '+1 (555) 987-6543',
                'address' => '456 Innovation Drive',
                'city' => 'San Francisco',
                'state' => 'CA',
                'postal_code' => '94105',
                'country' => 'United States',
                'website' => 'https://techstart.com',
                'description' => 'Cutting-edge technology solutions for modern businesses',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Global Services Ltd',
                'email' => 'contact@globalservices.com',
                'phone' => '+44 20 7123 4567',
                'address' => '789 International Plaza',
                'city' => 'London',
                'state' => 'England',
                'postal_code' => 'SW1A 1AA',
                'country' => 'United Kingdom',
                'website' => 'https://globalservices.com',
                'description' => 'International business consulting and services',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Digital Innovations Inc',
                'email' => 'info@digitalinnovations.com',
                'phone' => '+1 (555) 456-7890',
                'address' => '321 Digital Way',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '73301',
                'country' => 'United States',
                'website' => 'https://digitalinnovations.com',
                'description' => 'Digital transformation and innovation consulting',
                'is_active' => false,
                'is_default' => false,
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

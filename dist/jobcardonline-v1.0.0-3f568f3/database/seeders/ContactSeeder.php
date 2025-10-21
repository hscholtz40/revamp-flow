<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Customer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ContactSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing customers
        $customers = Customer::all();
        
        if ($customers->isEmpty()) {
            $this->command->info('No customers found. Please run the customer seeder first.');
            return;
        }

        // Create sample contacts for each customer
        foreach ($customers as $customer) {
            // Create 1-3 contacts per customer
            $contactCount = rand(1, 3);
            
            for ($i = 0; $i < $contactCount; $i++) {
                Contact::create([
                    'customer_id' => $customer->id,
                    'name' => fake()->name(),
                    'email' => fake()->optional(0.8)->email(),
                    'phone' => fake()->optional(0.7)->phoneNumber(),
                    'position' => fake()->optional(0.6)->jobTitle(),
                    'notes' => fake()->optional(0.3)->sentence(),
                    'is_primary' => $i === 0, // First contact is primary
                ]);
            }
        }
        
        $this->command->info('Contacts seeded successfully!');
    }
}

<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminEmail = env('ADMIN_EMAIL', 'admin@example.com');
        $adminPassword = env('ADMIN_PASSWORD', 'password');

        // Ensure Admin group exists (GroupSeeder also handles this, but safe here)
        $adminGroup = Group::firstOrCreate(
            ['name' => 'Admin'],
            [
                'description' => 'Full system administrator with access to all functions',
                'is_administrator' => true,
            ]
        );

        // Create admin user if missing
        $admin = User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Administrator',
                'password' => Hash::make($adminPassword),
                'email_verified_at' => now(),
            ]
        );

        // Attach admin to Admin group
        $admin->groups()->syncWithoutDetaching([$adminGroup->id]);

        // Attach admin to default company and set as current_company_id if available
        $defaultCompany = Company::getDefault();
        if ($defaultCompany) {
            $admin->companies()->syncWithoutDetaching([$defaultCompany->id]);
            if (!$admin->current_company_id) {
                $admin->current_company_id = $defaultCompany->id;
                $admin->save();
            }
        }
    }
}



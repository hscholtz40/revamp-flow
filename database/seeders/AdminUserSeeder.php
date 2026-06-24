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
        $adminEmail = config('installer.bootstrap_admin_email') ?: env('ADMIN_EMAIL');
        $adminPassword = config('installer.bootstrap_admin_password') ?: env('ADMIN_PASSWORD');
        $adminName = config('installer.bootstrap_admin_name') ?: env('ADMIN_NAME', 'Administrator');
        $mustResetPassword = filter_var(
            config('installer.bootstrap_admin_must_reset_password') ?: env('ADMIN_MUST_RESET_PASSWORD', false),
            FILTER_VALIDATE_BOOL
        );

        if (! is_string($adminEmail) || $adminEmail === '' || ! is_string($adminPassword) || $adminPassword === '') {
            if ($this->command) {
                $this->command->warn('AdminUserSeeder skipped: set ADMIN_EMAIL and ADMIN_PASSWORD in the environment for this run only, or run the web installer (credentials are not stored in .env).');
            }

            return;
        }

        // Ensure Admin group exists (GroupSeeder also handles this, but safe here)
        $adminGroup = Group::firstOrCreate(
            ['name' => 'Admin'],
            [
                'description' => 'Full system administrator with access to all functions',
                'is_administrator' => true,
            ]
        );

        // Create or refresh admin user for bootstrap (e.g. license deploy)
        $admin = User::updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => is_string($adminName) && $adminName !== '' ? $adminName : 'Administrator',
                'password' => Hash::make($adminPassword),
                'email_verified_at' => now(),
                'must_reset_password' => $mustResetPassword,
            ]
        );

        // Attach admin to Admin group
        $admin->groups()->syncWithoutDetaching([$adminGroup->id]);

        // Attach admin to default company and set as current_company_id if available
        $defaultCompany = Company::getDefault();
        if ($defaultCompany) {
            $admin->companies()->syncWithoutDetaching([$defaultCompany->id]);
            if (! $admin->current_company_id) {
                $admin->current_company_id = $defaultCompany->id;
                $admin->save();
            }
        }
    }
}

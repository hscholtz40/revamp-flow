<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\GroupPermission;
use App\Models\User;
use Illuminate\Database\Seeder;

class GroupSeeder extends Seeder
{
    public function run(): void
    {
        // Create all groups
        $groups = [
            'Admin' => Group::firstOrCreate(['name' => 'Admin'], [
                'description' => 'Full system administrator with access to all functions',
                'is_administrator' => true,
            ]),
            'Manager' => Group::firstOrCreate(['name' => 'Manager'], [
                'description' => 'Manager with elevated permissions',
                'is_administrator' => false,
            ]),
            'Sales' => Group::firstOrCreate(['name' => 'Sales'], [
                'description' => 'Sales team member',
                'is_administrator' => false,
            ]),
            'Backoffice' => Group::firstOrCreate(['name' => 'Backoffice'], [
                'description' => 'Backoffice staff member',
                'is_administrator' => false,
            ]),
        ];

        $modules = ['customers', 'users', 'groups', 'contacts', 'products', 'suppliers', 'stock-movements', 'purchase-orders', 'jobcards', 'quotes', 'invoices', 'credit-notes', 'timesheet', 'messages', 'dispatch', 'tasks', 'queries', 'registered-users', 'customer-update-requests'];

        // Add permissions for all groups
        foreach ($groups as $groupName => $group) {
            foreach ($modules as $module) {
                if (in_array($module, ['registered-users', 'customer-update-requests'], true)) {
                    $grant = (bool) $group->is_administrator;
                    GroupPermission::updateOrCreate(
                        ['group_id' => $group->id, 'module' => $module],
                        [
                            'can_list' => $grant,
                            'can_view' => $grant,
                            'can_create' => false,
                            'can_edit' => false,
                            'can_delete' => false,
                            'can_edit_completed' => false,
                            'can_edit_salesperson' => false,
                            'can_approve' => $grant,
                        ]
                    );

                    continue;
                }

                $permissions = [
                    'can_view' => true,
                    'can_list' => true,
                    'can_create' => true,
                    'can_edit' => true,
                    'can_delete' => true,
                ];

                // Add special permission for editing completed items
                if (in_array($module, ['jobcards', 'invoices', 'quotes', 'credit-notes'])) {
                    $permissions['can_edit_completed'] = in_array($groupName, ['Admin', 'Manager']);
                }

                // Add special permission for editing salesperson on invoices (default to false, can be configured per group)
                if ($module === 'invoices') {
                    $permissions['can_edit_salesperson'] = false;
                }

                GroupPermission::updateOrCreate(
                    ['group_id' => $group->id, 'module' => $module],
                    $permissions
                );
            }
        }

        // Assign first user to Admin group
        if ($firstUser = User::query()->orderBy('id')->first()) {
            $firstUser->groups()->syncWithoutDetaching([$groups['Admin']->id]);
        }
    }
}

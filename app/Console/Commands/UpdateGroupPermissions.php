<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Models\GroupPermission;
use Illuminate\Console\Command;

class UpdateGroupPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update group permissions to include new inventory modules';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Updating group permissions...');
        
        // Get all groups
        $groups = Group::all();
        
        $this->info('Found ' . $groups->count() . ' groups:');
        foreach ($groups as $group) {
            $this->line('- ' . $group->name . ' (ID: ' . $group->id . ')');
        }
        
        // Define modules and their permissions
        $modules = ['customers', 'users', 'groups', 'contacts', 'products', 'suppliers', 'stock-movements', 'purchase-orders', 'jobcards', 'quotes', 'invoices', 'credit-notes', 'reports', 'timesheet'];
        
        foreach ($groups as $group) {
            $this->info('Updating permissions for group: ' . $group->name);
            
            foreach ($modules as $module) {
                $permission = GroupPermission::updateOrCreate(
                    ['group_id' => $group->id, 'module' => $module],
                    [
                        'can_view' => true,
                        'can_list' => true,
                        'can_create' => true,
                        'can_edit' => true,
                        'can_delete' => true,
                    ]
                );
                
                $this->line('  ✓ Added/Updated ' . $module . ' permissions');
            }
        }
        
        $this->info('Group permissions updated successfully!');
        
        // Show summary
        $totalPermissions = GroupPermission::count();
        $this->info('Total permissions in database: ' . $totalPermissions);
    }
}

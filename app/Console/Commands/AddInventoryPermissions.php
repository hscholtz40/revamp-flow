<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Models\GroupPermission;
use Illuminate\Console\Command;

class AddInventoryPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:add-inventory';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add inventory module permissions (suppliers, stock-movements, purchase-orders) to all existing groups';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Adding inventory module permissions to all groups...');
        
        // Get all groups
        $groups = Group::all();
        
        if ($groups->isEmpty()) {
            $this->warn('No groups found. Please create groups first.');
            return 1;
        }
        
        $this->info('Found ' . $groups->count() . ' groups:');
        foreach ($groups as $group) {
            $this->line('- ' . $group->name . ' (ID: ' . $group->id . ')');
        }
        
        // Define new inventory modules
        $modules = ['suppliers', 'stock-movements', 'purchase-orders'];
        
        foreach ($groups as $group) {
            $this->info('Updating permissions for group: ' . $group->name);
            
            foreach ($modules as $module) {
                // Check if permission already exists
                $existing = GroupPermission::where('group_id', $group->id)
                    ->where('module', $module)
                    ->first();
                
                if ($existing) {
                    $this->line('  ⊙ ' . $module . ' permissions already exist (skipping)');
                    continue;
                }
                
                // Create permissions with default values (all true, matching GroupSeeder pattern)
                $permission = GroupPermission::create([
                    'group_id' => $group->id,
                    'module' => $module,
                    'can_view' => true,
                    'can_list' => true,
                    'can_create' => true,
                    'can_edit' => true,
                    'can_delete' => true,
                ]);
                
                $this->line('  ✓ Added ' . $module . ' permissions');
            }
        }
        
        $this->info('Inventory permissions added successfully!');
        
        // Show summary
        $totalPermissions = GroupPermission::whereIn('module', $modules)->count();
        $this->info('Total inventory permissions in database: ' . $totalPermissions);
        
        return 0;
    }
}

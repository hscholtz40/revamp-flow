<?php

namespace App\Console\Commands;

use App\Models\GroupPermission;
use App\Models\User;
use Illuminate\Console\Command;

class TestPermissionError extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:permission-error {--restore : Restore permissions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test permission error handling by temporarily removing contacts permissions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('restore')) {
            $this->restorePermissions();
            return;
        }

        $this->removePermissions();
    }

    private function removePermissions()
    {
        $this->info('Removing contacts permissions from all groups...');
        
        $removed = GroupPermission::where('module', 'contacts')->delete();
        
        $this->info("Removed {$removed} contacts permissions.");
        $this->warn('Now try to access /contacts/create?customer_id=1 to see the permission error page.');
    }

    private function restorePermissions()
    {
        $this->info('Restoring contacts permissions...');
        
        // Get all groups
        $groups = \App\Models\Group::all();
        
        foreach ($groups as $group) {
            GroupPermission::updateOrCreate(
                ['group_id' => $group->id, 'module' => 'contacts'],
                [
                    'can_view' => true,
                    'can_list' => true,
                    'can_create' => true,
                    'can_edit' => true,
                    'can_delete' => true,
                ]
            );
        }
        
        $this->info('Contacts permissions restored for all groups.');
    }
}

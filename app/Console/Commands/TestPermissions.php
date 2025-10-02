<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Group;
use Illuminate\Console\Command;

class TestPermissions extends Command
{
    protected $signature = 'test:permissions';
    protected $description = 'Test jobcard permissions';

    public function handle()
    {
        $this->info('Testing Jobcard Permissions...');
        
        // Test current user
        $user = User::first();
        if ($user) {
            $this->info("User: {$user->name}");
            $this->info("Groups: " . $user->groups->pluck('name')->implode(', '));
            $this->info("Can edit completed jobcards: " . ($user->canEditCompletedJobcards() ? 'YES' : 'NO'));
        }
        
        // Test all groups
        $this->info("\nGroup Permissions:");
        $groups = Group::with('permissions')->get();
        foreach ($groups as $group) {
            $jobcardPerm = $group->permissions->where('module', 'jobcards')->first();
            if ($jobcardPerm) {
                $this->info("{$group->name}: can_edit_completed = " . ($jobcardPerm->can_edit_completed ? 'YES' : 'NO'));
            }
        }
        
        // Create test user in Sales group
        $this->info("\nCreating test user in Sales group...");
        $salesUser = User::firstOrCreate(
            ['email' => 'sales@test.com'],
            [
                'name' => 'Sales Test User',
                'password' => bcrypt('password')
            ]
        );
        
        $salesGroup = Group::where('name', 'Sales')->first();
        $salesUser->groups()->syncWithoutDetaching([$salesGroup->id]);
        
        $this->info("Created user: {$salesUser->name}");
        $this->info("User groups: " . $salesUser->groups->pluck('name')->implode(', '));
        $this->info("Can edit completed jobcards: " . ($salesUser->canEditCompletedJobcards() ? 'YES' : 'NO'));
        
        // Test with a completed jobcard
        $this->info("\nTesting with completed jobcard...");
        $jobcard = \App\Models\Jobcard::first();
        if ($jobcard) {
            $jobcard->status = 'completed';
            $jobcard->save();
            
            $this->info("Jobcard status set to: {$jobcard->status}");
            $this->info("Admin user can delete completed jobcard: " . ($user->canEditCompletedJobcards() ? 'YES' : 'NO'));
            $this->info("Sales user can delete completed jobcard: " . ($salesUser->canEditCompletedJobcards() ? 'YES' : 'NO'));
        }
        
        return 0;
    }
}
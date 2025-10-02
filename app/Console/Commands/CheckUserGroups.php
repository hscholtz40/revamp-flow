<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Models\User;
use Illuminate\Console\Command;

class CheckUserGroups extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:check-groups';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check which users belong to which groups';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('User Group Assignments:');
        $this->line('');
        
        $users = User::with('groups')->get();
        
        foreach ($users as $user) {
            $this->line('User: ' . $user->name . ' (' . $user->email . ')');
            
            if ($user->groups->count() > 0) {
                $groupNames = $user->groups->pluck('name')->join(', ');
                $this->line('  Groups: ' . $groupNames);
            } else {
                $this->line('  Groups: None assigned');
            }
            $this->line('');
        }
        
        $this->info('Available Groups:');
        $groups = Group::all();
        foreach ($groups as $group) {
            $userCount = $group->users()->count();
            $this->line('- ' . $group->name . ' (' . $userCount . ' users)');
        }
    }
}

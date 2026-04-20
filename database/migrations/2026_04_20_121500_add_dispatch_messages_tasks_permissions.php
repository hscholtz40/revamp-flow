<?php

use App\Models\Group;
use App\Models\GroupPermission;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $modules = ['messages', 'dispatch', 'tasks'];

        foreach (Group::query()->get(['id', 'is_administrator']) as $group) {
            foreach ($modules as $module) {
                GroupPermission::query()->updateOrCreate(
                    ['group_id' => $group->id, 'module' => $module],
                    [
                        'can_list' => true,
                        'can_view' => true,
                        'can_create' => true,
                        'can_edit' => true,
                        'can_delete' => true,
                        'can_edit_completed' => false,
                        'can_edit_salesperson' => false,
                        'can_approve' => false,
                    ]
                );
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        GroupPermission::query()
            ->whereIn('module', ['messages', 'dispatch', 'tasks'])
            ->delete();
    }
};

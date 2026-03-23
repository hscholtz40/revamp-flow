<?php

use App\Models\Group;
use App\Models\GroupPermission;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Ensure every group has a timesheet permission row so time-entry routes can enforce module middleware.
     */
    public function up(): void
    {
        foreach (Group::query()->cursor() as $group) {
            GroupPermission::firstOrCreate(
                ['group_id' => $group->id, 'module' => 'timesheet'],
                [
                    'can_view' => true,
                    'can_list' => true,
                    'can_create' => true,
                    'can_edit' => true,
                    'can_delete' => true,
                ]
            );
        }
    }

    public function down(): void
    {
        GroupPermission::query()->where('module', 'timesheet')->delete();
    }
};

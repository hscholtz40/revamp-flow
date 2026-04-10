<?php

use App\Models\Group;
use App\Models\GroupPermission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_permissions', function (Blueprint $table) {
            $table->boolean('can_approve')->default(false)->after('can_edit_salesperson');
        });

        foreach (Group::query()->cursor() as $group) {
            $grant = (bool) $group->is_administrator;
            foreach (['registered-users', 'customer-update-requests'] as $module) {
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
            }
        }
    }

    public function down(): void
    {
        GroupPermission::query()
            ->whereIn('module', ['registered-users', 'customer-update-requests'])
            ->delete();

        Schema::table('group_permissions', function (Blueprint $table) {
            $table->dropColumn('can_approve');
        });
    }
};

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupPermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id', 'module', 'can_view', 'can_list', 'can_create', 'can_edit', 'can_delete', 'can_edit_completed', 'can_edit_salesperson', 'can_approve',
    ];

    protected $casts = [
        'can_view' => 'boolean',
        'can_list' => 'boolean',
        'can_create' => 'boolean',
        'can_edit' => 'boolean',
        'can_delete' => 'boolean',
        'can_edit_completed' => 'boolean',
        'can_edit_salesperson' => 'boolean',
        'can_approve' => 'boolean',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}

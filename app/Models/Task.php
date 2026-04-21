<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Task extends Model
{
    protected $fillable = [
        'company_id',
        'title',
        'description',
        'status',
        'assigned_to_user_id',
        'assigned_to_team_id',
        'jobcard_id',
        'created_by',
        'scheduled_start_at',
        'scheduled_end_at',
        'completed_at',
    ];

    protected $casts = [
        'scheduled_start_at' => 'datetime',
        'scheduled_end_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function notes(): HasMany
    {
        return $this->hasMany(TaskNote::class);
    }

    public function recordNotes(): MorphMany
    {
        return $this->morphMany(Note::class, 'noteable');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function assignedTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'assigned_to_team_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function jobcard(): BelongsTo
    {
        return $this->belongsTo(Jobcard::class);
    }
}

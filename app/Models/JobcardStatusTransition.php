<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobcardStatusTransition extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'jobcard_id',
        'from_status',
        'to_status',
        'transitioned_at',
        'user_id',
    ];

    protected $casts = [
        'transitioned_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function jobcard(): BelongsTo
    {
        return $this->belongsTo(Jobcard::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

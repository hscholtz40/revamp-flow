<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueryAttachment extends Model
{
    protected $fillable = [
        'query_id',
        'path',
        'type',
        'original_name',
    ];

    public function parentQuery(): BelongsTo
    {
        return $this->belongsTo(Query::class, 'query_id');
    }
}

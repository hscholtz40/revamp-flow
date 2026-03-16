<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LineGroup extends Model
{
    protected $fillable = [
        'line_groupable_type',
        'line_groupable_id',
        'name',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function lineGroupable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Create a default line group for a document.
     */
    public static function createDefaultFor($document): self
    {
        return static::create([
            'line_groupable_type' => get_class($document),
            'line_groupable_id' => $document->id,
            'name' => 'Items',
            'sort_order' => 0,
        ]);
    }
}

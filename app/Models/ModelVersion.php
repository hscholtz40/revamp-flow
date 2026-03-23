<?php

namespace App\Models;

use App\Traits\ScopedToCurrentCompanyRouteBinding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ModelVersion extends Model
{
    use ScopedToCurrentCompanyRouteBinding;

    protected $fillable = [
        'user_id',
        'company_id',
        'versionable_type',
        'versionable_id',
        'version_number',
        'data',
        'changes',
        'reason',
        'notes',
        'is_current',
    ];

    protected $casts = [
        'data' => 'array',
        'changes' => 'array',
        'is_current' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who created this version.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the company associated with this version.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the model that was versioned.
     */
    public function versionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to get versions for a specific model.
     */
    public function scopeForModel($query, string $modelType, int $modelId)
    {
        return $query->where('versionable_type', $modelType)
            ->where('versionable_id', $modelId)
            ->orderBy('version_number', 'desc');
    }

    /**
     * Scope to get the current version.
     */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    /**
     * Get the previous version.
     */
    public function previous(): ?self
    {
        return static::where('versionable_type', $this->versionable_type)
            ->where('versionable_id', $this->versionable_id)
            ->where('version_number', '<', $this->version_number)
            ->orderBy('version_number', 'desc')
            ->first();
    }

    /**
     * Get the next version.
     */
    public function next(): ?self
    {
        return static::where('versionable_type', $this->versionable_type)
            ->where('versionable_id', $this->versionable_id)
            ->where('version_number', '>', $this->version_number)
            ->orderBy('version_number', 'asc')
            ->first();
    }

    /**
     * Restore this version's data to the model.
     */
    public function restore(): bool
    {
        $model = $this->versionable;

        if (! $model) {
            return false;
        }

        // Mark all versions as not current
        static::where('versionable_type', $this->versionable_type)
            ->where('versionable_id', $this->versionable_id)
            ->update(['is_current' => false]);

        // Restore the data
        $model->fill($this->data);
        $model->save();

        // Create a new version for the restore
        $newVersion = static::create([
            'user_id' => auth()->id(),
            'company_id' => $this->company_id,
            'versionable_type' => $this->versionable_type,
            'versionable_id' => $this->versionable_id,
            'version_number' => static::where('versionable_type', $this->versionable_type)
                ->where('versionable_id', $this->versionable_id)
                ->max('version_number') + 1,
            'data' => $this->data,
            'reason' => 'Restored from version '.$this->version_number,
            'is_current' => true,
        ]);

        return true;
    }
}

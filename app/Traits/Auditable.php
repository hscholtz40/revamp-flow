<?php

namespace App\Traits;

use App\Models\AuditLog;
use App\Models\ModelVersion;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

trait Auditable
{
    /**
     * Boot the trait.
     */
    public static function bootAuditable(): void
    {
        // Track created events
        static::created(function (Model $model) {
            static::auditCreated($model);
        });

        // Track updated events
        static::updated(function (Model $model) {
            static::auditUpdated($model);
        });

        // Track deleted events
        static::deleted(function (Model $model) {
            static::auditDeleted($model);
        });

        // Track restored events (for soft deletes)
        if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive(static::class))) {
            static::restored(function (Model $model) {
                static::auditRestored($model);
            });
        }
    }

    /**
     * Audit a created event.
     */
    protected static function auditCreated(Model $model): void
    {
        try {
            $service = app(AuditService::class);
            $newValues = $model->getAttributes();
            
            // Remove timestamps and hidden fields
            $newValues = array_diff_key($newValues, array_flip(['created_at', 'updated_at']));
            $newValues = array_diff_key($newValues, array_flip($model->getHidden()));
            
            $service->log('created', $model, null, $newValues);
            
            // Create initial version if model is versionable
            if (static::isVersionable($model)) {
                $service->createVersion($model, 'Initial version');
            }
        } catch (\Exception $e) {
            Log::error('Failed to audit created event: ' . $e->getMessage());
        }
    }

    /**
     * Audit an updated event.
     */
    protected static function auditUpdated(Model $model): void
    {
        try {
            $service = app(AuditService::class);
            
            // Get original values (before update)
            $oldValues = $model->getOriginal();
            $newValues = $model->getAttributes();
            
            // Remove timestamps
            unset($oldValues['updated_at'], $newValues['updated_at']);
            
            // Remove hidden fields
            $oldValues = array_diff_key($oldValues, array_flip($model->getHidden()));
            $newValues = array_diff_key($newValues, array_flip($model->getHidden()));
            
            // Only log if there are actual changes
            $changes = array_diff_assoc($newValues, $oldValues);
            if (!empty($changes)) {
                $service->log('updated', $model, $oldValues, $newValues);
                
                // Create version if model is versionable and significant changes occurred
                if (static::isVersionable($model) && static::shouldCreateVersion($model, $changes)) {
                    $service->createVersion($model, 'Updated', null, $changes);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to audit updated event: ' . $e->getMessage());
        }
    }

    /**
     * Audit a deleted event.
     */
    protected static function auditDeleted(Model $model): void
    {
        try {
            $service = app(AuditService::class);
            $oldValues = $model->getAttributes();
            
            // Remove timestamps and hidden fields
            $oldValues = array_diff_key($oldValues, array_flip(['created_at', 'updated_at', 'deleted_at']));
            $oldValues = array_diff_key($oldValues, array_flip($model->getHidden()));
            
            $service->log('deleted', $model, $oldValues, null);
        } catch (\Exception $e) {
            Log::error('Failed to audit deleted event: ' . $e->getMessage());
        }
    }

    /**
     * Audit a restored event.
     */
    protected static function auditRestored(Model $model): void
    {
        try {
            $service = app(AuditService::class);
            $newValues = $model->getAttributes();
            
            // Remove timestamps and hidden fields
            $newValues = array_diff_key($newValues, array_flip(['created_at', 'updated_at', 'deleted_at']));
            $newValues = array_diff_key($newValues, array_flip($model->getHidden()));
            
            $service->log('restored', $model, null, $newValues);
        } catch (\Exception $e) {
            Log::error('Failed to audit restored event: ' . $e->getMessage());
        }
    }

    /**
     * Check if model should be versioned.
     */
    protected static function isVersionable(Model $model): bool
    {
        // Define which models should have version history
        $versionableModels = [
            \App\Models\Customer::class,
            \App\Models\Invoice::class,
            \App\Models\Quote::class,
            \App\Models\Jobcard::class,
            \App\Models\Product::class,
        ];

        return in_array(get_class($model), $versionableModels);
    }

    /**
     * Determine if a version should be created for this update.
     */
    protected static function shouldCreateVersion(Model $model, array $changes): bool
    {
        // Don't create versions for minor changes like timestamps or metadata
        $ignoredFields = ['updated_at', 'last_accessed_at', 'view_count'];
        
        $significantChanges = array_diff_key($changes, array_flip($ignoredFields));
        
        return !empty($significantChanges);
    }

    /**
     * Get audit logs for this model.
     */
    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /**
     * Get versions for this model.
     */
    public function versions()
    {
        return $this->morphMany(ModelVersion::class, 'versionable');
    }

    /**
     * Get the current version of this model.
     */
    public function currentVersion()
    {
        return $this->versions()->where('is_current', true)->first();
    }
}


<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ModelVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Log an audit event.
     */
    public function log(
        string $event,
        Model $model,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
        ?array $metadata = null
    ): AuditLog {
        $user = Auth::user();
        $companyId = $user?->getCurrentCompany()?->id;
        
        // Determine changed fields
        $changedFields = null;
        if ($oldValues && $newValues) {
            $changedFields = array_keys(array_diff_assoc($newValues, $oldValues));
        }

        return AuditLog::create([
            'user_id' => $user?->id,
            'company_id' => $companyId,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->id,
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changed_fields' => $changedFields,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'url' => Request::fullUrl(),
            'method' => Request::method(),
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Create a version snapshot of a model.
     */
    public function createVersion(
        Model $model,
        ?string $reason = null,
        ?string $notes = null,
        ?array $changes = null
    ): ModelVersion {
        $user = Auth::user();
        $companyId = $user?->getCurrentCompany()?->id;
        
        // Get current version number
        $currentVersion = ModelVersion::where('versionable_type', get_class($model))
            ->where('versionable_id', $model->id)
            ->max('version_number') ?? 0;

        // Mark all previous versions as not current
        ModelVersion::where('versionable_type', get_class($model))
            ->where('versionable_id', $model->id)
            ->update(['is_current' => false]);

        // Get model data (excluding hidden attributes)
        $data = $model->getAttributes();
        
        // Include relationships if needed
        if (method_exists($model, 'getVersionableAttributes')) {
            $data = array_merge($data, $model->getVersionableAttributes());
        }

        return ModelVersion::create([
            'user_id' => $user?->id,
            'company_id' => $companyId,
            'versionable_type' => get_class($model),
            'versionable_id' => $model->id,
            'version_number' => $currentVersion + 1,
            'data' => $data,
            'changes' => $changes,
            'reason' => $reason,
            'notes' => $notes,
            'is_current' => true,
        ]);
    }

    /**
     * Get audit logs for a model.
     */
    public function getAuditLogs(Model $model, ?int $limit = null)
    {
        $query = AuditLog::forModel(get_class($model), $model->id)
            ->with('user')
            ->orderBy('created_at', 'desc');

        if ($limit) {
            return $query->limit($limit)->get();
        }

        return $query->get();
    }

    /**
     * Get versions for a model.
     */
    public function getVersions(Model $model)
    {
        return ModelVersion::forModel(get_class($model), $model->id)
            ->with('user')
            ->get();
    }

    /**
     * Get the current version of a model.
     */
    public function getCurrentVersion(Model $model): ?ModelVersion
    {
        return ModelVersion::where('versionable_type', get_class($model))
            ->where('versionable_id', $model->id)
            ->where('is_current', true)
            ->first();
    }
}


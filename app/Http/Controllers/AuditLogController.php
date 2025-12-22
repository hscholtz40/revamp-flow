<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ModelVersion;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Display a listing of audit logs.
     */
    public function index(Request $request): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        $query = AuditLog::query()
            ->with(['user', 'company', 'auditable'])
            ->when($currentCompany, function ($q) use ($currentCompany) {
                $q->where('company_id', $currentCompany->id);
            });

        // Apply filters
        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('model_type')) {
            $query->where('auditable_type', $request->model_type);
        }

        if ($request->filled('model_id')) {
            $query->where('auditable_id', $request->model_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $auditLogs = $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Get users for filter dropdown (users who have made changes)
        $userIds = AuditLog::query()
            ->when($currentCompany, function ($q) use ($currentCompany) {
                $q->where('company_id', $currentCompany->id);
            })
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');
        
        $users = \App\Models\User::whereIn('id', $userIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        // Get unique model types from audit logs
        $modelTypes = AuditLog::query()
            ->when($currentCompany, function ($q) use ($currentCompany) {
                $q->where('company_id', $currentCompany->id);
            })
            ->distinct()
            ->pluck('auditable_type')
            ->map(function ($type) {
                return [
                    'value' => $type,
                    'label' => class_basename($type),
                ];
            })
            ->sortBy('label')
            ->values();

        return Inertia::render('audit-logs/Index', [
            'auditLogs' => $auditLogs,
            'users' => $users,
            'modelTypes' => $modelTypes,
            'filters' => [
                'event' => $request->input('event', ''),
                'user_id' => $request->input('user_id', ''),
                'model_type' => $request->input('model_type', ''),
                'model_id' => $request->input('model_id', ''),
                'date_from' => $request->input('date_from', ''),
                'date_to' => $request->input('date_to', ''),
                'search' => $request->input('search', ''),
            ],
            'currentCompany' => $currentCompany,
        ]);
    }

    /**
     * Show audit logs for a specific model.
     */
    public function forModel(Request $request, string $modelType, int $modelId): Response
    {
        $auditLogs = AuditLog::forModel($modelType, $modelId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('audit-logs/ModelLogs', [
            'auditLogs' => $auditLogs,
            'modelType' => $modelType,
            'modelId' => $modelId,
        ]);
    }

    /**
     * Show a specific audit log entry.
     */
    public function show(AuditLog $auditLog): Response
    {
        $auditLog->load(['user', 'company', 'auditable']);

        return Inertia::render('audit-logs/Show', [
            'auditLog' => $auditLog,
        ]);
    }

    /**
     * Get versions for a specific model.
     */
    public function versions(Request $request, string $modelType, int $modelId)
    {
        $versions = ModelVersion::forModel($modelType, $modelId)
            ->with('user')
            ->get();

        return response()->json([
            'versions' => $versions,
        ]);
    }

    /**
     * Restore a specific version.
     */
    public function restoreVersion(ModelVersion $modelVersion)
    {
        if ($modelVersion->restore()) {
            return redirect()->back()->with('success', 'Version restored successfully');
        }

        return redirect()->back()->with('error', 'Failed to restore version');
    }

    /**
     * Export audit logs for compliance reporting.
     */
    public function export(Request $request)
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        $query = AuditLog::query()
            ->with(['user', 'company'])
            ->when($currentCompany, function ($q) use ($currentCompany) {
                $q->where('company_id', $currentCompany->id);
            });

        // Apply same filters as index
        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('model_type')) {
            $query->where('auditable_type', $request->model_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $auditLogs = $query->orderBy('created_at', 'desc')->get();

        // Generate CSV
        $filename = 'audit_logs_' . date('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($auditLogs) {
            $file = fopen('php://output', 'w');
            
            // Header row
            fputcsv($file, [
                'ID',
                'Date',
                'User',
                'Event',
                'Model Type',
                'Model ID',
                'Description',
                'IP Address',
                'URL',
            ]);

            // Data rows
            foreach ($auditLogs as $log) {
                fputcsv($file, [
                    $log->id,
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->user ? $log->user->name : 'System',
                    $log->event,
                    class_basename($log->auditable_type),
                    $log->auditable_id,
                    $log->description,
                    $log->ip_address,
                    $log->url,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}

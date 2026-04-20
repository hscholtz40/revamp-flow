<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $company = $user?->getCurrentCompany();

        return response()->json([
            'user' => $user,
            'abilities' => [
                'jobcards' => [
                    'list' => $user?->hasModulePermission('jobcards', 'list') ?? false,
                    'view' => $user?->hasModulePermission('jobcards', 'view') ?? false,
                    'edit' => $user?->hasModulePermission('jobcards', 'edit') ?? false,
                ],
                'tasks' => [
                    'list' => $user?->hasModulePermission('tasks', 'list') ?? false,
                    'create' => $user?->hasModulePermission('tasks', 'create') ?? false,
                    'edit' => $user?->hasModulePermission('tasks', 'edit') ?? false,
                ],
                'messages' => [
                    'list' => $user?->hasModulePermission('messages', 'list') ?? false,
                    'view' => $user?->hasModulePermission('messages', 'view') ?? false,
                    'create' => $user?->hasModulePermission('messages', 'create') ?? false,
                ],
                'dispatch' => [
                    'list' => $user?->hasModulePermission('dispatch', 'list') ?? false,
                    'create' => $user?->hasModulePermission('dispatch', 'create') ?? false,
                    'edit' => $user?->hasModulePermission('dispatch', 'edit') ?? false,
                ],
            ],
            'current_company' => $company ? [
                'id' => $company->id,
                'name' => $company->name,
            ] : null,
        ]);
    }
}

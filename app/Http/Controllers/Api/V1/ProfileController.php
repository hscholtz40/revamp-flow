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
                    'list' => $user?->hasModulePermission('jobcards', 'list') ?? false,
                    'edit' => $user?->hasModulePermission('jobcards', 'edit') ?? false,
                ],
            ],
            'current_company' => $company ? [
                'id' => $company->id,
                'name' => $company->name,
            ] : null,
        ]);
    }
}

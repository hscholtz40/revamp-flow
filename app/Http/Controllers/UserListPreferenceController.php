<?php

namespace App\Http\Controllers;

use App\Models\UserListPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserListPreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page_key' => ['required', 'string', 'max:255'],
        ]);

        $user = auth()->user();
        $companyId = $user->getCurrentCompany()->id;

        $preference = UserListPreference::query()
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->where('page_key', $validated['page_key'])
            ->first();

        return response()->json([
            'columns' => $preference?->columns ?? [],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page_key' => ['required', 'string', 'max:255'],
            'columns' => ['required', 'array'],
            'columns.*.id' => ['required', 'integer', 'min:0'],
            'columns.*.label' => ['required', 'string', 'max:255'],
            'columns.*.visible' => ['required', 'boolean'],
            'columns.*.order' => ['required', 'integer', 'min:0'],
        ]);

        $user = auth()->user();
        $companyId = $user->getCurrentCompany()->id;

        UserListPreference::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'company_id' => $companyId,
                'page_key' => $validated['page_key'],
            ],
            [
                'columns' => $validated['columns'],
            ]
        );

        return response()->json(['saved' => true]);
    }
}

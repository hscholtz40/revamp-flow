<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;

class TeamsController extends Controller
{
    public function index(Request $request)
    {
        $companyId = (int) ($request->user()->getCurrentCompany()?->id ?? 0);

        return response()->json(
            Team::query()
                ->where('company_id', $companyId)
                ->with(['users:id,name'])
                ->orderBy('name')
                ->get()
        );
    }

    public function assignableUsers(Request $request)
    {
        $companyId = (int) ($request->user()->getCurrentCompany()?->id ?? 0);

        return response()->json(
            User::query()
                ->staffSelectableForCompany($companyId)
                ->select('id', 'name', 'email')
                ->orderBy('name')
                ->get()
        );
    }
}

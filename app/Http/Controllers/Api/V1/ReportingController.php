<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;

class ReportingController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            Report::query()
                ->where('company_id', (int) ($request->user()->getCurrentCompany()?->id ?? 0))
                ->orderByDesc('id')
                ->paginate(25)
        );
    }

    public function data(Request $request, Report $report)
    {
        $companyId = (int) ($request->user()->getCurrentCompany()?->id ?? 0);
        abort_unless((int) $report->company_id === $companyId, 404);

        return response()->json([
            'id' => $report->id,
            'name' => $report->name,
            'entity_type' => $report->entity_type,
            'config' => $report->config,
            'rows' => [],
        ]);
    }
}

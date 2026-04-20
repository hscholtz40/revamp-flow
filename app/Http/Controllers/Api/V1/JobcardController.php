<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Jobcard;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class JobcardController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()?->getCurrentCompany()?->id;

        $query = Jobcard::query()
            ->with(['customer:id,name', 'assignedUser:id,name', 'assignedTeam:id,name'])
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json($query->paginate(25));
    }

    public function show(Jobcard $jobcard)
    {
        $jobcard->load(['customer', 'assignedUser', 'assignedTeam', 'lineItems', 'timeEntries']);

        return response()->json($jobcard);
    }

    public function updateStatus(Request $request, Jobcard $jobcard)
    {
        $payload = $request->validate([
            'status' => ['required', 'in:draft,pending,in_progress,completed,cancelled'],
        ]);

        $jobcard->update($payload);

        return response()->json([
            'message' => 'Status updated',
            'jobcard' => $jobcard->fresh(),
        ]);
    }
}

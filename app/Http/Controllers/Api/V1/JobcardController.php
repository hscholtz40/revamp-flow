<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Jobcard;
use App\Models\JobcardAttachment;
use App\Support\JobcardStatuses;
use App\Models\TimeEntry;
use App\Services\AssignmentNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class JobcardController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->input('company_id') ?: $request->user()?->getCurrentCompany()?->id;
        $user = $request->user();

        $teamIds = $user?->teams()->where('teams.company_id', $companyId)->pluck('teams.id') ?? collect();

        $query = Jobcard::query()
            ->with(['customer:id,name', 'assignedUser:id,name', 'assignedTeam:id,name'])
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where(function ($q) use ($user, $teamIds) {
                $q->where('assigned_to_user_id', $user?->id);
                if ($teamIds->isNotEmpty()) {
                    $q->orWhereIn('assigned_to_team_id', $teamIds);
                }
            })
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json($query->paginate(25));
    }

    public function show(Request $request, $id)
    {
        $companyId = $request->input('company_id') ?? $request->user()?->getCurrentCompany()?->id;

        $jobcard = Jobcard::query()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->first();

        if (! $jobcard) {
            return response()->json(['message' => 'Jobcard not found'], 404);
        }

        $jobcard->load([
            'customer',
            'assignedUser',
            'assignedTeam',
            'lineItems',
            'timeEntries',
            'attachments',
        ]);

        $payload = $jobcard->toArray();
        $payload['attachments'] = $jobcard->attachments
            ->map(fn (JobcardAttachment $attachment) => $this->formatAttachment($attachment))
            ->values()
            ->all();

        return response()->json($payload);
    }

    public function storeAttachments(Request $request, Jobcard $jobcard)
    {
        $this->assertCompanyScope($jobcard);
        $this->assertCanModifyAttachments($request, $jobcard);

        $validated = $request->validate([
            'attachments' => ['required', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:51200', 'mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi,webm'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [
            'attachments.max' => 'You can upload a maximum of 10 files at a time.',
            'attachments.*.max' => 'Each file may not be larger than 50MB.',
            'attachments.*.mimes' => 'Each file must be an image or video.',
        ]);

        $description = $this->normalizeDescription($validated['description'] ?? null);

        $created = [];
        foreach ((array) ($validated['attachments'] ?? $request->file('attachments', [])) as $file) {
            if (! $file) {
                continue;
            }

            $attachment = $jobcard->attachments()->create([
                'path' => $file->store("jobcard-attachments/{$jobcard->company_id}", 'public'),
                'type' => str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'image',
                'original_name' => $file->getClientOriginalName(),
                'description' => $description,
                'uploaded_by' => $request->user()?->id,
            ]);

            $created[] = $this->formatAttachment($attachment);
        }

        return response()->json([
            'message' => 'Attachments uploaded successfully.',
            'attachments' => $created,
        ], 201);
    }

    public function updateAttachment(Request $request, Jobcard $jobcard, JobcardAttachment $attachment)
    {
        $this->assertCompanyScope($jobcard);
        abort_unless((int) $attachment->jobcard_id === (int) $jobcard->id, 404);
        $this->assertCanModifyAttachments($request, $jobcard);

        $validated = $request->validate([
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $attachment->update([
            'description' => $this->normalizeDescription($validated['description'] ?? null),
        ]);

        return response()->json([
            'message' => 'Attachment updated.',
            'attachment' => $this->formatAttachment($attachment->fresh()),
        ]);
    }

    public function destroyAttachment(Request $request, Jobcard $jobcard, JobcardAttachment $attachment)
    {
        $this->assertCompanyScope($jobcard);
        abort_unless((int) $attachment->jobcard_id === (int) $jobcard->id, 404);
        $this->assertCanModifyAttachments($request, $jobcard);

        if ($attachment->path && Storage::disk('public')->exists($attachment->path)) {
            Storage::disk('public')->delete($attachment->path);
        }

        $attachment->delete();

        return response()->json(['message' => 'Attachment deleted.']);
    }

    public function store(Request $request)
    {
        $companyId = (int) ($request->user()?->getCurrentCompany()?->id ?? 0);
        $payload = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'assigned_to_team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable', JobcardStatuses::validationRule()],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
        ]);

        $jobcard = Jobcard::create([
            ...$payload,
            'company_id' => $companyId,
            'job_number' => Jobcard::generateJobNumber($companyId),
            'status' => $payload['status'] ?? 'new',
        ]);

        app(AssignmentNotificationService::class)->notifyJobcardAssignmentIfChanged(
            $companyId,
            $jobcard,
            0,
            0
        );

        return response()->json($jobcard, 201);
    }

    public function update(Request $request, Jobcard $jobcard)
    {
        $this->assertCompanyScope($jobcard);
        $payload = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'assigned_to_team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'scheduled_start_at' => ['nullable', 'date'],
            'scheduled_end_at' => ['nullable', 'date'],
            'dispatch_order' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'priority' => ['sometimes', 'nullable', 'in:low,normal,high,urgent'],
            'estimated_duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:5', 'max:1440'],
        ]);

        $previousAssignedUserId = (int) ($jobcard->assigned_to_user_id ?? 0);
        $previousAssignedTeamId = (int) ($jobcard->assigned_to_team_id ?? 0);

        $jobcard->update($payload);
        $freshJobcard = $jobcard->fresh();

        app(AssignmentNotificationService::class)->notifyJobcardAssignmentIfChanged(
            (int) $jobcard->company_id,
            $freshJobcard,
            $previousAssignedUserId,
            $previousAssignedTeamId
        );

        return response()->json($freshJobcard);
    }

    public function destroy(Jobcard $jobcard)
    {
        $this->assertCompanyScope($jobcard);
        $jobcard->delete();

        return response()->json(['message' => 'Jobcard deleted']);
    }

    public function updateStatus(Request $request, Jobcard $jobcard)
    {
        $this->assertCompanyScope($jobcard);
        $payload = $request->validate([
            'status' => ['required', JobcardStatuses::validationRule()],
        ]);

        $jobcard->update($payload);

        return response()->json([
            'message' => 'Status updated',
            'jobcard' => $jobcard->fresh(),
        ]);
    }

    public function convertTimeEntries(Request $request, Jobcard $jobcard)
    {
        $this->assertCompanyScope($jobcard);
        $entryIds = $request->validate([
            'time_entry_ids' => ['required', 'array', 'min:1'],
            'time_entry_ids.*' => ['integer', 'exists:time_entries,id'],
        ])['time_entry_ids'];

        $entries = TimeEntry::query()
            ->whereIn('id', $entryIds)
            ->where('jobcard_id', $jobcard->id)
            ->get();

        return response()->json([
            'message' => 'Time entries ready for conversion',
            'count' => $entries->count(),
            'entries' => $entries,
        ]);
    }

    private function assertCompanyScope(Jobcard $jobcard): void
    {
        $request = request();
        $companyId = $request->filled('company_id')
            ? (int) $request->input('company_id')
            : (int) ($request->user()?->getCurrentCompany()?->id ?? 0);

        abort_unless($companyId > 0 && (int) $jobcard->company_id === $companyId, 404);
    }

    private function assertCanModifyAttachments(Request $request, Jobcard $jobcard): void
    {
        if ($jobcard->status === 'completed' && ! $request->user()?->canEditCompletedJobcards()) {
            abort(403, 'You do not have permission to update completed jobcards.');
        }
    }

    private function normalizeDescription(mixed $description): ?string
    {
        if ($description === null) {
            return null;
        }

        $trimmed = trim((string) $description);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return array{id: int, url: string, path: string, type: string|null, original_name: string|null, description: string|null, created_at: string|null}
     */
    private function formatAttachment(JobcardAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'url' => url('/storage/'.ltrim((string) $attachment->path, '/')),
            'path' => $attachment->path,
            'type' => $attachment->type,
            'original_name' => $attachment->original_name,
            'description' => $attachment->description,
            'created_at' => $attachment->created_at?->toIso8601String(),
        ];
    }
}

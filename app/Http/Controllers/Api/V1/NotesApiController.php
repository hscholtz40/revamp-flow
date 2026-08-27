<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Jobcard;
use App\Models\Note;
use App\Services\JobcardNoteNotificationService;
use Illuminate\Http\Request;

class NotesApiController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            Note::query()
                ->where('company_id', (int) ($request->user()->getCurrentCompany()?->id ?? 0))
                ->with(['user:id,name'])
                ->orderByDesc('id')
                ->paginate(25)
        );
    }

    public function store(Request $request)
    {
        $payload = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'noteable_type' => ['nullable', 'string'],
            'noteable_id' => ['nullable', 'integer'],
        ]);

        $note = Note::create([
            ...$payload,
            'company_id' => (int) ($request->user()->getCurrentCompany()?->id ?? 0),
            'user_id' => $request->user()->id,
        ]);

        $note->load('user:id,name');

        $this->notifyIfJobcardNote($note, $request->user());

        return response()->json($note, 201);
    }

    public function destroy(Request $request, Note $note)
    {
        $companyId = (int) ($request->user()->getCurrentCompany()?->id ?? 0);
        abort_unless((int) $note->company_id === $companyId, 404);
        $note->delete();

        return response()->json(['message' => 'Note deleted']);
    }

    private function notifyIfJobcardNote(Note $note, $author): void
    {
        if (! $author) {
            return;
        }

        $note->loadMissing('noteable');
        $noteable = $note->noteable;

        if ($noteable instanceof Jobcard) {
            app(JobcardNoteNotificationService::class)->notifyForNote($noteable, $note, $author);
        }
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\MessageCreated;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessagingController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $companyId = $request->user()->getCurrentCompany()?->id;

        $conversations = Conversation::query()
            ->where('company_id', $companyId)
            ->whereHas('participants', fn ($q) => $q->where('user_id', $userId))
            ->with(['participants.user:id,name', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->orderByDesc('last_message_at')
            ->get();

        return response()->json($conversations);
    }

    public function storeConversation(Request $request)
    {
        $payload = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $user = $request->user();

        $conversation = DB::transaction(function () use ($payload, $user) {
            $conversation = Conversation::create([
                'company_id' => $user->getCurrentCompany()?->id,
                'subject' => $payload['subject'] ?? null,
                'created_by' => $user->id,
            ]);

            $participantIds = collect($payload['participant_ids'])->push($user->id)->unique();
            foreach ($participantIds as $participantId) {
                ConversationParticipant::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $participantId,
                ]);
            }

            return $conversation;
        });

        return response()->json($conversation->load('participants.user:id,name'), 201);
    }

    public function show(Conversation $conversation)
    {
        $this->assertConversationScope($conversation);

        return response()->json(
            $conversation->load(['participants.user:id,name', 'messages.sender:id,name'])
        );
    }

    public function storeMessage(Request $request, Conversation $conversation)
    {
        $this->assertConversationScope($conversation);
        $payload = $request->validate([
            'body' => ['required', 'string'],
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $request->user()->id,
            'body' => $payload['body'],
        ]);

        $conversation->update(['last_message_at' => now()]);

        event(new MessageCreated($message->load('sender:id,name')));

        return response()->json($message, 201);
    }

    public function markRead(Request $request, Conversation $conversation)
    {
        $this->assertConversationScope($conversation);

        ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $request->user()->id)
            ->update(['last_read_at' => now()]);

        return response()->json(['message' => 'Conversation marked as read']);
    }

    private function assertConversationScope(Conversation $conversation): void
    {
        $companyId = (int) (request()->user()->getCurrentCompany()?->id ?? 0);
        abort_unless((int) $conversation->company_id === $companyId, 404);
        abort_unless(
            $conversation->participants()->where('user_id', request()->user()->id)->exists(),
            403
        );
    }
}

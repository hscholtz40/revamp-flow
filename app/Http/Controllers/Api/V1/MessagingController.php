<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\MessageCreated;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Http\Controllers\Controller;
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
        return response()->json(
            $conversation->load(['participants.user:id,name', 'messages.sender:id,name'])
        );
    }

    public function storeMessage(Request $request, Conversation $conversation)
    {
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
}

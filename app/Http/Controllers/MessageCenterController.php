<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Inertia\Inertia;
use Inertia\Response;

class MessageCenterController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();
        $companyId = $user?->getCurrentCompany()?->id;

        $conversations = Conversation::query()
            ->where('company_id', $companyId)
            ->whereHas('participants', fn ($query) => $query->where('user_id', $user?->id))
            ->with(['participants.user:id,name', 'messages' => fn ($query) => $query->latest()->limit(1)])
            ->orderByDesc('last_message_at')
            ->get();

        return Inertia::render('messages/Index', [
            'conversations' => $conversations,
        ]);
    }
}

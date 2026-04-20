<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public mixed $message) {}

    public function broadcastOn(): array
    {
        $conversationId = data_get($this->message, 'conversation_id');

        return [
            new PrivateChannel('conversation.'.$conversationId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.created';
    }
}

<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DispatchUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public mixed $payload) {}

    public function broadcastOn(): array
    {
        $companyId = data_get($this->payload, 'company_id');

        return [
            new PrivateChannel('dispatch.company.'.$companyId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'dispatch.updated';
    }
}

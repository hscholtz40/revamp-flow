<?php

namespace App\Notifications;

use App\Channels\PushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AssignmentNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private readonly string $entityType,
        private readonly int $entityId,
        private readonly string $title
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if (method_exists($notifiable, 'devices') && $notifiable->devices()->exists()) {
            $channels[] = PushChannel::class;
        }

        return $channels;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->entityType,
            'entity_id' => $this->entityId,
            'title' => $this->notificationTitle(),
        ];
    }

    /**
     * Build the push notification payload.
     *
     * @return array{title: string, body: string, data: array<string, mixed>}
     */
    public function toPush(object $notifiable): array
    {
        return [
            'title' => $this->notificationTitle(),
            'body' => $this->notificationBody(),
            'data' => [
                'type' => $this->entityType,
                'entity_id' => $this->entityId,
            ],
        ];
    }

    private function notificationTitle(): string
    {
        $title = trim($this->title);

        return match ($this->entityType) {
            'task' => "Task assignment: {$title}",
            'jobcard' => "Jobcard assignment: {$title}",
            default => $title,
        };
    }

    private function notificationBody(): string
    {
        return match ($this->entityType) {
            'task' => 'You have been assigned a new task.',
            'jobcard' => 'You have been assigned a new jobcard.',
            default => 'You have a new notification.',
        };
    }
}

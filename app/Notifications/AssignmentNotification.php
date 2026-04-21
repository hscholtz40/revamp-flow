<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AssignmentNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(private readonly string $entityType, private readonly int $entityId, private readonly string $title) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
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

    private function notificationTitle(): string
    {
        $title = trim($this->title);

        return match ($this->entityType) {
            'task' => "Task assignment: {$title}",
            'jobcard' => "Jobcard assignment: {$title}",
            default => $title,
        };
    }
}

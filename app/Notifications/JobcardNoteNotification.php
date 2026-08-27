<?php

namespace App\Notifications;

use App\Channels\PushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class JobcardNoteNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $jobcardId,
        private readonly string $jobcardLabel,
        private readonly string $noteSubject,
        private readonly string $authorName,
        private readonly string $audience,
    ) {}

    /**
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
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'jobcard_note',
            'entity_id' => $this->jobcardId,
            'title' => $this->notificationTitle(),
            'url' => '/jobcards/'.$this->jobcardId,
        ];
    }

    /**
     * @return array{title: string, body: string, data: array<string, mixed>}
     */
    public function toPush(object $notifiable): array
    {
        return [
            'title' => $this->notificationTitle(),
            'body' => $this->notificationBody(),
            'data' => [
                'type' => 'jobcard_note',
                'entity_id' => $this->jobcardId,
            ],
        ];
    }

    private function notificationTitle(): string
    {
        return match ($this->audience) {
            'admin' => "Jobcard note from assignee: {$this->jobcardLabel}",
            default => "Jobcard note: {$this->jobcardLabel}",
        };
    }

    private function notificationBody(): string
    {
        $subject = trim($this->noteSubject);

        return match ($this->audience) {
            'admin' => "{$this->authorName} added a note".($subject !== '' ? ": {$subject}" : '.'),
            default => "{$this->authorName} added a note".($subject !== '' ? ": {$subject}" : '.'),
        };
    }
}

<?php

namespace App\Services;

use App\Models\Jobcard;
use App\Models\Note;
use App\Models\User;
use App\Notifications\JobcardNoteNotification;
use Illuminate\Support\Collection;

class JobcardNoteNotificationService
{
    public function notifyForNote(Jobcard $jobcard, Note $note, User $author): void
    {
        $label = $jobcard->job_number ?: ('Jobcard #'.$jobcard->id);
        $subject = (string) ($note->subject ?? '');
        $authorName = (string) ($author->name ?: 'A user');

        $isAssignedAuthor = $jobcard->assigned_to_user_id
            && (int) $jobcard->assigned_to_user_id === (int) $author->id;

        if ($isAssignedAuthor) {
            $this->companyAdmins((int) $jobcard->company_id)
                ->reject(fn (User $user) => (int) $user->id === (int) $author->id)
                ->each(fn (User $user) => $user->notify(new JobcardNoteNotification(
                    $jobcard->id,
                    $label,
                    $subject,
                    $authorName,
                    'admin',
                )));

            return;
        }

        // Web / office user note → notify the assigned technician (and team members).
        $this->resolveAssignees((int) $jobcard->company_id, $jobcard)
            ->reject(fn (User $user) => (int) $user->id === (int) $author->id)
            ->each(fn (User $user) => $user->notify(new JobcardNoteNotification(
                $jobcard->id,
                $label,
                $subject,
                $authorName,
                'assignee',
            )));
    }

    /**
     * @return Collection<int, User>
     */
    private function companyAdmins(int $companyId): Collection
    {
        return User::query()
            ->whereHas('companies', fn ($q) => $q->where('companies.id', $companyId))
            ->whereHas('groups', fn ($q) => $q->where('is_administrator', true))
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveAssignees(int $companyId, Jobcard $jobcard): Collection
    {
        return app(AssignmentNotificationService::class)
            ->resolveAssignmentTargets(
                $companyId,
                (int) ($jobcard->assigned_to_user_id ?? 0),
                (int) ($jobcard->assigned_to_team_id ?? 0),
            );
    }
}

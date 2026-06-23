<?php

namespace App\Services;

use App\Models\Jobcard;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Notifications\AssignmentNotification;
use Illuminate\Support\Collection;

class AssignmentNotificationService
{
    public function assignmentChanged(
        int $previousAssignedUserId,
        int $previousAssignedTeamId,
        ?int $nextAssignedUserId,
        ?int $nextAssignedTeamId
    ): bool {
        return (int) ($nextAssignedUserId ?? 0) !== $previousAssignedUserId
            || (int) ($nextAssignedTeamId ?? 0) !== $previousAssignedTeamId;
    }

    public function notifyJobcardAssignmentIfChanged(
        int $companyId,
        Jobcard $jobcard,
        int $previousAssignedUserId,
        int $previousAssignedTeamId
    ): void {
        if (! $this->assignmentChanged(
            $previousAssignedUserId,
            $previousAssignedTeamId,
            $jobcard->assigned_to_user_id,
            $jobcard->assigned_to_team_id
        )) {
            return;
        }

        $this->notifyJobcardAssignment($companyId, $jobcard);
    }

    public function notifyTaskAssignmentIfChanged(
        int $companyId,
        Task $task,
        int $previousAssignedUserId,
        int $previousAssignedTeamId
    ): void {
        if (! $this->assignmentChanged(
            $previousAssignedUserId,
            $previousAssignedTeamId,
            $task->assigned_to_user_id,
            $task->assigned_to_team_id
        )) {
            return;
        }

        $this->notifyTaskAssignment($companyId, $task);
    }

    public function notifyJobcardAssignment(int $companyId, Jobcard $jobcard): void
    {
        if (! $jobcard->assigned_to_user_id && ! $jobcard->assigned_to_team_id) {
            return;
        }

        $this->notifyUsers(
            $companyId,
            (int) ($jobcard->assigned_to_user_id ?? 0),
            (int) ($jobcard->assigned_to_team_id ?? 0),
            new AssignmentNotification(
                'jobcard',
                $jobcard->id,
                $jobcard->title ?: ('Jobcard #'.$jobcard->job_number)
            )
        );
    }

    public function notifyTaskAssignment(int $companyId, Task $task): void
    {
        if (! $task->assigned_to_user_id && ! $task->assigned_to_team_id) {
            return;
        }

        $this->notifyUsers(
            $companyId,
            (int) ($task->assigned_to_user_id ?? 0),
            (int) ($task->assigned_to_team_id ?? 0),
            new AssignmentNotification(
                'task',
                $task->id,
                $task->title ?: ('Task #'.$task->id)
            )
        );
    }

    private function notifyUsers(int $companyId, int $assignedUserId, int $assignedTeamId, AssignmentNotification $notification): void
    {
        $this->resolveAssignmentTargets($companyId, $assignedUserId, $assignedTeamId)
            ->each(fn (User $user) => $user->notify($notification));
    }

    private function resolveAssignmentTargets(int $companyId, int $assignedUserId, int $assignedTeamId): Collection
    {
        $notifiableUsers = collect();

        if ($assignedUserId > 0) {
            $user = User::query()
                ->staffSelectableForCompany($companyId)
                ->whereKey($assignedUserId)
                ->first();
            if ($user) {
                $notifiableUsers->push($user);
            }
        }

        if ($assignedTeamId > 0) {
            $teamUsers = Team::query()
                ->where('company_id', $companyId)
                ->whereKey($assignedTeamId)
                ->with(['users' => fn ($query) => $query->select('users.id', 'users.name', 'users.email')])
                ->first()
                ?->users ?? collect();
            $notifiableUsers = $notifiableUsers->merge($teamUsers);
        }

        return $notifiableUsers->unique('id')->values();
    }
}

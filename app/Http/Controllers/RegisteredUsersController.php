<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerUpdateRequest;
use App\Models\User;
use App\Services\SystemNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUsersController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $canRegistered = $user && $user->hasModulePermission('registered-users', 'list');
        $canUpdateRequests = $user && $user->hasModulePermission('customer-update-requests', 'list');

        abort_unless($canRegistered || $canUpdateRequests, 403);

        return Inertia::render('registered-users/Index', [
            'showRegisteredUsers' => $canRegistered,
            'showUpdateRequests' => $canUpdateRequests,
            'counts' => [
                'registered' => $canRegistered
                    ? (int) User::query()
                        ->where('user_type', 'client')
                        ->where('approval_status', 'approved')
                        ->count()
                    : 0,
                'pending' => $canRegistered
                    ? (int) User::query()
                        ->where('user_type', 'client')
                        ->where('approval_status', 'pending')
                        ->count()
                    : 0,
                'deactivated' => $canRegistered
                    ? (int) User::query()
                        ->where('user_type', 'client')
                        ->where('approval_status', 'deactivated')
                        ->count()
                    : 0,
                'update_requests' => $canUpdateRequests
                    ? (int) CustomerUpdateRequest::query()->count()
                    : 0,
                'pending_update_requests' => $canUpdateRequests
                    ? (int) CustomerUpdateRequest::query()
                        ->where('status', 'pending')
                        ->count()
                    : 0,
            ],
        ]);
    }

    public function registeredIndex(Request $request): Response
    {
        $accountFilter = $request->input('account_filter', 'active');
        if (! in_array($accountFilter, ['active', 'deactivated'], true)) {
            $accountFilter = 'active';
        }

        $defaultSort = $accountFilter === 'deactivated' ? 'updated_at' : 'approved_at';
        $sortBy = $request->input('sort_by', $defaultSort);
        $sortDir = $request->input('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $sortable = ['name', 'email', 'approved_at', 'created_at', 'updated_at'];
        if (! in_array($sortBy, $sortable, true)) {
            $sortBy = $defaultSort;
        }

        $status = $accountFilter === 'active' ? 'approved' : 'deactivated';

        $query = User::query()
            ->with('customer')
            ->where('user_type', 'client')
            ->where('approval_status', $status)
            ->when($request->string('search')->toString() !== '', function ($q) use ($request) {
                $term = '%'.$request->string('search')->trim()->toString().'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhereHas('customer', function ($cq) use ($term) {
                            $cq->where('name', 'like', $term);
                        });
                });
            });

        if ($sortBy === 'name' || $sortBy === 'email') {
            $query->orderBy($sortBy, $sortDir);
        } elseif ($sortBy === 'approved_at') {
            $query->orderByRaw('approved_at IS NULL')->orderBy('approved_at', $sortDir);
        } elseif ($sortBy === 'updated_at') {
            $query->orderBy('updated_at', $sortDir);
        } else {
            $query->orderBy('created_at', $sortDir);
        }

        $users = $query->paginate(15)->withQueryString();

        return Inertia::render('registered-users/registered/Index', [
            'users' => $users,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'account_filter' => $accountFilter,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
        ]);
    }

    public function registeredShow(User $user): Response
    {
        abort_unless(
            $user->isClientUser() && in_array($user->approval_status, ['approved', 'deactivated'], true),
            404
        );

        $user->load(['customer', 'approvedBy']);

        return Inertia::render('registered-users/registered/Show', [
            'user' => $user,
        ]);
    }

    public function pendingIndex(Request $request): Response
    {
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $sortable = ['name', 'email', 'created_at'];
        if (! in_array($sortBy, $sortable, true)) {
            $sortBy = 'created_at';
        }

        $query = User::query()
            ->with('customer')
            ->where('user_type', 'client')
            ->where('approval_status', 'pending')
            ->when($request->string('search')->toString() !== '', function ($q) use ($request) {
                $term = '%'.$request->string('search')->trim()->toString().'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhereHas('customer', function ($cq) use ($term) {
                            $cq->where('name', 'like', $term);
                        });
                });
            })
            ->orderBy($sortBy, $sortDir);

        $users = $query->paginate(15)->withQueryString();

        return Inertia::render('registered-users/pending/Index', [
            'users' => $users,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
        ]);
    }

    public function pendingShow(User $user): Response
    {
        abort_unless($user->isClientUser() && $user->approval_status === 'pending', 404);

        $user->load('customer');

        return Inertia::render('registered-users/pending/Show', [
            'user' => $user,
        ]);
    }

    public function updateRequestsIndex(Request $request): Response
    {
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $sortable = ['status', 'created_at'];
        if (! in_array($sortBy, $sortable, true)) {
            $sortBy = 'created_at';
        }

        $statusFilter = $request->string('status')->toString();
        $allowedStatuses = ['pending', 'approved', 'rejected', ''];
        if (! in_array($statusFilter, $allowedStatuses, true)) {
            $statusFilter = '';
        }

        $query = CustomerUpdateRequest::query()
            ->with(['customer', 'user'])
            ->when($statusFilter !== '', fn ($q) => $q->where('status', $statusFilter))
            ->when($request->string('search')->toString() !== '', function ($q) use ($request) {
                $term = '%'.$request->string('search')->trim()->toString().'%';
                $q->where(function ($inner) use ($term) {
                    $inner->whereHas('customer', function ($cq) use ($term) {
                        $cq->where('name', 'like', $term)->orWhere('email', 'like', $term);
                    })->orWhereHas('user', function ($uq) use ($term) {
                        $uq->where('name', 'like', $term)->orWhere('email', 'like', $term);
                    });
                });
            })
            ->orderBy($sortBy, $sortDir);

        $updateRequests = $query->paginate(15)->withQueryString();

        return Inertia::render('registered-users/update-requests/Index', [
            'updateRequests' => $updateRequests,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $statusFilter,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
        ]);
    }

    public function updateRequestShow(CustomerUpdateRequest $updateRequest): Response
    {
        $updateRequest->load(['customer', 'user', 'reviewer']);

        return Inertia::render('registered-users/update-requests/Show', [
            'updateRequest' => $updateRequest,
        ]);
    }

    public function approve(User $user): RedirectResponse
    {
        abort_unless($user->isClientUser(), 404);

        $user->update([
            'approval_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ]);

        app(SystemNotificationService::class)->notifyClientApproval($user->fresh());

        return redirect()
            ->route('registered-users.registered.show', $user)
            ->with('success', 'Client user approved successfully.');
    }

    public function reject(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isClientUser(), 404);

        $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $user->update([
            'approval_status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => null,
        ]);

        app(SystemNotificationService::class)->notifyClientRejection(
            $user->fresh(),
            $request->string('reason')->toString() ?: null
        );

        return redirect()
            ->route('registered-users.pending.index')
            ->with('success', 'Client user rejected.');
    }

    public function deactivate(User $user): RedirectResponse
    {
        abort_unless($user->isClientUser() && $user->approval_status === 'approved', 404);

        $user->update([
            'approval_status' => 'deactivated',
        ]);

        return redirect()
            ->route('registered-users.registered.show', $user)
            ->with('success', 'Client user has been deactivated. They can no longer sign in to Client Zone.');
    }

    public function reactivate(User $user): RedirectResponse
    {
        abort_unless($user->isClientUser() && $user->approval_status === 'deactivated', 404);

        $user->update([
            'approval_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ]);

        return redirect()
            ->route('registered-users.registered.show', $user)
            ->with('success', 'Client user has been reactivated.');
    }

    public function approveUpdate(CustomerUpdateRequest $updateRequest): RedirectResponse
    {
        abort_unless($updateRequest->status === 'pending', 422);

        $customer = $updateRequest->customer;
        $clientUser = $updateRequest->user;
        $requestedChanges = $updateRequest->requested_changes ?? [];
        $beforeChanges = $customer->only(array_keys($requestedChanges));
        $requestedEmail = isset($requestedChanges['email']) ? strtolower(trim((string) $requestedChanges['email'])) : null;
        $customerEmailIsChanging = $requestedEmail !== null
            && $requestedEmail !== ''
            && $requestedEmail !== strtolower((string) $customer->email);

        if ($customerEmailIsChanging && $clientUser?->isClientUser() && $clientUser->customer_id === $customer->id) {
            $emailAlreadyTaken = User::query()
                ->whereRaw('LOWER(email) = ?', [$requestedEmail])
                ->where('id', '!=', $clientUser->id)
                ->exists();

            abort_if($emailAlreadyTaken, 422, 'The requested email address is already in use by another user.');
        }

        DB::transaction(function () use ($updateRequest, $customer, $clientUser, $requestedChanges, $requestedEmail, $customerEmailIsChanging): void {
            if ($requestedEmail !== null && $requestedEmail !== '') {
                $requestedChanges['email'] = $requestedEmail;
            }

            $customer->update($requestedChanges);

            if ($customerEmailIsChanging && $clientUser?->isClientUser() && $clientUser->customer_id === $customer->id) {
                $clientUser->forceFill([
                    'email' => $requestedEmail,
                    'email_verified_at' => null,
                ])->save();
            }

            $updateRequest->update([
                'status' => 'approved',
                'reviewed_at' => now(),
                'reviewed_by' => auth()->id(),
                'rejection_reason' => null,
            ]);
        });

        $customer->refresh();

        if ($clientUser) {
            $clientUser->refresh();
        }

        $changes = app(SystemNotificationService::class)->diffAttributes($beforeChanges, $requestedChanges);
        app(SystemNotificationService::class)->notifyClientInfoUpdateApplied($customer, $changes);

        return redirect()
            ->route('registered-users.update-requests.show', $updateRequest)
            ->with('success', 'Customer update request approved and applied.');
    }

    public function rejectUpdate(Request $request, CustomerUpdateRequest $updateRequest): RedirectResponse
    {
        abort_unless($updateRequest->status === 'pending', 422);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $reason = isset($validated['reason']) ? trim((string) $validated['reason']) : '';
        $reason = $reason === '' ? null : $reason;

        $updateRequest->update([
            'status' => 'rejected',
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
            'rejection_reason' => $reason,
        ]);

        app(SystemNotificationService::class)->notifyClientInfoUpdateRejected($updateRequest->customer, $reason);

        return redirect()
            ->route('registered-users.update-requests.show', $updateRequest)
            ->with('success', 'Customer update request rejected.');
    }
}

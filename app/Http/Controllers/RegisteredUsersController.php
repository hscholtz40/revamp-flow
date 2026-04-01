<?php

namespace App\Http\Controllers;

use App\Models\CustomerUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUsersController extends Controller
{
    public function index(Request $request): Response
    {
        $pendingClients = User::query()
            ->with('customer')
            ->where('user_type', 'client')
            ->where('approval_status', 'pending')
            ->latest('created_at')
            ->get();

        $approvedClients = User::query()
            ->with('customer')
            ->where('user_type', 'client')
            ->where('approval_status', 'approved')
            ->latest('approved_at')
            ->limit(50)
            ->get();

        $pendingUpdateRequests = CustomerUpdateRequest::query()
            ->with(['customer', 'user'])
            ->where('status', 'pending')
            ->latest('created_at')
            ->get();

        return Inertia::render('registered-users/Index', [
            'pendingClients' => $pendingClients,
            'approvedClients' => $approvedClients,
            'pendingUpdateRequests' => $pendingUpdateRequests,
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

        return back()->with('success', 'Client user approved successfully.');
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

        return back()->with('success', 'Client user rejected.');
    }

    public function approveUpdate(CustomerUpdateRequest $updateRequest): RedirectResponse
    {
        abort_unless($updateRequest->status === 'pending', 422);

        $updateRequest->customer->update($updateRequest->requested_changes ?? []);

        $updateRequest->update([
            'status' => 'approved',
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
            'rejection_reason' => null,
        ]);

        return back()->with('success', 'Customer update request approved and applied.');
    }

    public function rejectUpdate(Request $request, CustomerUpdateRequest $updateRequest): RedirectResponse
    {
        abort_unless($updateRequest->status === 'pending', 422);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $updateRequest->update([
            'status' => 'rejected',
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
            'rejection_reason' => $validated['reason'] ?? null,
        ]);

        return back()->with('success', 'Customer update request rejected.');
    }
}

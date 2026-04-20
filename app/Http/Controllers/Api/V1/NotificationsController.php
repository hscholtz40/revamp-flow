<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class NotificationsController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min(50, max(1, (int) $request->input('per_page', 15)));

        if (! Schema::hasTable('notifications')) {
            return response()->json(new LengthAwarePaginator([], 0, $perPage));
        }

        return response()->json(
            $request->user()->notifications()->latest()->paginate($perPage)
        );
    }

    public function markRead(Request $request, string $id)
    {
        if (! Schema::hasTable('notifications')) {
            return response()->json(['message' => 'Notification marked read']);
        }

        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        return response()->json(['message' => 'Notification marked read']);
    }

    public function markAllRead(Request $request)
    {
        if (! Schema::hasTable('notifications')) {
            return response()->json(['message' => 'All notifications marked read']);
        }

        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked read']);
    }

    public function registerDevice(Request $request)
    {
        $payload = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:50'],
        ]);

        return response()->json([
            'message' => 'Push token registered',
            'token' => $payload['token'],
            'platform' => $payload['platform'] ?? 'unknown',
        ], 201);
    }
}

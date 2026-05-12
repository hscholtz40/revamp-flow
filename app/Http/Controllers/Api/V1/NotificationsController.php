<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Device;
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
            'token' => ['required', 'string', 'max:500'],
            'platform' => ['required', 'string', 'max:50', 'in:ios,android'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        Device::updateOrCreate(
            ['user_id' => $request->user()->id, 'token' => $payload['token']],
            [
                'platform' => $payload['platform'],
                'device_name' => $payload['device_name'] ?? 'Unknown Device',
                'last_active_at' => now(),
            ]
        );

        return response()->json(['message' => 'Device registered'], 201);
    }

    public function listDevices(Request $request)
    {
        return response()->json(
            $request->user()->devices()->get(['id', 'platform', 'device_name', 'last_active_at'])
        );
    }
}

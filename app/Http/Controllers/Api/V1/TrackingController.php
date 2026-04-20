<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\LocationPingUpdated;
use App\Http\Controllers\Controller;
use App\Models\LocationPing;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    public function vehicles(Request $request)
    {
        $companyId = $request->user()->getCurrentCompany()?->id;

        return response()->json(
            Vehicle::query()->where('company_id', $companyId)->orderBy('name')->get()
        );
    }

    public function storeVehicle(Request $request)
    {
        $companyId = (int) ($request->user()->getCurrentCompany()?->id ?? 0);
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        $vehicle = Vehicle::create([
            ...$payload,
            'company_id' => $companyId,
            'status' => $payload['status'] ?? 'active',
        ]);

        return response()->json($vehicle, 201);
    }

    public function updateVehicle(Request $request, Vehicle $vehicle)
    {
        $companyId = (int) ($request->user()->getCurrentCompany()?->id ?? 0);
        abort_unless((int) $vehicle->company_id === $companyId, 404);

        $payload = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        $vehicle->update($payload);

        return response()->json($vehicle->fresh());
    }

    public function latest(Request $request)
    {
        $companyId = $request->user()->getCurrentCompany()?->id;
        $query = LocationPing::query()->where('company_id', $companyId)->latest('recorded_at');

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->integer('vehicle_id'));
        }

        return response()->json($query->limit(100)->get());
    }

    public function history(Request $request)
    {
        $companyId = (int) ($request->user()->getCurrentCompany()?->id ?? 0);
        $request->validate([
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $query = LocationPing::query()->where('company_id', $companyId)->orderByDesc('recorded_at');

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->integer('vehicle_id'));
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }
        if ($request->filled('start_at')) {
            $query->where('recorded_at', '>=', $request->date('start_at'));
        }
        if ($request->filled('end_at')) {
            $query->where('recorded_at', '<=', $request->date('end_at'));
        }

        return response()->json($query->paginate(200));
    }

    public function ingest(Request $request)
    {
        $payload = $request->validate([
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'jobcard_id' => ['nullable', 'integer', 'exists:jobcards,id'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'speed' => ['nullable', 'numeric'],
            'heading' => ['nullable', 'numeric'],
            'accuracy' => ['nullable', 'numeric'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        $ping = LocationPing::create([
            ...$payload,
            'company_id' => $request->user()->getCurrentCompany()?->id,
            'user_id' => $request->user()->id,
            'recorded_at' => $payload['recorded_at'] ?? now(),
        ]);

        event(new LocationPingUpdated($ping));

        return response()->json($ping, 201);
    }
}

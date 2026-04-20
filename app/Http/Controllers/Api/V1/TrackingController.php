<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\LocationPingUpdated;
use App\Models\LocationPing;
use App\Models\Vehicle;
use App\Http\Controllers\Controller;
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

    public function latest(Request $request)
    {
        $companyId = $request->user()->getCurrentCompany()?->id;
        $query = LocationPing::query()->where('company_id', $companyId)->latest('recorded_at');

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->integer('vehicle_id'));
        }

        return response()->json($query->limit(100)->get());
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

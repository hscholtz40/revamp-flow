<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FilesController extends Controller
{
    public function upload(Request $request)
    {
        $payload = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
        ]);

        $path = $payload['file']->store('mobile-uploads', 'public');
        $fileId = base64_encode($path);

        return response()->json([
            'id' => $fileId,
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ], 201);
    }

    public function show(Request $request, string $id)
    {
        $path = base64_decode($id, true);
        abort_unless(is_string($path) && Storage::disk('public')->exists($path), 404);

        return response()->json([
            'id' => $id,
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    public function destroy(Request $request, string $id)
    {
        $path = base64_decode($id, true);
        abort_unless(is_string($path) && Storage::disk('public')->exists($path), 404);
        Storage::disk('public')->delete($path);

        return response()->json(['message' => 'File deleted']);
    }
}

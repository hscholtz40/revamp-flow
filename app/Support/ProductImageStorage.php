<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProductImageStorage
{
    public static function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, '/')) {
            return $path;
        }

        return '/storage/'.ltrim($path, '/');
    }

    public static function isManagedPath(?string $path): bool
    {
        if ($path === null || $path === '') {
            return false;
        }

        return ! str_starts_with($path, 'http://')
            && ! str_starts_with($path, 'https://')
            && ! str_starts_with($path, '/');
    }

    public static function store(UploadedFile $file, int $companyId): string
    {
        return $file->store("product-images/{$companyId}", 'public');
    }

    public static function delete(?string $path): void
    {
        if (! self::isManagedPath($path)) {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    public static function resolvePath(Request $request, int $companyId, ?string $existingPath = null): ?string
    {
        if ($request->boolean('remove_image')) {
            self::delete($existingPath);

            return null;
        }

        if ($request->hasFile('image')) {
            self::delete($existingPath);

            return self::store($request->file('image'), $companyId);
        }

        return $existingPath;
    }
}

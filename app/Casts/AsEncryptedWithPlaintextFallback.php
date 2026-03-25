<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Matches Laravel's encrypted string cast (no serialization) but returns the raw DB value when
 * decryption fails, so rows stored as plaintext before encryption was enabled keep working.
 * New and updated values are always persisted encrypted.
 */
class AsEncryptedWithPlaintextFallback implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            return $value;
        }

        try {
            return Crypt::decrypt($value, false);
        } catch (DecryptException) {
            return $value;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '') {
            return [$key => null];
        }

        return [$key => Crypt::encrypt($value, false)];
    }
}

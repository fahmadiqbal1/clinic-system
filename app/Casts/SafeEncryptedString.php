<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Drop-in replacement for Laravel's built-in 'encrypted' cast.
 *
 * Difference: if decryption fails (e.g. the stored value is plain text from
 * before encryption was introduced), it returns the raw value instead of
 * throwing. This prevents 500 errors on legacy rows while a one-time migration
 * command re-encrypts them.
 *
 * Once all rows have been re-encrypted by `php artisan patients:encrypt-phi`,
 * the Patient model can switch back to the standard 'encrypted' cast.
 */
class SafeEncryptedString implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            // Value is plain text (stored before encryption was added).
            // Return as-is so the row remains readable; the encrypt-phi
            // command will fix it in the background.
            return $value;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return Crypt::encryptString((string) $value);
    }
}

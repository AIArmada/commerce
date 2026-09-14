<?php

declare(strict_types=1);

namespace AIArmada\Communications\Support;

use AIArmada\Communications\Contracts\DestinationProtector;
use Illuminate\Support\Facades\Crypt;

class DestinationProtectorService implements DestinationProtector
{
    public function encrypt(string $destination): string
    {
        return Crypt::encryptString($destination);
    }

    public function decrypt(string $ciphertext): string
    {
        return Crypt::decryptString($ciphertext);
    }

    public function hash(string $destination): string
    {
        return hash_hmac('sha256', $destination, (string) config('app.key'));
    }

    public function hint(string $destination): string
    {
        $length = mb_strlen($destination);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        $visible = 2;
        $masked = $length - $visible;

        return mb_substr($destination, 0, $visible) . str_repeat('*', $masked);
    }
}

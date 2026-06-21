<?php

declare(strict_types=1);

namespace AIArmada\Communications\Support;

use AIArmada\Communications\Contracts\DestinationProtector;
use RuntimeException;

class DestinationProtectorService implements DestinationProtector
{
    public function encrypt(string $destination): string
    {
        $key = $this->key();

        $iv = random_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($destination, 'aes-256-cbc', $key, 0, $iv);

        return base64_encode($iv . $encrypted);
    }

    public function decrypt(string $ciphertext): string
    {
        $key = $this->key();

        $data = base64_decode($ciphertext);
        $ivLength = openssl_cipher_iv_length('aes-256-cbc');
        $iv = mb_substr($data, 0, $ivLength, '8bit');
        $encrypted = mb_substr($data, $ivLength, null, '8bit');

        return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
    }

    public function hash(string $destination): string
    {
        return hash_hmac('sha256', $destination, $this->key());
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

    private function key(): string
    {
        $key = config('app.key');

        if ($key === null || $key === '' || $key === 'base64:' || str_starts_with($key, 'base64:') === false && mb_strlen($key, '8bit') !== 32) {
            throw new RuntimeException(
                'DestinationProtector requires a valid app.key to be configured. '
                . 'Set APP_KEY in your .env file or ensure config("app.key") returns a 32-byte string.',
            );
        }

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(mb_substr($key, 7), true);

            if ($decoded === false || mb_strlen($decoded, '8bit') !== 32) {
                throw new RuntimeException('The app.key base64 prefix is present but the decoded key is not a valid 32-byte key.');
            }

            return $decoded;
        }

        return $key;
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Webhooks;

use Illuminate\Http\Request;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;

final class CashierChipSpatieSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $shouldVerify = (bool) config('cashier-chip.webhooks.verify_signature', true);

        if (! $shouldVerify) {
            return true;
        }

        $signature = $request->header('X-Signature');

        if (! is_string($signature) || $signature === '') {
            return false;
        }

        $secret = config('cashier-chip.webhooks.secret');

        if (! is_string($secret) || $secret === '') {
            return false;
        }

        $payload = $request->getContent();
        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }
}

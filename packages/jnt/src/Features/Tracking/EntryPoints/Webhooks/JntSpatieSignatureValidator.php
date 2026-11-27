<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Tracking\EntryPoints\Webhooks;

use AIArmada\CommerceSupport\Features\Webhooks\EntryPoints\CommerceSignatureValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class JntSpatieSignatureValidator extends CommerceSignatureValidator
{
    protected function getSignatureHeader(): string
    {
        return 'digest';
    }

    protected function validateSignature(Request $request, string $signature, string $secret): bool
    {
        $bizContent = $this->getPayloadForSigning($request);

        if (mb_strlen($bizContent, '8bit') > max(1, (int) config('jnt.webhooks.max_biz_content_bytes', 1048576))) {
            return false;
        }

        if (! config('jnt.webhooks.verify_signature', true)) {
            if (app()->environment('production')) {
                Log::channel(config('jnt.logging.channel', 'stack'))
                    ->error('J&T webhook signature verification cannot be disabled in production');

                return false;
            }

            return true;
        }

        $runtimeSecret = (string) config('jnt.private_key', '');

        if ($runtimeSecret !== '') {
            $secret = $runtimeSecret;
        }

        if ($secret === '') {
            return false;
        }

        if ($bizContent === '' || $bizContent === '0') {
            return false;
        }

        return parent::validateSignature($request, $signature, $secret);
    }

    protected function getPayloadForSigning(Request $request): string
    {
        $payload = $request->input('bizContent', '');

        return is_string($payload) ? $payload : '';
    }

    protected function computeSignature(string $payload, string $secret): string
    {
        return base64_encode(md5($payload . $secret, true));
    }

    protected function getHashAlgorithm(): string
    {
        // Not used because computeSignature is overridden for J&T MD5 + Base64 signing.
        return 'md5';
    }
}

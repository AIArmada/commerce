<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Transformers;

use AIArmada\Checkout\Contracts\SessionDataTransformerInterface;
use AIArmada\Checkout\Models\CheckoutSession;
use Illuminate\Support\Facades\Log;

final class NullSessionDataTransformer implements SessionDataTransformerInterface
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function transform(array $data, CheckoutSession $session): array
    {
        if (app()->environment('production')) {
            Log::warning('Checkout is using the null session data transformer.', [
                'session_id' => $session->getKey(),
                'transformer' => self::class,
            ]);
        }

        return $data;
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Merchant\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Session\Session;

final class CaptureMerchantReferral
{
    /** @param array<string, mixed> $query */
    public function execute(Session $session, array $query): void
    {
        $code = $query[(string) config('affiliates.merchant.referral_param', 'anl')] ?? null;

        if (! is_string($code) || $code === '' || mb_strlen($code) > 255) {
            return;
        }

        $sessionKey = (string) config('affiliates.merchant.session_key', 'affiliate_network.link_code');
        $subIds = [];

        foreach (['sub1', 'sub2', 'sub3'] as $key) {
            $value = $query[$key] ?? null;

            if (is_string($value) && $value !== '' && mb_strlen($value) <= 255) {
                $subIds[$key] = $value;
            }
        }

        $session->put([
            $sessionKey => $code,
            $sessionKey . '_clicked_at' => CarbonImmutable::now()->toIso8601String(),
            $sessionKey . '_sub_ids' => $subIds,
        ]);
    }
}

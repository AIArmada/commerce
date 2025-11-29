<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Listeners;

use AIArmada\Affiliates\Services\AffiliateService;
use AIArmada\Vouchers\Events\VoucherApplied;
use Illuminate\Support\Arr;

final class AttachAffiliateFromVoucher
{
    public function __construct(private readonly AffiliateService $affiliates) {}

    public function handle(VoucherApplied $event): void
    {
        $metadata = $event->voucher->metadata ?? [];
        $context = [
            'voucher_code' => $event->voucher->code,
            'source' => 'voucher',
            'utm_campaign' => $metadata['campaign'] ?? null,
            'metadata' => ['voucher_id' => $event->voucher->id],
        ];

        $cookieValue = $this->resolveCookieValue();

        if ($cookieValue) {
            $context['cookie_value'] = $cookieValue;
        }

        $code = $this->resolveAffiliateCode($metadata);

        if ($code) {
            $attached = $this->affiliates->attachToCartByCode($code, $event->cart, $context);

            if ($attached !== null) {
                return;
            }
        }

        if (! $this->shouldMatchDefaultVoucherCode()) {
            return;
        }

        $affiliate = $this->affiliates->findByDefaultVoucherCode($event->voucher->code);

        if (! $affiliate) {
            return;
        }

        $this->affiliates->attachAffiliate($affiliate, $event->cart, $context);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function resolveAffiliateCode(?array $metadata): ?string
    {
        if (! $metadata) {
            return null;
        }

        $paths = (array) config('affiliates.integrations.vouchers.metadata_keys', ['affiliate_code']);

        foreach ($paths as $path) {
            $value = Arr::get($metadata, $path);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function shouldMatchDefaultVoucherCode(): bool
    {
        return (bool) config('affiliates.integrations.vouchers.match_default_voucher_code', true);
    }

    private function resolveCookieValue(): ?string
    {
        if (! config('affiliates.cookies.enabled', true)) {
            return null;
        }

        if (! app()->bound('request')) {
            return null;
        }

        return request()->cookie(config('affiliates.cookies.name', 'affiliate_session'));
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Listeners;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Services\AffiliateService;
use AIArmada\Vouchers\Events\VoucherApplied;

final class AttachAffiliateFromVoucher
{
    public function __construct(private readonly AffiliateService $affiliates) {}

    public function handle(VoucherApplied $event): void
    {
        $voucher = $event->voucher;

        $metadata = $voucher->metadata ?? [];

        $context = [
            'voucher_code' => $voucher->code,
            'source' => 'voucher',
            'utm_campaign' => $metadata['campaign'] ?? null,
            'metadata' => ['voucher_id' => $voucher->id],
        ];

        if ($voucher->affiliateCommissionType && $voucher->affiliateCommissionValue !== null) {
            $context['commission_override'] = [
                'type' => $voucher->affiliateCommissionType,
                'value' => $voucher->affiliateCommissionValue,
            ];
        }

        if ($voucher->affiliateProgramId) {
            $context['affiliate_program_id'] = $voucher->affiliateProgramId;
        }

        if ($voucher->affiliateUplineLevels) {
            $context['upline_levels'] = $voucher->affiliateUplineLevels;
        }

        $cookieValue = $this->resolveCookieValue();

        if ($cookieValue) {
            $context['cookie_value'] = $cookieValue;
        }

        if ($voucher->affiliateId !== null) {
            $affiliate = $this->affiliates->query()->whereKey($voucher->affiliateId)->first();

            if ($affiliate instanceof Affiliate && $affiliate->isActive()) {
                $this->affiliates->attachAffiliate($affiliate, $event->cart, $context);

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

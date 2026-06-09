<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Conversions;

use AIArmada\Affiliates\Data\AffiliateConversionData;
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\ConversionStatus;
use AIArmada\Affiliates\Support\Webhooks\WebhookDispatcher;
use Illuminate\Contracts\Events\Dispatcher;
use Lorisleiva\Actions\Concerns\AsAction;

final class AllocateUplineCommissions
{
    use AsAction;

    public function __construct(
        private readonly Dispatcher $events,
        private readonly WebhookDispatcher $webhooks,
    ) {}

    public function handle(array $baseConversions, bool $autoApprove, ConversionStatus $statusEnum, ?string $attributionId): void
    {
        $config = config('affiliates.payouts.multi_level', []);

        if (! ($config['enabled'] ?? false)) {
            return;
        }

        $levels = $config['levels'] ?? [];

        if ($levels === [] || ! is_array($levels)) {
            return;
        }

        foreach ($baseConversions as $conversionData) {
            $affiliate = Affiliate::query()->find($conversionData->affiliateId);

            if (! $affiliate) {
                continue;
            }

            $current = $affiliate->parent;
            $depth = 0;

            foreach ($levels as $share) {
                $depth++;

                if (! $current) {
                    break;
                }

                $portion = (int) round($conversionData->commissionMinor * (float) $share);

                if ($portion > 0) {
                    $model = AffiliateConversion::create([
                        'affiliate_id' => $current->getKey(),
                        'affiliate_code' => $current->code,
                        'affiliate_attribution_id' => $attributionId,
                        'subject_type' => $conversionData->subjectType,
                        'subject_identifier' => $conversionData->subjectIdentifier,
                        'subject_instance' => $conversionData->subjectInstance,
                        'subject_title_snapshot' => $conversionData->subjectTitleSnapshot,
                        'cart_identifier' => $conversionData->cartIdentifier,
                        'cart_instance' => $conversionData->cartInstance,
                        'voucher_code' => $conversionData->voucherCode,
                        'external_reference' => $conversionData->externalReference,
                        'order_reference' => $conversionData->orderReference,
                        'conversion_type' => $conversionData->conversionType,
                        'subtotal_minor' => 0,
                        'value_minor' => 0,
                        'total_minor' => 0,
                        'commission_minor' => $portion,
                        'commission_currency' => $conversionData->commissionCurrency,
                        'status' => $autoApprove ? ApprovedConversion::class : $statusEnum::class,
                        'channel' => 'upline',
                        'metadata' => [
                            'upline_of' => $affiliate->getKey(),
                            'level' => $depth,
                            'weight' => $share,
                            'base_conversion' => $conversionData->id,
                        ],
                        'owner_type' => $current->owner_type,
                        'owner_id' => $current->owner_id,
                        'occurred_at' => now(),
                        'approved_at' => $autoApprove ? now() : null,
                    ]);

                    $uplineData = AffiliateConversionData::fromModel($model);

                    $shouldDispatchConversion = (bool) config('affiliates.events.dispatch_conversion', true);

                    if ($shouldDispatchConversion) {
                        $this->events?->dispatch(new AffiliateConversionRecorded($uplineData));
                    }

                    $shouldDispatchWebhooks = (bool) config('affiliates.events.dispatch_webhooks', true);

                    if ($shouldDispatchWebhooks) {
                        $this->webhooks->dispatch('conversion', $uplineData->toArray());
                    }
                }

                $current = $current->parent;
            }
        }
    }
}

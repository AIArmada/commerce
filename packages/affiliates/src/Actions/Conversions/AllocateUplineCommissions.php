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
        private readonly ApplyConversionAccounting $accounting,
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

            $overrideLevels = $conversionData->metadata['upline_levels'] ?? null;

            $resolvedLevels = $levels;

            if (is_array($overrideLevels)) {
                usort($overrideLevels, static fn (array $a, array $b): int => ($a['level'] ?? 0) <=> ($b['level'] ?? 0));
                $resolvedLevels = $overrideLevels;
            }

            $current = $affiliate->parent;
            $depth = 0;

            foreach ($resolvedLevels as $levelConfig) {
                $depth++;

                if (! $current) {
                    break;
                }

                $portion = self::resolvePortion($conversionData->commissionMinor, $levelConfig);

                if ($portion > 0) {
                    $model = AffiliateConversion::create([
                        'affiliate_id' => $current->getKey(),
                        'affiliate_code' => $current->code,
                        'affiliate_attribution_id' => $attributionId,
                        'subject_type' => $conversionData->subjectType,
                        'subject_identifier' => $conversionData->subjectIdentifier,
                        'subject_instance' => $conversionData->subjectInstance,
                        'subject_title_snapshot' => $conversionData->subjectTitleSnapshot,
                        'voucher_code' => $conversionData->voucherCode,
                        'external_reference' => $conversionData->externalReference,
                        'conversion_type' => $conversionData->conversionType,
                        'subtotal_minor' => 0,
                        'value_minor' => 0,
                        'commission_minor' => $portion,
                        'commission_currency' => $conversionData->commissionCurrency,
                        'status' => $autoApprove ? ApprovedConversion::class : $statusEnum::class,
                        'channel' => 'upline',
                        'metadata' => [
                            'upline_of' => $affiliate->getKey(),
                            'level' => $depth,
                            'weight' => self::resolveWeight($levelConfig),
                            'base_conversion' => $conversionData->id,
                        ],
                        'owner_type' => $current->owner_type,
                        'owner_id' => $current->owner_id,
                        'occurred_at' => now(),
                        'approved_at' => $autoApprove ? now() : null,
                    ]);

                    $this->accounting->handle($model);

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

    /**
     * @param  int  $commissionMinor  Base commission in cents
     * @param  array{type?: string, value?: int|float, share?: float}|float  $levelConfig  Level configuration
     */
    private static function resolvePortion(int $commissionMinor, array | float $levelConfig): int
    {
        if (is_array($levelConfig)) {
            $type = $levelConfig['type'] ?? null;

            if ($type === 'fixed') {
                return (int) ($levelConfig['value'] ?? 0);
            }

            if ($type === 'percentage') {
                return (int) round($commissionMinor * ((float) ($levelConfig['value'] ?? 0) / 100));
            }

            // Legacy: share as float (0.05 = 5%)
            return (int) round($commissionMinor * (float) ($levelConfig['share'] ?? 0));
        }

        // Global config: flat float (0.05 = 5%)
        return (int) round($commissionMinor * (float) $levelConfig);
    }

    /**
     * @param  array{type?: string, value?: int|float, share?: float}|float  $levelConfig
     */
    private static function resolveWeight(array | float $levelConfig): float | int
    {
        if (is_array($levelConfig)) {
            $type = $levelConfig['type'] ?? null;

            if ($type === 'fixed') {
                return (int) ($levelConfig['value'] ?? 0);
            }

            if ($type === 'percentage') {
                return (float) ($levelConfig['value'] ?? 0);
            }

            // Legacy: share as float
            return (float) ($levelConfig['share'] ?? 0);
        }

        return (float) $levelConfig;
    }
}

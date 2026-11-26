<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Actions;

use AIArmada\Affiliates\Events\AffiliateConversionRecorded;
use AIArmada\Affiliates\Features\Commissions\Api\CommissionPricing;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateConversion;
use AIArmada\Affiliates\Features\Conversions\States\ApprovedConversion;
use AIArmada\Affiliates\Features\Conversions\States\ConversionStatus;
use AIArmada\Affiliates\Features\Webhooks\Api\WebhookApi;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateAccessGuard;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateProfileReader;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class AllocateUplineCommissions
{
    public function __construct(
        private Dispatcher $events,
        private WebhookApi $webhooks,
        private ApplyConversionAccounting $accounting,
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

        $connection = (new AffiliateConversion)->getConnection();
        $connectionIdentity = spl_object_id($connection);
        app(AffiliateAccessGuard::class)->assertConnection($connectionIdentity);
        $profiles = app(AffiliateProfileReader::class);

        $connection->transaction(function () use ($baseConversions, $autoApprove, $statusEnum, $attributionId, $levels, $connectionIdentity, $profiles): void {
            foreach ($baseConversions as $conversionData) {
                $affiliate = $profiles->lock($conversionData->affiliateId, $connectionIdentity);

                if (! $affiliate || ! $affiliate->commercial) {
                    continue;
                }

                $overrideLevels = $conversionData->uplineLevels;

                $resolvedLevels = $levels;

                if (is_array($overrideLevels)) {
                    usort($overrideLevels, static fn (array $a, array $b): int => ($a['level'] ?? 0) <=> ($b['level'] ?? 0));
                    $resolvedLevels = $overrideLevels;
                }

                $current = $affiliate->parentAffiliateId === null ? null : $profiles->lock($affiliate->parentAffiliateId, $connectionIdentity);
                $depth = 0;
                $visited = [$affiliate->reference->id => true];

                foreach ($resolvedLevels as $levelConfig) {
                    $depth++;

                    if (! $current || isset($visited[$current->reference->id])) {
                        break;
                    }

                    if (config('affiliates.owner.enabled', false)
                        && ($current->reference->ownerType !== $affiliate->reference->ownerType
                            || $current->reference->ownerId !== $affiliate->reference->ownerId)) {
                        break;
                    }

                    $visited[$current->reference->id] = true;

                    if (! $current->commercial) {
                        $current = $current->parentAffiliateId === null ? null : $profiles->lock($current->parentAffiliateId, $connectionIdentity);

                        continue;
                    }

                    $portion = app(CommissionPricing::class)->clamp(self::resolvePortion($conversionData->commissionMinor, $levelConfig));

                    if ($portion > 0) {
                        $model = new AffiliateConversion([
                            'affiliate_id' => $current->reference->id,
                            'affiliate_code' => $current->code,
                            'affiliate_attribution_id' => $attributionId,
                            'subject_type' => $conversionData->subjectType,
                            'subject_key' => $conversionData->subjectKey,
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
                                'upline_of' => $affiliate->reference->id,
                                'level' => $depth,
                                'weight' => self::resolveWeight($levelConfig),
                                'base_conversion' => $conversionData->id,
                            ],
                            'occurred_at' => CarbonImmutable::now(),
                            'approved_at' => $autoApprove ? CarbonImmutable::now() : null,
                        ]);
                        $model->forceFill([
                            'owner_type' => $current->reference->ownerType,
                            'owner_id' => $current->reference->ownerId,
                        ]);
                        $model->save();

                        $this->accounting->handle($model);

                        $uplineData = $model->toData();

                        $shouldDispatchConversion = (bool) config('affiliates.events.dispatch_conversion', true);

                        if ($shouldDispatchConversion) {
                            $this->events?->dispatch(new AffiliateConversionRecorded($uplineData));
                        }

                        $shouldDispatchWebhooks = (bool) config('affiliates.events.dispatch_webhooks', true);

                        if ($shouldDispatchWebhooks) {
                            $this->webhooks->dispatch('conversion', $uplineData->toArray());
                        }
                    }

                    $current = $current->parentAffiliateId === null ? null : $profiles->lock($current->parentAffiliateId, $connectionIdentity);
                }
            }
        }, attempts: 3);
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

            if (array_key_exists('share', $levelConfig) && is_numeric($levelConfig['share'])) {
                return (int) round($commissionMinor * (float) $levelConfig['share']);
            }

            return 0;
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

            if (array_key_exists('share', $levelConfig) && is_numeric($levelConfig['share'])) {
                return (float) $levelConfig['share'];
            }

            return 0.0;
        }

        return (float) $levelConfig;
    }
}

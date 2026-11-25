<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Actions;

use AIArmada\Affiliates\Features\Commissions\Api\CommissionPricing;
use AIArmada\Affiliates\Features\Conversions\Api\PerformanceBonusPosting;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use Carbon\CarbonImmutable;
use LogicException;

final readonly class AwardPerformanceBonuses implements PerformanceBonusPosting
{
    public function __construct(private ApplyConversionAccounting $accounting) {}

    public function award(array $bonuses): int
    {
        $connection = (new AffiliateConversion)->getConnection();

        if ($connection !== (new Affiliate)->getConnection()) {
            throw new LogicException('Bonus posting requires a shared database connection.');
        }

        return $connection->transaction(function () use ($bonuses): int {
            $awarded = 0;

            foreach ($bonuses as $bonus) {
                $affiliate = Affiliate::query()->forOwner()->lockForUpdate()->find($bonus['affiliate_id']);

                if (! $affiliate || ! $affiliate->status->equals(Active::class) || ! $affiliate->isCommercialPurpose()) {
                    continue;
                }

                $period = (string) ($bonus['metrics']['period'] ?? CarbonImmutable::now()->format('Y-m'));
                $performanceBonusKey = implode(':', [
                    'performance-bonus',
                    $affiliate->id,
                    $bonus['bonus_type'],
                    $period,
                ]);

                $currency = mb_strtoupper((string) ($affiliate->currency ?? config('affiliates.currency.default', 'MYR')));

                $conversion = AffiliateConversion::firstOrCreate(
                    ['performance_bonus_key' => $performanceBonusKey],
                    [
                        'affiliate_id' => $affiliate->id,
                        'affiliate_code' => $affiliate->code,
                        'external_reference' => 'BONUS-' . $period . '-' . mb_strtoupper(mb_substr(md5($performanceBonusKey), 0, 8)),
                        'performance_bonus_key' => $performanceBonusKey,
                        'subtotal_minor' => 0,
                        'commission_minor' => app(CommissionPricing::class)->clamp((int) $bonus['amount_minor']),
                        'commission_currency' => $currency,
                        'status' => ApprovedConversion::class,
                        'occurred_at' => CarbonImmutable::now(),
                        'metadata' => [
                            'type' => 'performance_bonus',
                            'bonus_type' => $bonus['bonus_type'],
                            'reason' => $bonus['reason'],
                            'metrics' => $bonus['metrics'],
                        ],
                    ],
                );

                if ($conversion->wasRecentlyCreated) {
                    $this->accounting->handle($conversion);
                }

                $awarded += (int) $conversion->wasRecentlyCreated;
            }

            return $awarded;
        }, attempts: 3);
    }
}

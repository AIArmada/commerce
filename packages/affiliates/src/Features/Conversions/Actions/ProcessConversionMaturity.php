<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Actions;

use AIArmada\Affiliates\Actions\Conversions\ApplyConversionAccounting;
use AIArmada\Affiliates\Features\Conversions\Api\ConversionMaturity;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\AffiliateStatus;
use AIArmada\Affiliates\States\QualifiedConversion;
use Carbon\CarbonImmutable;
use RuntimeException;

final readonly class ProcessConversionMaturity implements ConversionMaturity
{
    public function __construct(private ApplyConversionAccounting $accounting) {}

    public function mature(string $conversionId): bool
    {
        $connection = (new AffiliateConversion)->getConnection();

        if ((new Affiliate)->getConnection() !== $connection) {
            throw new RuntimeException('Conversion maturity requires a shared database connection.');
        }

        return $connection->transaction(function () use ($conversionId): bool {
            $conversion = AffiliateConversion::query()->lockForUpdate()->find($conversionId);

            if ($conversion === null || ! $conversion->status->equals(QualifiedConversion::class)
                || $conversion->occurred_at === null
                || $conversion->occurred_at->addDays($this->maturityDays())->isFuture()) {
                return false;
            }

            $affiliate = Affiliate::query()->whereKey($conversion->affiliate_id)->lockForUpdate()->first();

            if ($affiliate === null || ! $affiliate->isCommercialPurpose() || ! $affiliate->canReceivePayout() || $affiliate->isDeactivated()) {
                return false;
            }

            $previous = $conversion->status;
            $conversion->update([
                'status' => ApprovedConversion::class,
                'metadata' => array_merge($conversion->metadata ?? [], [
                    'matured_at' => CarbonImmutable::now()->toIso8601String(),
                ]),
            ]);
            $this->accounting->handle($conversion, $previous);

            return true;
        }, attempts: 3);
    }

    public function process(bool $dryRun = false): int
    {
        $query = AffiliateConversion::query()
            ->where('status', QualifiedConversion::value())
            ->where('occurred_at', '<=', CarbonImmutable::now()->subDays($this->maturityDays()))
            ->whereHas('affiliate', static function ($query): void {
                $query->whereCommercialPurpose()
                    ->where('status', AffiliateStatus::normalize(Active::class))
                    ->whereNull('paused_at')
                    ->whereNull('deactivated_at');
            });

        if ($dryRun) {
            return $query->count();
        }

        $matured = 0;
        foreach ($query->select('id')->lazyById(200) as $conversion) {
            $matured += (int) $this->mature((string) $conversion->getKey());
        }

        return $matured;
    }

    private function maturityDays(): int
    {
        return max(0, (int) config('affiliates.payouts.maturity_days', 30));
    }
}

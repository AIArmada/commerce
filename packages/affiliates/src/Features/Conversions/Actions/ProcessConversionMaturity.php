<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Actions;

use AIArmada\Affiliates\Features\Conversions\Api\ConversionMaturity;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateConversion;
use AIArmada\Affiliates\Features\Conversions\Queries\CommercialAffiliateCandidates;
use AIArmada\Affiliates\Features\Conversions\States\ApprovedConversion;
use AIArmada\Affiliates\Features\Conversions\States\QualifiedConversion;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateAccessGuard;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateProfileReader;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final readonly class ProcessConversionMaturity implements ConversionMaturity
{
    public function __construct(private ApplyConversionAccounting $accounting) {}

    public function mature(string $conversionId): bool
    {
        if (! Str::isUuid($conversionId)) {
            return false;
        }

        $connection = (new AffiliateConversion)->getConnection();
        app(AffiliateAccessGuard::class)->assertConnection(spl_object_id($connection));

        return $connection->transaction(function () use ($conversionId, $connection): bool {
            $conversion = AffiliateConversion::query()->lockForUpdate()->find($conversionId);

            if ($conversion === null || ! $conversion->status->equals(QualifiedConversion::class)
                || $conversion->occurred_at === null
                || $conversion->occurred_at->addDays($this->maturityDays())->isFuture()) {
                return false;
            }

            $affiliate = app(AffiliateProfileReader::class)->lock($conversion->affiliate_id, spl_object_id($connection));

            if ($affiliate === null || ! $affiliate->commercial || ! $affiliate->canReceivePayout) {
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
        $model = new AffiliateConversion;
        $query = AffiliateConversion::query()
            ->where('status', QualifiedConversion::value())
            ->where('occurred_at', '<=', CarbonImmutable::now()->subDays($this->maturityDays()))
            ->whereIn($model->qualifyColumn('affiliate_id'), CommercialAffiliateCandidates::query($model->getConnection(), requirePayoutEligibility: true));

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

<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Conversions;

use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\QualifiedConversion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

final class MatureConversion
{
    use AsAction;

    private int $maturityDays;

    public function __construct(
        private readonly ApplyConversionAccounting $accounting,
    ) {
        $this->maturityDays = config('affiliates.payouts.maturity_days', 30);
    }

    public function handle(AffiliateConversion $conversion): bool
    {
        if (! $conversion->status->equals(QualifiedConversion::class)) {
            return false;
        }

        if ($conversion->occurred_at === null) {
            return false;
        }

        $maturityDate = $conversion->occurred_at->addDays($this->maturityDays);

        if ($maturityDate->isFuture()) {
            return false;
        }

        return DB::transaction(function () use ($conversion): bool {
            $locked = AffiliateConversion::query()->lockForUpdate()->find($conversion->getKey());

            if (! $locked instanceof AffiliateConversion) {
                return false;
            }

            if (! $locked->status->equals(QualifiedConversion::class)) {
                return false;
            }

            if ($locked->occurred_at === null) {
                return false;
            }

            if ($locked->occurred_at->addDays($this->maturityDays)->isFuture()) {
                return false;
            }

            $previousStatus = $locked->status;

            $locked->update([
                'status' => ApprovedConversion::class,
                'metadata' => array_merge($locked->metadata ?? [], [
                    'matured_at' => CarbonImmutable::now()->toIso8601String(),
                ]),
            ]);

            $this->accounting->handle($locked, $previousStatus);

            return true;
        }, attempts: 3);
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Queries;

use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\States\ConversionStatus;
use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class ReadPayoutExport
{
    /** @return list<array{string, string, int, string, string}>|null */
    public function find(string $reference): ?array
    {
        $runner = new OwnerBatchRunner(
            AffiliatePayout::class,
            ['enabled' => 'affiliates.owner.enabled', 'include_global' => 'affiliates.owner.include_global'],
        );

        $matches = $runner->forEach(function () use ($reference): array {
            $payouts = AffiliatePayout::query()
                ->forOwner()
                ->where(function (Builder $query) use ($reference): void {
                    $query->where('reference', $reference);

                    if (Str::isUuid($reference)) {
                        $query->orWhere('id', $reference);
                    }
                })
                ->with('conversions')
                ->limit(2)
                ->get();

            return $payouts->map(static fn (AffiliatePayout $payout): array => $payout->conversions
                ->map(static fn (AffiliateConversion $conversion): array => [
                    (string) $conversion->affiliate_code,
                    (string) $conversion->external_reference,
                    (int) $conversion->commission_minor,
                    (string) $conversion->commission_currency,
                    ConversionStatus::normalize($conversion->status),
                ])->values()->all())->values()->all();
        });

        $result = null;

        foreach ($matches as $ownerMatches) {
            foreach ($ownerMatches as $rows) {
                if ($result !== null) {
                    throw new RuntimeException('Payout reference is ambiguous.');
                }

                $result = $rows;
            }
        }

        return $result;
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Queries;

use AIArmada\Affiliates\Features\Conversions\Api\PayoutConversionIntegrity;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayout;
use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class ReadPayoutExport
{
    public function __construct(private PayoutConversionIntegrity $conversions) {}

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
                ->limit(2)
                ->get();

            return $payouts->map(function (AffiliatePayout $payout): array {
                $rows = [];

                foreach ($this->conversions->exportRows((string) $payout->getKey(), $payout->owner_type, $payout->owner_id, spl_object_id($payout->getConnection())) as $conversion) {
                    $rows[] = [
                        $conversion->affiliateCode,
                        (string) $conversion->externalReference,
                        $conversion->commissionMinor,
                        $conversion->currency,
                        $conversion->status,
                    ];
                }

                return $rows;
            })->values()->all();
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

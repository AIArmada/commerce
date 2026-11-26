<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Queries;

use AIArmada\Affiliates\Features\Conversions\Api\ConversionStatisticsReader;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateAccessGuard;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateProfile;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateProfileReader;
use AIArmada\Affiliates\Features\Programs\Api\ProgramEligibilityReader;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgram;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\Support\RevenueVolume;
use Illuminate\Support\Str;
use LogicException;

final readonly class ProgramEligibilityQuery implements ProgramEligibilityReader
{
    public function __construct(private ConversionStatisticsReader $statistics) {}

    public function canJoin(string $affiliateId, string $programId): bool
    {
        if (! Str::isUuid($affiliateId) || ! Str::isUuid($programId)) {
            return false;
        }

        $affiliate = app(AffiliateProfileReader::class)->find($affiliateId, spl_object_id((new AffiliateProgramMembership)->getConnection()));
        $program = AffiliateProgram::query()->forOwner(includeGlobal: true)->find($programId);

        if ($affiliate === null || $program === null || AffiliateProgramMembership::query()
            ->where('affiliate_id', $affiliateId)->where('program_id', $programId)->exists()) {
            return false;
        }

        return $this->eligibleIds($affiliate, [$program]) !== [];
    }

    /**
     * Evaluate programs whose membership absence has already been checked by the caller.
     *
     * @param  iterable<AffiliateProgram>  $programs
     * @return list<string>
     */
    public function eligibleIds(AffiliateProfile $affiliate, iterable $programs): array
    {
        $connection = (new AffiliateProgramMembership)->getConnection();
        app(AffiliateAccessGuard::class)->assertConnection(spl_object_id($connection));

        if ((new AffiliateProgram)->getConnection() !== $connection || (new AffiliateProgramMembership)->getConnection() !== $connection) {
            throw new LogicException('Program eligibility requires a shared database connection.');
        }

        $affiliateId = $affiliate->reference->id;
        $connectionIdentity = spl_object_id($connection);
        $count = null;
        $byCurrency = null;
        $measured = [];
        $eligible = [];

        foreach ($programs as $program) {
            if (! $program->isOpen()) {
                continue;
            }

            $rules = $program->eligibility_rules ?? [];

            if (isset($rules['required_status']) && $affiliate->status !== $rules['required_status']) {
                continue;
            }

            if (isset($rules['min_conversions'])) {
                $count ??= $this->statistics->count([$affiliateId], $connectionIdentity);

                if ($count < $rules['min_conversions']) {
                    continue;
                }
            }

            if (isset($rules['min_revenue'])) {
                if ($byCurrency === null) {
                    $byCurrency = [];

                    foreach ($this->statistics->totals($affiliateId, $affiliate->currency !== null && mb_trim($affiliate->currency) !== '' ? mb_strtoupper(mb_trim($affiliate->currency)) : RevenueVolume::default(), $connectionIdentity) as $row) {
                        $byCurrency[$row->currency] = $row->revenueMinor;
                    }
                }

                $currency = is_string($rules['min_revenue_currency'] ?? null) && mb_trim($rules['min_revenue_currency']) !== ''
                    ? mb_strtoupper(mb_trim($rules['min_revenue_currency']))
                    : RevenueVolume::default();
                $revenue = $measured[$currency] ??= RevenueVolume::measurableIn($byCurrency, $currency);

                if ($revenue < $rules['min_revenue']) {
                    continue;
                }
            }

            $eligible[] = (string) $program->getKey();
        }

        return $eligible;
    }
}

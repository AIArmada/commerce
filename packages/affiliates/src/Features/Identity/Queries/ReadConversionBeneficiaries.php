<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Queries;

use AIArmada\Affiliates\Features\Identity\Api\ConversionBeneficiary;
use AIArmada\Affiliates\Features\Identity\Api\ConversionBeneficiaryReader;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

final readonly class ReadConversionBeneficiaries implements ConversionBeneficiaryReader
{
    public function __construct(private GuardAffiliateAccess $access) {}

    public function find(string $id, int $connectionIdentity, bool $includeGlobal = false): ?ConversionBeneficiary
    {
        $this->access->assertConnection($connectionIdentity);

        if (! Str::isUuid($id)) {
            return null;
        }

        $affiliate = Affiliate::query()->forOwner(includeGlobal: $includeGlobal && ! OwnerScopeOverride::suppressIncludeGlobal())->find($id);

        return $affiliate === null ? null : self::project($affiliate);
    }

    public function findByCode(string $code, int $connectionIdentity): ?ConversionBeneficiary
    {
        $this->access->assertConnection($connectionIdentity);
        $matches = Affiliate::query()->forOwner(includeGlobal: ! OwnerScopeOverride::suppressIncludeGlobal())
            ->where('code', $code)->limit(2)->get();

        if ($matches->count() > 1) {
            throw new InvalidArgumentException('Conversion affiliate code is ambiguous in the current owner scope.');
        }

        $affiliate = $matches->first();

        return $affiliate === null ? null : self::project($affiliate);
    }

    public function lock(array $ids, int $connectionIdentity, bool $includeGlobal = false): array
    {
        $this->access->assertConnection($connectionIdentity);

        if ((new Affiliate)->getConnection()->transactionLevel() < 1) {
            throw new LogicException('Conversion beneficiary locks require an active transaction.');
        }

        $ids = array_values(array_unique($ids));

        foreach ($ids as $id) {
            if (! Str::isUuid($id)) {
                throw new InvalidArgumentException('Invalid conversion beneficiary identity.');
            }
        }

        $locked = Affiliate::query()->forOwner(includeGlobal: $includeGlobal && ! OwnerScopeOverride::suppressIncludeGlobal())
            ->whereKey($ids)->orderBy('id')->lockForUpdate()->get();

        if ($locked->count() !== count($ids)) {
            throw new InvalidArgumentException('A conversion beneficiary is missing or outside the current owner scope.');
        }

        $result = [];

        foreach ($locked as $affiliate) {
            $result[$affiliate->id] = self::project($affiliate);
        }

        return $result;
    }

    private static function project(Affiliate $affiliate): ConversionBeneficiary
    {
        return new ConversionBeneficiary(
            reference: $affiliate->lifecycleReference(),
            code: $affiliate->code,
            currency: $affiliate->currency,
            commercial: $affiliate->isCommercialPurpose(),
            commissionType: $affiliate->commission_type->value,
            commissionRate: $affiliate->commission_rate,
        );
    }
}

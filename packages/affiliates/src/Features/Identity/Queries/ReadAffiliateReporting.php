<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Queries;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateReportingReader;
use AIArmada\Affiliates\Models\Affiliate;
use Illuminate\Support\Str;
use LogicException;

final readonly class ReadAffiliateReporting implements AffiliateReportingReader
{
    public function available(): bool
    {
        $affiliate = new Affiliate;

        return $affiliate->getConnection()->getSchemaBuilder()->hasTable($affiliate->getTable());
    }

    public function nameFor(string $affiliateId, ?string $ownerType, ?string $ownerId, int $connectionIdentity): ?string
    {
        if (spl_object_id((new Affiliate)->getConnection()) !== $connectionIdentity) {
            throw new LogicException('Affiliate reporting requires a shared database connection.');
        }

        if (! Str::isUuid($affiliateId) || ! $this->available()) {
            return null;
        }

        $name = Affiliate::query()->forOwner(includeGlobal: true)
            ->whereKey($affiliateId)
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->value('name');

        return is_string($name) ? $name : null;
    }

    public function codeOptions(): array
    {
        $options = [];

        foreach (Affiliate::query()->forOwner()->orderBy('name')->orderBy('code')->select(['code', 'name'])->toBase()->cursor() as $row) {
            $code = mb_trim((string) $row->code);

            if ($code === '') {
                continue;
            }

            $name = mb_trim((string) $row->name);
            $options[$code] = $name !== '' ? "{$name} ({$code})" : $code;
        }

        return $options;
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Queries;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateCohortMember;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateReportingReader;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\CommerceSupport\Support\ConnectionDriver;
use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;

final readonly class ReadAffiliateReporting implements AffiliateReportingReader
{
    public function names(array $ids): array
    {
        $names = [];
        $ids = array_values(array_unique(array_filter($ids, static fn (string $id): bool => Str::isUuid($id))));

        foreach (array_chunk($ids, 500) as $chunk) {
            foreach (Affiliate::query()->forOwner()->whereKey($chunk)->pluck('name', 'id') as $id => $name) {
                $names[(string) $id] = (string) $name;
            }
        }

        return $names;
    }

    public function scopedIds(int $connectionIdentity, bool $commercialOnly = false): iterable
    {
        if (spl_object_id((new Affiliate)->getConnection()) !== $connectionIdentity) {
            throw new LogicException('Affiliate reporting requires a shared database connection.');
        }

        $query = Affiliate::query()->forOwner(includeGlobal: false)->select('id');

        if ($commercialOnly) {
            $query->whereCommercialPurpose();
        }

        foreach ($query->toBase()->lazyById(100) as $row) {
            yield (string) $row->id;
        }
    }

    public function cohorts(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $driver = ConnectionDriver::name((new Affiliate)->getConnection());
        $dateFormat = match ($driver) {
            'pgsql' => "to_char(created_at, 'YYYY-MM')",
            'sqlite' => "strftime('%Y-%m', created_at)",
            default => throw new RuntimeException("Unsupported database driver [{$driver}] for affiliate cohorts."),
        };

        $cohorts = [];

        foreach (Affiliate::query()->forOwner(includeGlobal: true)
            ->select(['id', 'status', 'deactivated_at'])
            ->selectRaw("{$dateFormat} as cohort_month")
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('cohort_month')->orderBy('id')->cursor() as $affiliate) {
            $month = (string) $affiliate->getAttribute('cohort_month');
            $cohorts[$month][] = new AffiliateCohortMember(
                (string) $affiliate->getKey(),
                $affiliate->isActive(),
                $affiliate->deactivated_at === null ? null : CarbonImmutable::instance($affiliate->deactivated_at),
            );
        }

        return $cohorts;
    }

    public function runForOwners(callable $callback): int
    {
        return (int) $this->ownerRunner()->run($callback);
    }

    public function summarizeForOwners(callable $callback): ?array
    {
        return $this->ownerRunner()->run($callback);
    }

    /** @return OwnerBatchRunner<Affiliate> */
    private function ownerRunner(): OwnerBatchRunner
    {
        return new OwnerBatchRunner(
            Affiliate::class,
            ['enabled' => 'affiliates.owner.enabled', 'include_global' => 'affiliates.owner.include_global'],
        );
    }

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

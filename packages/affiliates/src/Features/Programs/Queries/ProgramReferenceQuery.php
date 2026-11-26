<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Queries;

use AIArmada\Affiliates\Features\Programs\Api\ProgramReferenceReader;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\CommerceSupport\Support\LikeSearch;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

final readonly class ProgramReferenceQuery implements ProgramReferenceReader
{
    public function assertReadable(string $id): void
    {
        if (! Str::isUuid($id)) {
            throw (new ModelNotFoundException)->setModel(AffiliateProgram::class, [$id]);
        }

        AffiliateProgram::query()->findOrFail($id);
    }

    public function assertSharedConnection(int $connectionIdentity): void
    {
        if (spl_object_id((new AffiliateProgram)->getConnection()) !== $connectionIdentity) {
            throw new LogicException('Program references require a shared database connection.');
        }
    }

    public function assertWritable(string $id, int $connectionIdentity): void
    {
        $this->assertSharedConnection($connectionIdentity);

        if (! Str::isUuid($id)) {
            throw new AuthorizationException('Invalid program reference.');
        }

        if ((bool) config('affiliates.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(AffiliateProgram::class, $id);

            return;
        }

        $this->assertReadable($id);
    }

    public function lockForAdministration(string $id, int $connectionIdentity): void
    {
        $connection = (new AffiliateProgram)->getConnection();

        if (spl_object_id($connection) !== $connectionIdentity || $connection->transactionLevel() < 1) {
            throw new LogicException('Program administration requires a shared active database transaction.');
        }

        if (! Str::isUuid($id)) {
            throw (new ModelNotFoundException)->setModel(AffiliateProgram::class, [$id]);
        }

        if ((bool) config('affiliates.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(AffiliateProgram::class, $id);
        }

        AffiliateProgram::query()->lockForUpdate()->findOrFail($id);
    }

    public function exists(string $id): bool
    {
        if (! Str::isUuid($id)) {
            return false;
        }

        if (! config('affiliates.owner.enabled', false)) {
            return AffiliateProgram::query()->whereKey($id)->exists();
        }

        try {
            OwnerWriteGuard::findOrFailForOwner(
                AffiliateProgram::class,
                $id,
                includeGlobal: (bool) config('affiliates.owner.include_global', false),
            );

            return true;
        } catch (AuthorizationException | InvalidArgumentException | RuntimeException) {
            return false;
        }
    }

    /** @return array<string, string> */
    public function options(string $search = '', int $limit = 50): array
    {
        $query = AffiliateProgram::query()->forOwner(includeGlobal: (bool) config('affiliates.owner.include_global', false));
        $search = mb_trim($search);

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                LikeSearch::whereLike($builder, 'name', LikeSearch::contains($search));
            });
        }

        /** @var array<string, string> $options */
        $options = $query->orderBy('name')->orderBy('id')->limit(max(1, min(100, $limit)))->pluck('name', 'id')->all();

        return $options;
    }

    public function label(string $id): ?string
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        $name = AffiliateProgram::query()->forOwner(includeGlobal: (bool) config('affiliates.owner.include_global', false))->whereKey($id)->value('name');

        return is_string($name) ? $name : null;
    }
}

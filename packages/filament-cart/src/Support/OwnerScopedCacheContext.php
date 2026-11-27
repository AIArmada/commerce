<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;

final readonly class OwnerScopedCacheContext
{
    public function __construct(
        public ?Model $owner,
        public string $key,
    ) {}

    public static function for(string $logicalKey, bool $includeGlobal, bool $ownerScopingEnabled, ?Model $owner): self
    {
        if (! $ownerScopingEnabled) {
            return new self(null, $logicalKey . '.unscoped');
        }

        OwnerContext::assertResolvedOrExplicitGlobal(
            $owner,
            'Cart Filament resources require an owner context or explicit global context.',
        );

        $scope = $owner === null ? 'global' : 'owner';

        return new self(
            $owner,
            $logicalKey . '.' . $scope . '.' . ($includeGlobal ? 'include-global' : 'owner-only'),
        );
    }
}

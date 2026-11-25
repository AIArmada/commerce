<?php

declare(strict_types=1);

namespace AIArmada\Authz\Features\Impersonation\Api;

use Illuminate\Contracts\Auth\Authenticatable;

interface ImpersonationApi
{
    public function isImpersonating(): bool;

    public function getImpersonatorId(): mixed;

    public function getImpersonatorGuardName(): ?string;

    public function getImpersonatedGuardName(): ?string;

    public function getBackTo(): ?string;

    public function getImpersonator(): ?Authenticatable;

    public function take(Authenticatable $from, Authenticatable $to, ?string $guardName = null, ?string $backTo = null): bool;

    public function leave(): bool;

    public function clear(): void;

    public function getCurrentAuthGuardName(): ?string;

    public function canAccessTarget(Authenticatable $target): bool;
}

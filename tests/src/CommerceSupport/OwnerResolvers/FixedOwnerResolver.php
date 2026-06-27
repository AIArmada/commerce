<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Support\OwnerResolvers;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Illuminate\Database\Eloquent\Model;

final class FixedOwnerResolver implements OwnerResolverInterface
{
    public function __construct(private readonly ?Model $owner) {}

    public function resolve(): ?Model
    {
        return $this->owner;
    }
}

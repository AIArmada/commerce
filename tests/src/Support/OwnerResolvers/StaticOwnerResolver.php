<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Support\OwnerResolvers;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Illuminate\Database\Eloquent\Model;

final class StaticOwnerResolver implements OwnerResolverInterface
{
    public static ?Model $owner = null;

    public function resolve(): ?Model
    {
        return self::$owner;
    }
}

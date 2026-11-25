<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Tests\OwnerResolvers;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use Illuminate\Database\Eloquent\Model;

final class StaticOwnerResolver implements OwnerResolverInterface
{
    public static ?Model $owner = null;

    public function resolve(): ?RecordReference
    {
        return EloquentRecordReference::fromModel(self::$owner);
    }
}

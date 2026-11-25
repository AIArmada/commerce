<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Tests\OwnerResolvers;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Illuminate\Database\Eloquent\Model;

final class FixedOwnerResolver implements OwnerResolverInterface
{
    public function __construct(private readonly ?Model $owner) {}

    public function resolve(): \AIArmada\CommerceSupport\Data\RecordReference | null
    {
        return \AIArmada\CommerceSupport\Support\EloquentRecordReference::fromModel($this->owner);
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentInventory\Fixtures;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use Illuminate\Database\Eloquent\Model;

final class TestOwnerResolver implements OwnerResolverInterface
{
    public function __construct(private readonly ?Model $owner) {}

    public function resolve(): ?RecordReference
    {
        return EloquentRecordReference::fromModel($this->owner);
    }
}

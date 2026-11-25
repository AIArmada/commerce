<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Resolvers;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use AIArmada\Organizations\Contracts\CurrentOrganizationResolver;

final readonly class OrganizationOwnerResolver implements OwnerResolverInterface
{
    public function __construct(private CurrentOrganizationResolver $organizations) {}

    public function resolve(): ?RecordReference
    {
        return EloquentRecordReference::fromModel($this->organizations->resolve());
    }
}

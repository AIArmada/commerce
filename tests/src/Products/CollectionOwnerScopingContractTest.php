<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Products;

require_once __DIR__ . '/OwnerScopingContractsTest.php';

use AIArmada\Products\Models\Collection;

final class CollectionOwnerScopingContractTest extends AbstractProductOwnerScopingContractTest
{
    protected function getModelClass(): string
    {
        return Collection::class;
    }
}

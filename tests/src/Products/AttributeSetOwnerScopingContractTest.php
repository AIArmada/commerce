<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Products;

require_once __DIR__ . '/OwnerScopingContractsTest.php';

use AIArmada\Products\Models\AttributeSet;

final class AttributeSetOwnerScopingContractTest extends AbstractProductOwnerScopingContractTest
{
    protected function getModelClass(): string
    {
        return AttributeSet::class;
    }
}

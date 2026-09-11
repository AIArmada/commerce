<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Products;

require_once __DIR__ . '/OwnerScopingContractsTest.php';

use AIArmada\Products\Models\Attribute;

final class AttributeOwnerScopingContractTest extends AbstractProductOwnerScopingContractTest
{
    protected function getModelClass(): string
    {
        return Attribute::class;
    }
}

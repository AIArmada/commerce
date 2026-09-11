<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Products;

require_once __DIR__ . '/OwnerScopingContractsTest.php';

use AIArmada\Products\Models\Variant;

final class VariantOwnerScopingContractTest extends AbstractProductOwnerScopingContractTest
{
    protected function getModelClass(): string
    {
        return Variant::class;
    }
}

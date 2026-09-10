<?php

declare(strict_types=1);

namespace AIArmada\Membership\Tests\Unit;

require_once __DIR__ . '/OwnerScopingContractsTest.php';

use AIArmada\Membership\Models\MembershipApplication;

final class MembershipApplicationOwnerScopingContractTest extends AbstractMembershipOwnerScopingContractTest
{
    protected function getModelClass(): string
    {
        return MembershipApplication::class;
    }
}

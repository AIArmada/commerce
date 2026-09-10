<?php

declare(strict_types=1);

namespace AIArmada\Membership\Tests\Unit;

require_once __DIR__ . '/OwnerScopingContractsTest.php';

use AIArmada\Membership\Models\MembershipInvitation;

final class MembershipInvitationOwnerScopingContractTest extends AbstractMembershipOwnerScopingContractTest
{
    protected function getModelClass(): string
    {
        return MembershipInvitation::class;
    }
}

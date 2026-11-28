<?php

declare(strict_types=1);

namespace AIArmada\Membership\Contracts;

use AIArmada\Membership\Data\MembershipApplicationData;

interface MembershipApplicationNotifier
{
    public function notifySubmitted(MembershipApplicationData $application): void;

    public function notifyApproved(MembershipApplicationData $application): void;

    public function notifyRejected(MembershipApplicationData $application): void;
}

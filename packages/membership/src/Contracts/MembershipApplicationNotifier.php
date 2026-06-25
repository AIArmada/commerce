<?php

declare(strict_types=1);

namespace AIArmada\Membership\Contracts;

use AIArmada\Membership\Models\MembershipApplication;

interface MembershipApplicationNotifier
{
    public function notifySubmitted(MembershipApplication $application): void;

    public function notifyApproved(MembershipApplication $application): void;

    public function notifyRejected(MembershipApplication $application): void;
}

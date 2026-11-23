<?php

declare(strict_types=1);

namespace AIArmada\Membership\Contracts;

use AIArmada\Membership\Data\InvitationActorData;
use AIArmada\Membership\Data\InvitationRecipientData;

interface InvitationRecipientVerifier
{
    public function channel(): string;

    public function verify(InvitationRecipientData $recipient, InvitationActorData $actor): bool;
}

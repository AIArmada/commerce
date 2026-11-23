<?php

declare(strict_types=1);

namespace AIArmada\Membership\Channels;

use AIArmada\Membership\Contracts\InvitationRecipientVerifier;
use AIArmada\Membership\Data\InvitationActorData;
use AIArmada\Membership\Data\InvitationRecipientData;

final class EmailInvitationRecipientVerifier implements InvitationRecipientVerifier
{
    public function channel(): string
    {
        return EmailInvitationChannel::CHANNEL;
    }

    public function verify(InvitationRecipientData $recipient, InvitationActorData $actor): bool
    {
        if ($actor->email === null) {
            return false;
        }

        return mb_strtolower(mb_trim($actor->email)) === $recipient->identifier;
    }
}

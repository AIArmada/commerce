<?php

declare(strict_types=1);

namespace AIArmada\Membership\Tests\Fixtures;

use AIArmada\Membership\Contracts\InvitationRecipientVerifier;
use AIArmada\Membership\Data\InvitationActorData;
use AIArmada\Membership\Data\InvitationRecipientData;

final class FakeInvitationRecipientVerifier implements InvitationRecipientVerifier
{
    public const string CHANNEL = FakeInvitationChannel::CHANNEL;

    /**
     * @var array<string, string>
     */
    public array $identities = [];

    public function channel(): string
    {
        return self::CHANNEL;
    }

    public function verify(InvitationRecipientData $recipient, InvitationActorData $actor): bool
    {
        return ($this->identities[$actor->id] ?? null) === $recipient->identifier;
    }
}

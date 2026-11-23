<?php

declare(strict_types=1);

namespace AIArmada\Membership\Contracts;

use AIArmada\Membership\Data\InvitationDeliveryData;
use InvalidArgumentException;

interface InvitationChannel
{
    public function channel(): string;

    public function label(): string;

    /**
     * Validate and canonicalise a recipient identifier for this channel.
     *
     * @throws InvalidArgumentException when the identifier cannot address this channel
     */
    public function normalize(string $identifier): string;

    /**
     * Push the invitation over this channel's transport.
     *
     * The action dispatches `MembershipInvitationSent` for every channel, so
     * this hook exists for integrations that must actively deliver (SMS,
     * WhatsApp, social DMs). The built-in email channel relies on the event.
     */
    public function deliver(InvitationDeliveryData $delivery): void;
}

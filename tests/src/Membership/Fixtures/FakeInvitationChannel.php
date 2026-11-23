<?php

declare(strict_types=1);

namespace AIArmada\Membership\Tests\Fixtures;

use AIArmada\Membership\Contracts\InvitationChannel;
use AIArmada\Membership\Data\InvitationDeliveryData;
use InvalidArgumentException;

final class FakeInvitationChannel implements InvitationChannel
{
    public const string CHANNEL = 'sms';

    /**
     * @var list<InvitationDeliveryData>
     */
    public array $deliveries = [];

    public function channel(): string
    {
        return self::CHANNEL;
    }

    public function label(): string
    {
        return 'SMS';
    }

    public function normalize(string $identifier): string
    {
        $identifier = mb_trim($identifier);

        if (preg_match('/^\+[1-9]\d{6,14}$/', $identifier) !== 1) {
            throw new InvalidArgumentException('A valid E.164 phone number is required.');
        }

        return $identifier;
    }

    public function deliver(InvitationDeliveryData $delivery): void
    {
        $this->deliveries[] = $delivery;
    }
}

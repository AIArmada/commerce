<?php

declare(strict_types=1);

namespace AIArmada\Membership\Channels;

use AIArmada\Membership\Contracts\InvitationChannel;
use AIArmada\Membership\Data\InvitationDeliveryData;
use InvalidArgumentException;

final class EmailInvitationChannel implements InvitationChannel
{
    public const string CHANNEL = 'email';

    public function channel(): string
    {
        return self::CHANNEL;
    }

    public function label(): string
    {
        return 'Email';
    }

    public function normalize(string $identifier): string
    {
        $email = mb_strtolower(mb_trim($identifier));

        if ($email === '' || mb_strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('A valid invitation email address is required.');
        }

        return $email;
    }

    public function deliver(InvitationDeliveryData $delivery): void {}
}

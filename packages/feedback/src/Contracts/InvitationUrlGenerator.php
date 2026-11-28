<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Contracts;

interface InvitationUrlGenerator
{
    public function generate(string $invitationId, string $rawToken): string;
}

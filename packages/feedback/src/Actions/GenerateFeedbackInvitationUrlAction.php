<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\Feedback\Contracts\InvitationUrlGenerator;
use AIArmada\Feedback\Models\FeedbackInvitation;
use InvalidArgumentException;

final class GenerateFeedbackInvitationUrlAction
{
    public function __construct(
        private readonly InvitationUrlGenerator $urlGenerator,
    ) {}

    public function execute(FeedbackInvitation $invitation, string $rawToken): string
    {
        if (! hash_equals($invitation->token_hash, hash('sha256', $rawToken))) {
            throw new InvalidArgumentException('The raw invitation token does not match this invitation.');
        }

        return $this->urlGenerator->generate($invitation, $rawToken);
    }
}

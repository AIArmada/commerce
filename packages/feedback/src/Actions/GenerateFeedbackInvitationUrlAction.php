<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\Feedback\Contracts\InvitationUrlGenerator;
use AIArmada\Feedback\Models\FeedbackInvitation;

final class GenerateFeedbackInvitationUrlAction
{
    public function __construct(
        private readonly InvitationUrlGenerator $urlGenerator,
    ) {}

    public function execute(FeedbackInvitation $invitation): string
    {
        return $this->urlGenerator->generate($invitation, $invitation->token_hash);
    }
}

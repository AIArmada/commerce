<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Invitations\Api;

interface ManagesInvitationLifecycle
{
    public function start(string $invitationId): void;

    public function submit(string $invitationId): void;

    public function expire(string $invitationId): void;

    public function deleteForForm(string $formId): void;
}

<?php

declare(strict_types=1);

namespace AIArmada\Membership\Tests\Fixtures;

use AIArmada\Membership\Support\InvitationChannelManager;

trait RegistersFakeInvitationChannel
{
    protected FakeInvitationChannel $fakeChannel;

    protected FakeInvitationRecipientVerifier $fakeVerifier;

    protected function registerFakeInvitationChannel(): void
    {
        $this->fakeChannel = new FakeInvitationChannel;
        $this->fakeVerifier = new FakeInvitationRecipientVerifier;

        $manager = app(InvitationChannelManager::class);
        $manager->registerChannel($this->fakeChannel);
        $manager->registerVerifier($this->fakeVerifier);
    }
}

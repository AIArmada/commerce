<?php

declare(strict_types=1);

namespace AIArmada\Membership\Tests\Fixtures;

use AIArmada\Membership\Channels\InvitationChannelManager;

trait RegistersFakeInvitationChannel
{
    protected FakeInvitationChannel $fakeChannel;

    protected FakeInvitationRecipientVerifier $fakeVerifier;

    protected function registerFakeInvitationChannel(): void
    {
        $this->fakeChannel = new FakeInvitationChannel;
        $this->fakeVerifier = new FakeInvitationRecipientVerifier;

        $manager = new InvitationChannelManager;
        $manager->registerChannel($this->fakeChannel);
        $manager->registerVerifier($this->fakeVerifier);

        app()->instance(InvitationChannelManager::class, $manager);
    }
}

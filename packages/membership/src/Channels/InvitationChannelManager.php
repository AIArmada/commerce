<?php

declare(strict_types=1);

namespace AIArmada\Membership\Channels;

use AIArmada\Membership\Contracts\InvitationChannel;
use AIArmada\Membership\Contracts\InvitationRecipientVerifier;
use AIArmada\Membership\Data\InvitationActorData;
use AIArmada\Membership\Data\InvitationRecipientData;
use InvalidArgumentException;

final class InvitationChannelManager
{
    /**
     * @var array<string, InvitationChannel>
     */
    private array $channels = [];

    /**
     * @var array<string, InvitationRecipientVerifier>
     */
    private array $verifiers = [];

    public function __construct()
    {
        $this->registerChannel(new EmailInvitationChannel);
        $this->registerVerifier(new EmailInvitationRecipientVerifier);
    }

    public function registerChannel(InvitationChannel $channel): static
    {
        $this->channels[$channel->channel()] = $channel;

        return $this;
    }

    public function registerVerifier(InvitationRecipientVerifier $verifier): static
    {
        $this->verifiers[$verifier->channel()] = $verifier;

        return $this;
    }

    public function defaultChannelName(): string
    {
        return (string) config('membership.invitations.default_channel', EmailInvitationChannel::CHANNEL);
    }

    public function channel(string $name): InvitationChannel
    {
        $channel = $this->channels[$name] ?? $this->resolveChannel($name);

        if (! $channel instanceof InvitationChannel) {
            throw new InvalidArgumentException(sprintf('The invitation channel [%s] is not registered.', $name));
        }

        return $channel;
    }

    public function hasChannel(string $name): bool
    {
        return isset($this->channels[$name]) || $this->resolveChannel($name) instanceof InvitationChannel;
    }

    public function verifier(string $name): ?InvitationRecipientVerifier
    {
        return $this->verifiers[$name] ?? $this->resolveVerifier($name);
    }

    /**
     * @return array<string, string>
     */
    public function options(): array
    {
        $configured = config('membership.invitations.channels', []);
        $names = array_keys($this->channels);

        if (is_array($configured)) {
            $names = [...$names, ...array_keys($configured)];
        }

        $options = [];

        foreach (array_unique($names) as $name) {
            $channel = $this->channels[$name] ?? $this->resolveChannel((string) $name);

            if ($channel instanceof InvitationChannel) {
                $options[$name] = $channel->label();
            }
        }

        return $options;
    }

    public function verifyRecipient(string $channel, string $recipient, InvitationActorData $actor): bool
    {
        $verifier = $this->verifier($channel);

        if (! $verifier instanceof InvitationRecipientVerifier) {
            return false;
        }

        return $verifier->verify(new InvitationRecipientData($channel, $recipient), $actor);
    }

    private function resolveChannel(string $name): ?InvitationChannel
    {
        $class = config('membership.invitations.channels.' . $name);

        if (! is_string($class) || ! class_exists($class)) {
            return null;
        }

        $channel = app($class);

        return $channel instanceof InvitationChannel && $channel->channel() === $name ? $channel : null;
    }

    private function resolveVerifier(string $name): ?InvitationRecipientVerifier
    {
        $class = config('membership.invitations.verifiers.' . $name);

        if (! is_string($class) || ! class_exists($class)) {
            return null;
        }

        $verifier = app($class);

        return $verifier instanceof InvitationRecipientVerifier && $verifier->channel() === $name ? $verifier : null;
    }
}

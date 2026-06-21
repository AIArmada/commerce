<?php

declare(strict_types=1);

namespace AIArmada\Communications\Testing;

use AIArmada\Communications\Contracts\CommunicationManager;
use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Communications\Models\Communication;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Assert;

final class FakeCommunicationManager implements CommunicationManager
{
    /** @var array<int, array{notifiable: mixed, notification: Notification, context: ?CommunicationContextData, communication: Communication}> */
    private array $sent = [];

    /** @var array<int, array{notifiable: mixed, notification: Notification, channel: string, communication: ?Communication}> */
    private array $recordedNative = [];

    public function notify(
        mixed $notifiable,
        Notification $notification,
        ?CommunicationContextData $context = null,
    ): Communication {
        $communication = new Communication;
        $communication->id = (string) Str::uuid();

        $this->sent[] = [
            'notifiable' => $notifiable,
            'notification' => $notification,
            'context' => $context,
            'communication' => $communication,
        ];

        return $communication;
    }

    public function recordNative(
        mixed $notifiable,
        Notification $notification,
        string $channel,
    ): ?Communication {
        $communication = new Communication;
        $communication->id = (string) Str::uuid();

        $this->recordedNative[] = [
            'notifiable' => $notifiable,
            'notification' => $notification,
            'channel' => $channel,
            'communication' => $communication,
        ];

        return $communication;
    }

    public function assertSent(?callable $callback = null, ?int $count = null): void
    {
        $matches = $this->filterSent($callback);

        if ($count !== null) {
            Assert::assertCount($count, $matches, sprintf(
                'Expected %d sent communication(s), but %d matched.',
                $count,
                count($matches),
            ));
        } else {
            Assert::assertGreaterThan(0, count($matches), 'Expected a sent communication, but none matched the given callback.');
        }
    }

    public function assertNotSent(callable $callback): void
    {
        $matches = $this->filterSent($callback);

        Assert::assertCount(0, $matches, 'Expected no matching sent communication, but one or more matched.');
    }

    public function assertNothingSent(): void
    {
        Assert::assertCount(0, $this->sent, sprintf(
            'Expected no sent communications, but %d were sent.',
            count($this->sent),
        ));
    }

    public function assertSentTimes(int $times): void
    {
        Assert::assertCount($times, $this->sent, sprintf(
            'Expected %d sent communication(s), but %d were sent.',
            $times,
            count($this->sent),
        ));
    }

    public function reset(): void
    {
        $this->sent = [];
        $this->recordedNative = [];
    }

    /** @return array<int, array{notifiable: mixed, notification: Notification, context: ?CommunicationContextData, communication: Communication}> */
    public function sentNotifications(): array
    {
        return $this->sent;
    }

    private function filterSent(?callable $callback): array
    {
        if ($callback === null) {
            return $this->sent;
        }

        return array_values(array_filter(
            $this->sent,
            fn (array $record): bool => $callback(
                $record['notifiable'],
                $record['notification'],
                $record['context'] ?? null,
            ),
        ));
    }
}

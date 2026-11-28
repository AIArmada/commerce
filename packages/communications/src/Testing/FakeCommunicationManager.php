<?php

declare(strict_types=1);

namespace AIArmada\Communications\Testing;

use AIArmada\Communications\Contracts\CommunicationManager;
use AIArmada\Communications\Data\CommunicationContextData;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Assert;

final class FakeCommunicationManager implements CommunicationManager
{
    /** @var array<int, array{notifiable: mixed, notification: Notification, context: CommunicationContextData|null, communicationId: string}> */
    private array $sent = [];

    public function notify(
        mixed $notifiable,
        Notification $notification,
        ?CommunicationContextData $context = null,
    ): string {
        $communicationId = (string) Str::uuid();

        $this->sent[] = [
            'notifiable' => $notifiable,
            'notification' => $notification,
            'context' => $context,
            'communicationId' => $communicationId,
        ];

        return $communicationId;
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
    }

    /** @return array<int, array{notifiable: mixed, notification: Notification, context: CommunicationContextData|null, communicationId: string}> */
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

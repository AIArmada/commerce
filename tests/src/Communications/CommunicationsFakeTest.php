<?php

declare(strict_types=1);

use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Communications\Facades\Communications;
use AIArmada\Communications\Testing\FakeCommunicationManager;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use PHPUnit\Framework\AssertionFailedError;

beforeEach(function (): void {
    $this->fake = Communications::fake();
});

afterEach(function (): void {
    $this->fake->reset();
});

test('fake swaps manager binding', function (): void {
    expect(Communications::getFacadeRoot())->toBeInstanceOf(FakeCommunicationManager::class);
});

test('fake records sent notifications', function (): void {
    $notifiable = new class
    {
        use Notifiable;

        public function getKey(): string
        {
            return 'user-1';
        }
    };

    $notification = new class extends Notification
    {
        public function via(): array
        {
            return ['mail'];
        }
    };

    Communications::notify($notifiable, $notification);

    Communications::assertSent(function (mixed $n, Notification $notif, ?CommunicationContextData $context) use ($notification): bool {
        return $notif::class === $notification::class;
    });
});

test('assertNothingSent passes when nothing sent', function (): void {
    Communications::assertNothingSent();
});

test('assertNothingSent fails when notifications were sent', function (): void {
    $notifiable = new class
    {
        use Notifiable;

        public function getKey(): string
        {
            return 'user-1';
        }
    };

    Communications::notify($notifiable, new class extends Notification
    {
        public function via(): array
        {
            return ['mail'];
        }
    });

    expect(fn () => Communications::assertNothingSent())->toThrow(AssertionFailedError::class);
});

test('assertSent respects count constraint', function (): void {
    $notifiable = new class
    {
        use Notifiable;

        public function getKey(): string
        {
            return 'user-1';
        }
    };

    $notification = new class extends Notification
    {
        public function via(): array
        {
            return ['mail'];
        }
    };

    Communications::notify($notifiable, $notification);
    Communications::notify($notifiable, $notification);

    Communications::assertSent(count: 2);
});

test('assertSentTimes verifies exact count', function (): void {
    $notifiable = new class
    {
        use Notifiable;

        public function getKey(): string
        {
            return 'user-1';
        }
    };

    $notification = new class extends Notification
    {
        public function via(): array
        {
            return ['mail'];
        }
    };

    Communications::notify($notifiable, $notification);

    Communications::assertSentTimes(1);
});

test('assertNotSent passes when no match', function (): void {
    $notifiable = new class
    {
        use Notifiable;

        public function getKey(): string
        {
            return 'user-1';
        }
    };

    $notification = new class extends Notification
    {
        public function via(): array
        {
            return ['mail'];
        }
    };

    Communications::notify($notifiable, $notification);

    Communications::assertNotSent(function (): bool {
        return false;
    });
});

test('assertNotSent fails when match found', function (): void {
    $notifiable = new class
    {
        use Notifiable;

        public function getKey(): string
        {
            return 'user-1';
        }
    };

    Communications::notify($notifiable, new class extends Notification
    {
        public function via(): array
        {
            return ['mail'];
        }
    });

    expect(fn () => Communications::assertNotSent(function (): bool {
        return true;
    }))->toThrow(AssertionFailedError::class);
});

test('fake tracks context data', function (): void {
    $notifiable = new class
    {
        use Notifiable;

        public function getKey(): string
        {
            return 'user-1';
        }
    };

    $notification = new class extends Notification
    {
        public function via(): array
        {
            return ['mail'];
        }
    };

    $context = CommunicationContextData::from([
        'direction' => 'outbound',
        'category' => 'transactional',
        'purpose' => 'order-confirmed',
    ]);

    Communications::notify($notifiable, $notification, $context);

    Communications::assertSent(function (mixed $n, Notification $notif, ?CommunicationContextData $ctx) use ($context): bool {
        return $ctx !== null
            && $ctx->direction === $context->direction
            && $ctx->category === $context->category
            && $ctx->purpose === $context->purpose;
    });
});

test('fake returns communication with uuid', function (): void {
    $notifiable = new class
    {
        use Notifiable;

        public function getKey(): string
        {
            return 'user-1';
        }
    };

    $notification = new class extends Notification
    {
        public function via(): array
        {
            return ['mail'];
        }
    };

    $communication = Communications::notify($notifiable, $notification);

    expect($communication->id)->toBeString();
    expect(mb_strlen($communication->id))->toBe(36);
});

test('reset clears all recorded state', function (): void {
    $notifiable = new class
    {
        use Notifiable;

        public function getKey(): string
        {
            return 'user-1';
        }
    };

    $notification = new class extends Notification
    {
        public function via(): array
        {
            return ['mail'];
        }
    };

    Communications::notify($notifiable, $notification);
    Communications::assertSentTimes(1);

    $this->fake->reset();
    Communications::assertNothingSent();
});

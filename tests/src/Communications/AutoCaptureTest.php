<?php

declare(strict_types=1);

use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationContent;
use AIArmada\Communications\Models\CommunicationDelivery;
use AIArmada\Communications\Models\CommunicationRecipient;
use AIArmada\Communications\Traits\HasCommunicationContext;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;

beforeEach(function (): void {
    config()->set('communications.features.auto_capture', true);
});

test('auto-capture creates communication records on NotificationSending', function (): void {
    $notifiable = new class
    {
        use Notifiable;

        public function routeNotificationForMail(): string
        {
            return 'user@example.com';
        }

        public function getKey(): string
        {
            return 'notifiable-1';
        }
    };

    $notification = new class extends Notification
    {
        public function via(object $notifiable): array
        {
            return ['mail'];
        }

        public function toMail(object $notifiable): mixed
        {
            return (new MailMessage)
                ->subject('Hello')
                ->line('Test body');
        }
    };

    event(new NotificationSending($notifiable, $notification, 'mail'));

    $communication = Communication::query()->latest('id')->first();
    expect($communication)->not->toBeNull();
    expect($communication->direction->value)->toBe('outbound');
    expect($communication->category->value)->toBe('transactional');

    $recipient = CommunicationRecipient::query()->where('communication_id', $communication->id)->first();
    expect($recipient)->not->toBeNull();
    expect($recipient->role->value)->toBe('to');

    $content = CommunicationContent::query()->where('communication_id', $communication->id)->first();
    expect($content)->not->toBeNull();
    expect($content->channel)->toBe('mail');

    $delivery = CommunicationDelivery::query()->where('communication_id', $communication->id)->first();
    expect($delivery)->not->toBeNull();
    expect($delivery->channel)->toBe('mail');
    expect($delivery->status->value)->toBe('queued');
});

test('auto-capture handles multiple channels for same notification', function (): void {
    $notifiable = new class
    {
        use Notifiable;

        public function routeNotificationForMail(): string
        {
            return 'user@example.com';
        }

        public function routeNotificationForSms(): string
        {
            return '+1234567890';
        }

        public function getKey(): string
        {
            return 'notifiable-2';
        }
    };

    $notification = new class extends Notification
    {
        public function via(object $notifiable): array
        {
            return ['mail', 'sms'];
        }
    };

    event(new NotificationSending($notifiable, $notification, 'mail'));
    event(new NotificationSending($notifiable, $notification, 'sms'));

    $communications = Communication::query()->latest('id')->get();
    expect($communications)->toHaveCount(1);

    $deliveries = CommunicationDelivery::query()
        ->where('communication_id', $communications->first()->id)
        ->get();
    expect($deliveries)->toHaveCount(2);
});

test('auto-capture marks delivery as sent on NotificationSent', function (): void {
    $notifiable = new class
    {
        use Notifiable;

        public function routeNotificationForMail(): string
        {
            return 'user@example.com';
        }

        public function getKey(): string
        {
            return 'notifiable-3';
        }
    };

    $notification = new class extends Notification
    {
        public function via(object $notifiable): array
        {
            return ['mail'];
        }
    };

    event(new NotificationSending($notifiable, $notification, 'mail'));

    $delivery = CommunicationDelivery::query()->first();
    expect($delivery->status->value)->toBe('queued');

    event(new NotificationSent($notifiable, $notification, 'mail', 'msg_abc'));

    $delivery->refresh();
    expect($delivery->status->value)->toBe('sent');
    expect($delivery->provider_message_id)->toBe('msg_abc');
});

test('auto-capture does nothing when feature is disabled', function (): void {
    config()->set('communications.features.auto_capture', false);

    $notifiable = new class
    {
        use Notifiable;

        public function routeNotificationForMail(): string
        {
            return 'user@example.com';
        }

        public function getKey(): string
        {
            return 'notifiable-4';
        }
    };

    $notification = new class extends Notification
    {
        public function via(object $notifiable): array
        {
            return ['mail'];
        }
    };

    event(new NotificationSending($notifiable, $notification, 'mail'));

    expect(Communication::query()->count())->toBe(0);
    expect(CommunicationDelivery::query()->count())->toBe(0);
});

test('auto-capture skips notifications with existing communication id', function (): void {
    config()->set('communications.features.auto_capture', true);

    $notifiable = new class
    {
        use Notifiable;

        public function routeNotificationForMail(): string
        {
            return 'user@example.com';
        }

        public function getKey(): string
        {
            return 'notifiable-5';
        }
    };

    $notification = new class extends Notification
    {
        use HasCommunicationContext;

        public function via(object $notifiable): array
        {
            return ['mail'];
        }
    };

    $notification->withCommunicationContext(
        communicationId: 'managed-comm-1',
        deliveryIdsByChannel: ['mail' => 'managed-del-1'],
    );

    event(new NotificationSending($notifiable, $notification, 'mail'));

    expect(Communication::query()->count())->toBe(0);
});

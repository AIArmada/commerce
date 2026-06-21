<?php

declare(strict_types=1);

use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Communications\Facades\Communications;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationBatch;
use AIArmada\Communications\Services\CommunicationManagerService;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;

test('facade resolves manager service', function (): void {
    expect(Communications::getFacadeRoot())->toBeInstanceOf(CommunicationManagerService::class);
});

test('manager notify creates communication record', function (): void {
    $notifiable = new class
    {
        use Notifiable;

        public function routeNotificationForMail(): string
        {
            return 'test@example.com';
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
                ->subject('Test')
                ->line('Hello!');
        }
    };

    $context = CommunicationContextData::from([
        'direction' => 'outbound',
        'category' => 'transactional',
        'priority' => 'normal',
        'purpose' => 'manager-test',
    ]);

    $result = Communications::notify($notifiable, $notification, $context);

    expect($result)->toBeInstanceOf(Communication::class);
    expect($result->purpose)->toBe('manager-test');
});

test('manager recordNative returns null by default', function (): void {
    $notifiable = new class
    {
        use Notifiable;

        public function routeNotificationForMail(): string
        {
            return 'test@example.com';
        }
    };

    $notification = new class extends Notification
    {
        public function via(object $notifiable): array
        {
            return ['mail'];
        }
    };

    $result = Communications::recordNative($notifiable, $notification, 'mail');

    expect($result)->toBeNull();
});

test('config driven table names support prefix', function (): void {
    config()->set('communications.database.table_prefix', 'test_');
    config()->set('communications.database.tables.batches', 'test_communication_batches');

    $batch = new CommunicationBatch;
    expect($batch->getTable())->toBe('test_communication_batches');

    config()->set('communications.database.table_prefix', '');
    config()->set('communications.database.tables.batches', 'communication_batches');
});

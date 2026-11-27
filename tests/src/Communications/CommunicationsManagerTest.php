<?php

declare(strict_types=1);

use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Communications\Facades\Communications;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Features\Aggregate\Models\CommunicationBatch;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;

final class StreamAManagerNotifiable
{
    use Notifiable;

    public function routeNotificationForMail(): string
    {
        return 'test@example.com';
    }

    public function getKey(): string
    {
        return 'notifiable-manager-1';
    }
}

final class StreamAManagerNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Test')
            ->line('Hello!');
    }
}

test('manager notify creates communication record', function (): void {
    $notifiable = new StreamAManagerNotifiable;
    $notification = new StreamAManagerNotification;

    $context = CommunicationContextData::from([
        'direction' => 'outbound',
        'category' => 'transactional',
        'priority' => 'normal',
        'purpose' => 'manager-test',
    ]);

    $result = Communications::notify($notifiable, $notification, $context);

    expect($result)->toBeString();
    expect(Communication::query()->findOrFail($result)->purpose)->toBe('manager-test');
});

test('config driven table names support prefix', function (): void {
    config()->set('communications.database.table_prefix', 'test_');
    config()->set('communications.database.tables.batches', 'test_communication_batches');

    $batch = new CommunicationBatch;
    expect($batch->getTable())->toBe('test_communication_batches');

    config()->set('communications.database.table_prefix', '');
    config()->set('communications.database.tables.batches', 'communication_batches');
});

<?php

declare(strict_types=1);

namespace AIArmada\Events\Notifications;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerJobContext;
use AIArmada\Events\Models\EventPass;
use AIArmada\Events\Support\EventWriteGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class EventTicketNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private readonly string $passId;

    private readonly OwnerJobContext $ownerContext;

    public function __construct(
        EventPass $pass,
    ) {
        EventWriteGuard::findOrFail($pass->event_id);

        $owner = OwnerContext::resolve();

        $this->passId = (string) $pass->getKey();
        $this->ownerContext = $owner === null
            ? OwnerJobContext::explicitGlobal()
            : OwnerJobContext::fromOwnerModel($owner);

        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return OwnerContext::withOwner($this->ownerContext->toOwnerModel(), function (): MailMessage {
            $pass = EventPass::query()
                ->with(['event', 'occurrence', 'registration.participants'])
                ->findOrFail($this->passId);
            $eventName = (string) config('events.notifications.ticket.event_name', config('app.name', 'Laravel'));
            $brandName = (string) config('events.notifications.ticket.brand_name', config('app.name', 'Laravel'));

            return (new MailMessage)
                ->from(
                    config('events.notifications.ticket.from_address', config('mail.from.address', 'hello@example.com')),
                    config('events.notifications.ticket.from_name', config('app.name')),
                )
                ->subject(sprintf('Your Ticket - %s : %s', $brandName, $eventName))
                ->markdown('events::notifications.ticket', [
                    'pass' => $pass,
                    'eventName' => $eventName,
                    'brandName' => $brandName,
                    'passNo' => $pass->pass_no,
                ]);
        });
    }
}

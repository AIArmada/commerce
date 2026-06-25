<?php

declare(strict_types=1);

namespace AIArmada\Events\Notifications;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerJobContext;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Support\EventWriteGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class EventWelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private readonly string $registrationId;

    private readonly OwnerJobContext $ownerContext;

    public function __construct(
        EventRegistration $registration,
    ) {
        EventWriteGuard::findOrFail($registration->event_id);

        $owner = OwnerContext::resolve();

        $this->registrationId = (string) $registration->getKey();
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
            $registration = EventRegistration::query()->findOrFail($this->registrationId);
            $eventName = (string) config('events.notifications.welcome.event_name', config('app.name', 'Laravel'));
            $brandName = (string) config('events.notifications.welcome.brand_name', config('app.name', 'Laravel'));

            return (new MailMessage)
                ->from(
                    config('events.notifications.welcome.from_address', config('mail.from.address', 'hello@example.com')),
                    config('events.notifications.welcome.from_name', config('app.name')),
                )
                ->subject(sprintf("You're In - %s : %s Registration Confirmed", $brandName, $eventName))
                ->markdown('events::notifications.welcome', [
                    'registration' => $registration,
                    'eventName' => $eventName,
                    'brandName' => $brandName,
                ]);
        });
    }
}

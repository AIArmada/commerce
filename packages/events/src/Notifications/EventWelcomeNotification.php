<?php

declare(strict_types=1);

namespace AIArmada\Events\Notifications;

use AIArmada\CommerceSupport\Data\OwnerJobContext;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerJobContextMapper;
use AIArmada\Events\Data\EventWelcomeNotificationData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class EventWelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly EventWelcomeNotificationData $data,
    ) {
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
        return OwnerContext::withOwner($this->ownerModel(), function (): MailMessage {
            return (new MailMessage)
                ->from($this->data->fromAddress, $this->data->fromName)
                ->subject(sprintf("You're In - %s : %s Registration Confirmed", $this->data->brandName, $this->data->eventName))
                ->markdown('events::notifications.welcome', [
                    'eventName' => $this->data->eventName,
                    'brandName' => $this->data->brandName,
                    'registrationNo' => $this->data->registrationNo,
                ]);
        });
    }

    private function ownerModel(): ?Model
    {
        if ($this->data->ownerType === null || $this->data->ownerId === null) {
            return null;
        }

        return OwnerJobContextMapper::resolve(new OwnerJobContext($this->data->ownerType, $this->data->ownerId));
    }
}

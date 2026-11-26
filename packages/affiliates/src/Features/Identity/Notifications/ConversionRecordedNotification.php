<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Notifications;

use AIArmada\CommerceSupport\Support\MoneyFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ConversionRecordedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $currency,
        private readonly int $commissionMinor,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $amount = MoneyFormatter::decimalFromMinor($this->commissionMinor, $this->currency);

        return (new MailMessage)
            ->subject("New commission: {$this->currency} {$amount}")
            ->line("A conversion earned you {$this->currency} {$amount}.");
    }
}

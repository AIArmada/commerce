<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Notifications;

use AIArmada\Affiliates\Data\AffiliateConversionData;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ConversionRecordedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly AffiliateConversionData $conversion,
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
        $amount = MoneyFormatter::decimalFromMinor($this->conversion->commissionMinor, $this->conversion->commissionCurrency);

        return (new MailMessage)
            ->subject("New commission: {$this->conversion->commissionCurrency} {$amount}")
            ->line("A conversion earned you {$this->conversion->commissionCurrency} {$amount}.");
    }
}

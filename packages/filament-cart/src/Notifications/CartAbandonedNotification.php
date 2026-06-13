<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class CartAbandonedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $recoveryData
     */
    public function __construct(
        private readonly array $recoveryData,
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
        $brandName = (string) config('filament-cart.notifications.abandoned_cart.brand_name', 'Unfair Advantage');
        $offerName = (string) ($this->recoveryData['offer_name'] ?? 'AI Awakening');
        $retryUrl = (string) ($this->recoveryData['retry_url'] ?? '#');
        $formattedTotal = (string) ($this->recoveryData['formatted_total'] ?? '');

        return (new MailMessage)
            ->from(
                config('filament-cart.notifications.abandoned_cart.from_address', 'info@unfairadvantage.my'),
                config('filament-cart.notifications.abandoned_cart.from_name', config('app.name')),
            )
            ->subject(sprintf('Your %s checkout is waiting — complete your registration', $offerName))
            ->markdown('filament-cart::notifications.cart-abandoned', [
                'brandName' => $brandName,
                'offerName' => $offerName,
                'retryUrl' => $retryUrl,
                'formattedTotal' => $formattedTotal,
                'preferredDate' => $this->recoveryData['preferred_date'] ?? null,
            ]);
    }
}

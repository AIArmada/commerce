<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Listeners;

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentCart\Events\CartAbandoned;
use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Notifications\CartAbandonedNotification;
use Illuminate\Support\Facades\Notification;

final class SendCartAbandonedNotification
{
    public function handle(CartAbandoned $event): void
    {
        $owner = OwnerContext::fromTypeAndId($event->ownerType, $event->ownerId);

        $cart = OwnerContext::withOwner($owner, function () use ($event): ?Cart {
            return Cart::query()->find($event->cartId);
        });

        if ($cart === null) {
            return;
        }

        $session = OwnerContext::withOwner($owner, function () use ($cart): ?CheckoutSession {
            return CheckoutSession::query()
                ->where('cart_id', $cart->getKey())
                ->latest()
                ->first();
        });

        if ($session === null) {
            return;
        }

        $billingData = $session->billing_data ?? [];
        $purchaserEmail = $billingData['email'] ?? null;

        if (! is_string($purchaserEmail) || $purchaserEmail === '') {
            return;
        }

        $items = is_array($cart->items) ? array_values($cart->items) : [];
        $firstItem = $items[0] ?? [];
        $itemAttributes = $firstItem['attributes'] ?? [];
        $offerName = $firstItem['name'] ?? 'Event';
        $preferredDate = $itemAttributes['preferred_date'] ?? null;
        $formattedTotal = $cart->formatMoney($cart->total);
        $retryUrl = $this->resolveRetryUrl($session);

        Notification::route('mail', $purchaserEmail)->notify(new CartAbandonedNotification([
            'offer_name' => $offerName,
            'preferred_date' => is_string($preferredDate) ? $preferredDate : null,
            'formatted_total' => $formattedTotal,
            'retry_url' => $retryUrl,
        ]));
    }

    private function resolveRetryUrl(CheckoutSession $session): string
    {
        if (is_string($session->payment_redirect_url) && $session->payment_redirect_url !== '') {
            return $session->payment_redirect_url;
        }

        return (string) config('app.url');
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Listeners;

use AIArmada\Cart\Events\CartAbandoned;
use AIArmada\Cart\Snapshots\CartSnapshot;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentCart\Notifications\CartAbandonedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

final class SendCartAbandonedNotification implements ShouldQueue
{
    public function handle(CartAbandoned $event): void
    {
        if (! class_exists(CheckoutSession::class)) {
            return;
        }

        $owner = OwnerContext::fromTypeAndId($event->ownerType, $event->ownerId);

        $cart = OwnerContext::withOwner($owner, function () use ($event): ?CartSnapshot {
            return CartSnapshot::query()->find($event->cartId);
        });

        if ($cart === null) {
            return;
        }

        $session = $this->findLatestSession($cart, $owner);

        if ($session === null) {
            return;
        }

        $billingData = is_array($session->billing_data) ? $session->billing_data : [];
        $purchaserEmail = $billingData['email'] ?? null;

        if (! is_string($purchaserEmail) || filter_var($purchaserEmail, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        $items = is_array($cart->items) ? array_values($cart->items) : [];
        $firstItem = $items[0] ?? [];
        $itemAttributes = is_array($firstItem['attributes'] ?? null) ? $firstItem['attributes'] : [];
        $offerName = is_string($firstItem['name'] ?? null) ? $firstItem['name'] : 'Event';
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

    private function findLatestSession(CartSnapshot $cart, ?Model $owner): ?CheckoutSession
    {
        return OwnerContext::withOwner($owner, function () use ($cart): ?CheckoutSession {
            return CheckoutSession::query()
                ->where('cart_id', $cart->getKey())
                ->latest()
                ->first();
        });
    }

    private function resolveRetryUrl(CheckoutSession $session): string
    {
        $fallback = (string) config('app.url');
        $candidate = $session->payment_redirect_url;

        if (! is_string($candidate) || $candidate === '') {
            return $fallback;
        }

        if (filter_var($candidate, FILTER_VALIDATE_URL) === false) {
            return $fallback;
        }

        $scheme = mb_strtolower((string) parse_url($candidate, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return $fallback;
        }

        $allowedHosts = config('filament-cart.notifications.abandoned_cart.allowed_retry_hosts', []);

        if (is_array($allowedHosts) && $allowedHosts !== []) {
            $host = mb_strtolower((string) parse_url($candidate, PHP_URL_HOST));
            $allowed = array_map(static fn (mixed $value): string => mb_strtolower((string) $value), $allowedHosts);

            if (! in_array($host, $allowed, true)) {
                return $fallback;
            }
        }

        return $candidate;
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Drivers;

use AIArmada\Cashier\Contracts\GatewayHealthProbe;
use AIArmada\Chip\Services\ChipCollectService;
use Stripe\Service\AccountService;
use Stripe\StripeClient;

final class GatewayHealthDriver implements GatewayHealthProbe
{
    public function fingerprint(string $gateway): string
    {
        return hash('sha256', serialize(match ($gateway) {
            'stripe' => [$this->stripeSecret()],
            'chip' => [config('chip.collect.brand_id'), config('chip.collect.api_key')],
            default => [],
        }));
    }

    public function probe(string $gateway): string
    {
        return match ($gateway) {
            'stripe' => $this->stripe(),
            'chip' => $this->chip(),
            default => 'unknown',
        };
    }

    private function stripe(): string
    {
        $secret = $this->stripeSecret();

        if ($secret === null || str_contains($secret, 'xxx') || str_contains($secret, 'placeholder')) {
            return 'not_configured';
        }

        if (! class_exists(StripeClient::class)) {
            return 'sdk_missing';
        }

        (new AccountService(new StripeClient($secret)))->retrieve();

        return 'healthy';
    }

    private function chip(): string
    {
        $brandId = config('chip.collect.brand_id');
        $apiKey = config('chip.collect.api_key');

        if (! is_string($brandId) || $brandId === '' || ! is_string($apiKey) || $apiKey === '') {
            return 'not_configured';
        }

        if (! class_exists(ChipCollectService::class)) {
            return 'sdk_missing';
        }

        app(ChipCollectService::class)->getAccountBalance();

        return 'healthy';
    }

    private function stripeSecret(): ?string
    {
        foreach (['services.stripe.secret', 'cashier.gateways.stripe.secret'] as $key) {
            $secret = config($key);

            if (is_string($secret) && $secret !== '') {
                return $secret;
            }
        }

        return null;
    }
}

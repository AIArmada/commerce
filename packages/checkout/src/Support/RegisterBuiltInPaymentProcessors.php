<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Support;

use AIArmada\Cashier\GatewayManager;
use AIArmada\CashierChip\Cashier;
use AIArmada\Checkout\Integrations\Payment\CashierChipProcessor;
use AIArmada\Checkout\Integrations\Payment\CashierProcessor;
use AIArmada\Checkout\Integrations\Payment\ChipProcessor;
use AIArmada\Checkout\Services\PaymentGatewayResolver;
use AIArmada\Chip\Facades\Chip;

final class RegisterBuiltInPaymentProcessors
{
    public function register(PaymentGatewayResolver $resolver): void
    {
        $gateways = (array) config('checkout.payment.gateways', []);

        if (class_exists(GatewayManager::class) && ($gateways['cashier']['enabled'] ?? true)) {
            $resolver->register('cashier', app(CashierProcessor::class));
        }

        if (class_exists(Cashier::class) && ($gateways['cashier-chip']['enabled'] ?? true)) {
            $resolver->register('cashier-chip', app(CashierChipProcessor::class));
        }

        if (class_exists(Chip::class) && ($gateways['chip']['enabled'] ?? true)) {
            $resolver->register('chip', app(ChipProcessor::class));
        }
    }
}

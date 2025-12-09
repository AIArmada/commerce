<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services\Payouts;

use AIArmada\Affiliates\Contracts\PayoutProcessorInterface;
use AIArmada\Affiliates\Enums\PayoutMethodType;
use InvalidArgumentException;

final class PayoutProcessorFactory
{
    /**
     * @var array<string, class-string<PayoutProcessorInterface>>
     */
    private array $processors = [
        'manual' => ManualPayoutProcessor::class,
        'bank_transfer' => ManualPayoutProcessor::class,
        'check' => ManualPayoutProcessor::class,
        'wire' => ManualPayoutProcessor::class,
        'stripe_connect' => StripeConnectProcessor::class,
        'paypal' => PayPalProcessor::class,
    ];

    public function make(string | PayoutMethodType $type): PayoutProcessorInterface
    {
        $typeString = $type instanceof PayoutMethodType ? $type->value : $type;

        if (! isset($this->processors[$typeString])) {
            throw new InvalidArgumentException("Unknown payout processor type: {$typeString}");
        }

        return app($this->processors[$typeString]);
    }

    public function register(string $type, string $processorClass): void
    {
        if (! is_a($processorClass, PayoutProcessorInterface::class, true)) {
            throw new InvalidArgumentException(
                'Processor class must implement ' . PayoutProcessorInterface::class
            );
        }

        $this->processors[$type] = $processorClass;
    }

    /**
     * @return array<string>
     */
    public function getAvailableProcessors(): array
    {
        return array_keys($this->processors);
    }

    public function hasProcessor(string $type): bool
    {
        return isset($this->processors[$type]);
    }
}

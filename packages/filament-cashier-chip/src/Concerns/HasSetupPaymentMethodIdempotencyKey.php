<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Concerns;

use Illuminate\Support\Str;

trait HasSetupPaymentMethodIdempotencyKey
{
    /**
     * The key for the current payment-method setup attempt.
     */
    public ?string $setupPaymentMethodIdempotencyKey = null;

    /**
     * Add the current setup-attempt key to billable options.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function setupPaymentMethodOptions(array $options = []): array
    {
        $options['idempotency_key'] = $this->getSetupPaymentMethodIdempotencyKey();

        return $options;
    }

    /**
     * Get or create the key for the current setup attempt.
     */
    protected function getSetupPaymentMethodIdempotencyKey(): string
    {
        return $this->setupPaymentMethodIdempotencyKey ??= (string) Str::uuid();
    }

    /**
     * Start a new setup attempt after the current one has been created.
     */
    protected function resetSetupPaymentMethodIdempotencyKey(): void
    {
        $this->setupPaymentMethodIdempotencyKey = null;
    }
}

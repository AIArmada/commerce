<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

use AIArmada\Chip\Data\Casts\MoneyCast;
use AIArmada\Chip\Data\Transformers\MoneyTransformer;
use Akaunting\Money\Money;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;

final class PaymentData extends ChipData
{
    public function __construct(
        public readonly bool $is_outgoing,
        public readonly string $payment_type,
        #[WithCast(MoneyCast::class)]
        #[WithTransformer(MoneyTransformer::class)]
        public readonly Money $amount,
        #[WithCast(MoneyCast::class)]
        #[WithTransformer(MoneyTransformer::class)]
        public readonly Money $net_amount,
        #[WithCast(MoneyCast::class)]
        #[WithTransformer(MoneyTransformer::class)]
        public readonly Money $fee_amount,
        #[WithCast(MoneyCast::class)]
        #[WithTransformer(MoneyTransformer::class)]
        public readonly Money $pending_amount,
        public readonly ?int $pending_unfreeze_on,
        public readonly ?string $description,
        public readonly ?int $paid_on,
        public readonly ?int $remote_paid_on,
        public readonly ?string $type = null,
        public readonly ?string $id = null,
        public readonly ?int $created_on = null,
        public readonly ?int $updated_on = null,
        public readonly ?ClientDetailsData $client = null,
        /** @var array<string, mixed> */
        public readonly array $transaction_data = [],
        public readonly ?RelatedObjectData $related_to = null,
        public readonly ?string $reference_generated = null,
        public readonly ?string $reference = null,
        public readonly ?string $account_id = null,
        public readonly ?string $company_id = null,
        public readonly ?bool $is_test = null,
        public readonly ?string $user_id = null,
        public readonly ?string $brand_id = null,
        public readonly ?string $status = null,
    ) {}

    /**
     * Create a Payment from array data (typically from CHIP API response).
     * Amounts in the array are expected to be in cents (minor units).
     *
     * @param  array<string, mixed>|self  ...$payloads
     */
    public static function from(mixed ...$payloads): static
    {
        $data = self::resolvePayload(...$payloads);

        $paymentPayload = $data;

        if (isset($data['payment']) && is_array($data['payment'])) {
            $paymentPayload = $data['payment'];
        } elseif (! isset($data['amount']) && isset($data['purchase']) && is_array($data['purchase'])) {
            $paymentPayload = [
                'amount' => $data['purchase']['total'] ?? 0,
                'currency' => $data['purchase']['currency'] ?? 'MYR',
            ];
        }

        $currency = $paymentPayload['currency'] ?? $data['currency'] ?? 'MYR';

        return new self(
            is_outgoing: $paymentPayload['is_outgoing'] ?? false,
            payment_type: $paymentPayload['payment_type'] ?? 'purchase',
            amount: Money::{$currency}((int) ($paymentPayload['amount'] ?? 0)),
            net_amount: Money::{$currency}((int) ($paymentPayload['net_amount'] ?? 0)),
            fee_amount: Money::{$currency}((int) ($paymentPayload['fee_amount'] ?? 0)),
            pending_amount: Money::{$currency}((int) ($paymentPayload['pending_amount'] ?? 0)),
            pending_unfreeze_on: isset($paymentPayload['pending_unfreeze_on']) ? (int) $paymentPayload['pending_unfreeze_on'] : null,
            description: $paymentPayload['description'] ?? null,
            paid_on: isset($paymentPayload['paid_on']) ? (int) $paymentPayload['paid_on'] : null,
            remote_paid_on: isset($paymentPayload['remote_paid_on']) ? (int) $paymentPayload['remote_paid_on'] : null,
            type: $data['type'] ?? null,
            id: $data['id'] ?? null,
            created_on: isset($data['created_on']) ? (int) $data['created_on'] : null,
            updated_on: isset($data['updated_on']) ? (int) $data['updated_on'] : null,
            client: isset($data['client']) && is_array($data['client']) ? ClientDetailsData::from($data['client']) : null,
            transaction_data: isset($data['transaction_data']) && is_array($data['transaction_data']) ? $data['transaction_data'] : [],
            related_to: isset($data['related_to']) && is_array($data['related_to']) ? RelatedObjectData::from($data['related_to']) : null,
            reference_generated: $data['reference_generated'] ?? null,
            reference: $data['reference'] ?? null,
            account_id: $data['account_id'] ?? null,
            company_id: $data['company_id'] ?? null,
            is_test: isset($data['is_test']) ? (bool) $data['is_test'] : null,
            user_id: $data['user_id'] ?? null,
            brand_id: $data['brand_id'] ?? null,
            status: $data['status'] ?? null,
        );
    }

    /**
     * Create a Payment from a webhook payload only when the payload contains
     * the minimum documented money fields required for a real payment object.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromWebhookPayload(array $payload): ?self
    {
        if (! self::hasWebhookPaymentPayload($payload)) {
            return null;
        }

        return self::from($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function hasWebhookPaymentPayload(array $payload): bool
    {
        if (isset($payload['payment']) && is_array($payload['payment'])) {
            return self::hasMoneyFields($payload['payment']);
        }

        return self::hasMoneyFields($payload);
    }

    /**
     * Get the currency code for this payment.
     */
    public function getCurrency(): string
    {
        return $this->amount->getCurrency()->getCurrency();
    }

    /**
     * Get amount in cents for API communication.
     */
    public function getAmountInCents(): int
    {
        return (int) $this->amount->getAmount();
    }

    /**
     * Get net amount in cents for API communication.
     */
    public function getNetAmountInCents(): int
    {
        return (int) $this->net_amount->getAmount();
    }

    /**
     * Get fee amount in cents for API communication.
     */
    public function getFeeAmountInCents(): int
    {
        return (int) $this->fee_amount->getAmount();
    }

    /**
     * Get pending amount in cents for API communication.
     */
    public function getPendingAmountInCents(): int
    {
        return (int) $this->pending_amount->getAmount();
    }

    public function getPaymentId(): ?string
    {
        return $this->id;
    }

    public function getRelatedPurchaseId(): ?string
    {
        if ($this->related_to?->isPurchase() === true) {
            return $this->related_to->id;
        }

        if ($this->type === 'purchase') {
            return $this->id;
        }

        return null;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function getClientEmail(): ?string
    {
        return $this->client?->email;
    }

    public function getClientName(): ?string
    {
        return $this->client?->full_name;
    }

    public function getPaidAt(): ?CarbonImmutable
    {
        return $this->paid_on ? CarbonImmutable::createFromTimestamp($this->paid_on) : null;
    }

    public function getRemotePaidAt(): ?CarbonImmutable
    {
        return $this->remote_paid_on ? CarbonImmutable::createFromTimestamp($this->remote_paid_on) : null;
    }

    public function getPendingUnfreezeAt(): ?CarbonImmutable
    {
        return $this->pending_unfreeze_on ? CarbonImmutable::createFromTimestamp($this->pending_unfreeze_on) : null;
    }

    public function getCreatedAt(): ?CarbonImmutable
    {
        return $this->created_on ? CarbonImmutable::createFromTimestamp($this->created_on) : null;
    }

    public function getUpdatedAt(): ?CarbonImmutable
    {
        return $this->updated_on ? CarbonImmutable::createFromTimestamp($this->updated_on) : null;
    }

    public function isPaid(): bool
    {
        return $this->paid_on !== null;
    }

    public function isTest(): bool
    {
        return $this->is_test ?? true;
    }

    /**
     * Convert to array for CHIP API (amounts in cents).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'is_outgoing' => $this->is_outgoing,
            'payment_type' => $this->payment_type,
            'amount' => $this->getAmountInCents(),
            'currency' => $this->getCurrency(),
            'net_amount' => $this->getNetAmountInCents(),
            'fee_amount' => $this->getFeeAmountInCents(),
            'pending_amount' => $this->getPendingAmountInCents(),
            'pending_unfreeze_on' => $this->pending_unfreeze_on,
            'description' => $this->description,
            'paid_on' => $this->paid_on,
            'remote_paid_on' => $this->remote_paid_on,
            'type' => $this->type,
            'id' => $this->id,
            'created_on' => $this->created_on,
            'updated_on' => $this->updated_on,
            'client' => $this->client?->toArray(),
            'transaction_data' => $this->transaction_data,
            'related_to' => $this->related_to?->toArray(),
            'reference_generated' => $this->reference_generated,
            'reference' => $this->reference,
            'account_id' => $this->account_id,
            'company_id' => $this->company_id,
            'is_test' => $this->is_test,
            'user_id' => $this->user_id,
            'brand_id' => $this->brand_id,
            'status' => $this->status,
        ], static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function hasMoneyFields(array $payload): bool
    {
        $amount = $payload['amount'] ?? null;
        $currency = $payload['currency'] ?? null;

        return is_numeric($amount)
            && is_string($currency)
            && $currency !== '';
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

use AIArmada\Chip\Data\Casts\MoneyCast;
use AIArmada\Chip\Data\Transformers\MoneyTransformer;
use Akaunting\Money\Money;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;

/**
 * Value object for CHIP's Payout resource.
 *
 * CHIP puts payout money in the nested `payment` object and the beneficiary
 * details in the nested `client` object.
 */
final class PayoutData extends ChipData
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly int $created_on,
        public readonly int $updated_on,
        public readonly string $status,
        #[WithCast(MoneyCast::class)]
        #[WithTransformer(MoneyTransformer::class)]
        public readonly Money $amount,
        public readonly string $currency,
        public readonly ClientDetailsData $client,
        public readonly PaymentData $payment,
        /** @var array<string, mixed> */
        public readonly array $transaction_data,
        public readonly ?string $reference_generated,
        public readonly ?string $reference,
        public readonly ?string $sender_name,
        public readonly ?string $recipient_card_country,
        public readonly ?string $recipient_card_brand,
        public readonly ?string $execution_url,
        public readonly string $brand_id,
        public readonly ?string $company_id,
        public readonly bool $is_test,
        public readonly ?string $user_id,
        /** @var array<int, mixed> */
        public readonly array $status_history,
        /** @var array<string, mixed> */
        public readonly array $error,
    ) {}

    public static function from(mixed ...$payloads): static
    {
        $data = self::resolvePayload(...$payloads);
        $payment = $data['payment'] ?? null;
        $client = $data['client'] ?? null;

        if (! is_array($payment) || ! is_array($client)) {
            throw new InvalidArgumentException('CHIP Payout payload must contain payment and client objects.');
        }

        $currency = $payment['currency'] ?? null;
        $amount = $payment['amount'] ?? null;

        if (! is_string($currency) || $currency === '' || ! is_numeric($amount)) {
            throw new InvalidArgumentException('CHIP Payout payment must contain a currency and numeric amount.');
        }

        $paymentData = PaymentData::from($payment);

        $transactionData = is_array($data['transaction_data'] ?? null)
            ? $data['transaction_data']
            : [];

        return new self(
            id: (string) ($data['id'] ?? ''),
            type: (string) ($data['type'] ?? 'payout'),
            created_on: (int) ($data['created_on'] ?? 0),
            updated_on: (int) ($data['updated_on'] ?? 0),
            status: (string) ($data['status'] ?? 'initialized'),
            amount: Money::{$currency}((int) $amount),
            currency: $currency,
            client: ClientDetailsData::from($client),
            payment: $paymentData,
            transaction_data: $transactionData,
            reference_generated: self::nullableString($data['reference_generated'] ?? null),
            reference: self::nullableString($data['reference'] ?? null),
            sender_name: self::nullableString($data['sender_name'] ?? null),
            recipient_card_country: self::nullableString($data['recipient_card_country'] ?? null),
            recipient_card_brand: self::nullableString($data['recipient_card_brand'] ?? null),
            execution_url: self::nullableString($data['execution_url'] ?? null),
            brand_id: (string) ($data['brand_id'] ?? ''),
            company_id: self::nullableString($data['company_id'] ?? null),
            is_test: (bool) ($data['is_test'] ?? false),
            user_id: self::nullableString($data['user_id'] ?? null),
            status_history: is_array($data['status_history'] ?? null) ? $data['status_history'] : [],
            error: self::lastAttemptError($transactionData),
        );
    }

    public function getCreatedAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestampUTC($this->created_on);
    }

    public function getUpdatedAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestampUTC($this->updated_on);
    }

    public function getAmountInCents(): int
    {
        return (int) $this->amount->getAmount();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'error';
    }

    public function hasError(): bool
    {
        return $this->error !== [];
    }

    public function getErrorMessage(): ?string
    {
        return self::nullableString($this->error['message'] ?? null);
    }

    public function getErrorCode(): ?string
    {
        return self::nullableString($this->error['code'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'created_on' => $this->created_on,
            'updated_on' => $this->updated_on,
            'payment' => $this->payment->toArray(),
            'client' => $this->client->toArray(),
            'transaction_data' => $this->transaction_data,
            'reference_generated' => $this->reference_generated,
            'reference' => $this->reference,
            'status' => $this->status,
            'status_history' => $this->status_history,
            'sender_name' => $this->sender_name,
            'recipient_card_country' => $this->recipient_card_country,
            'recipient_card_brand' => $this->recipient_card_brand,
            'execution_url' => $this->execution_url,
            'brand_id' => $this->brand_id,
            'company_id' => $this->company_id,
            'is_test' => $this->is_test,
            'user_id' => $this->user_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $transactionData
     * @return array<string, mixed>
     */
    private static function lastAttemptError(array $transactionData): array
    {
        $attempts = $transactionData['attempts'] ?? [];

        if (! is_array($attempts)) {
            return [];
        }

        foreach ($attempts as $attempt) {
            if (! is_array($attempt) || ! is_array($attempt['error'] ?? null)) {
                continue;
            }

            return $attempt['error'];
        }

        return [];
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}

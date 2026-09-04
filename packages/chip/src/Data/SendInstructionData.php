<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

use AIArmada\Chip\Enums\SendInstructionState;
use Carbon\CarbonImmutable;

final class SendInstructionData extends ChipData
{
    public function __construct(
        public readonly int $id,
        public readonly int $bank_account_id,
        public readonly string $amount,
        public readonly string $state,
        public readonly string $email,
        public readonly string $description,
        public readonly string $reference,
        public readonly bool $send_recipient_receipt,
        public readonly ?string $receipt_url,
        public readonly ?string $slug,
        public readonly string $created_at,
        public readonly string $updated_at,
    ) {}

    public static function from(mixed ...$payloads): static
    {
        $data = self::resolvePayload(...$payloads);

        return new self(
            id: (int) $data['id'],
            bank_account_id: (int) $data['bank_account_id'],
            amount: (string) $data['amount'],
            state: SendInstructionState::from((string) $data['state'])->value,
            email: (string) $data['email'],
            description: (string) $data['description'],
            reference: (string) $data['reference'],
            send_recipient_receipt: (bool) $data['send_recipient_receipt'],
            receipt_url: $data['receipt_url'] ?? null,
            slug: $data['slug'] ?? null,
            created_at: (string) $data['created_at'],
            updated_at: (string) $data['updated_at'],
        );
    }

    public function getCreatedAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->created_at);
    }

    public function getUpdatedAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->updated_at);
    }

    public function getAmountInMinorUnits(): int
    {
        [$whole, $fraction] = array_pad(explode('.', mb_trim($this->amount), 2), 2, '0');

        return ((int) $whole * 100) + (int) mb_str_pad(mb_substr($fraction, 0, 2), 2, '0');
    }

    public function isReceived(): bool
    {
        return $this->state === 'received';
    }

    public function isEnquiring(): bool
    {
        return $this->state === 'enquiring';
    }

    public function isExecuting(): bool
    {
        return $this->state === 'executing';
    }

    public function isReviewing(): bool
    {
        return $this->state === 'reviewing';
    }

    public function isAccepted(): bool
    {
        return $this->state === 'accepted';
    }

    public function isCompleted(): bool
    {
        return $this->state === 'completed';
    }

    public function isRejected(): bool
    {
        return $this->state === 'rejected';
    }

    public function isDeleted(): bool
    {
        return $this->state === 'deleted';
    }

    public function isPending(): bool
    {
        return in_array($this->state, ['received', 'enquiring', 'executing', 'reviewing', 'accepted']);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'bank_account_id' => $this->bank_account_id,
            'amount' => $this->amount,
            'state' => $this->state,
            'email' => $this->email,
            'description' => $this->description,
            'reference' => $this->reference,
            'send_recipient_receipt' => $this->send_recipient_receipt,
            'receipt_url' => $this->receipt_url,
            'slug' => $this->slug,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Chip\DataObjects;

use Carbon\Carbon;

final class SendInstruction
{
    public function __construct(
        public readonly int $id,
        public readonly int $bank_account_id,
        public readonly string $amount,
        public readonly string $state,
        public readonly string $email,
        public readonly string $description,
        public readonly string $reference,
        public readonly ?string $receipt_url,
        public readonly ?string $slug,
        public readonly string $created_at,
        public readonly string $updated_at,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            bank_account_id: (int) $data['bank_account_id'],
            amount: (string) $data['amount'],
            state: $data['state'] ?? 'received',
            email: $data['email'],
            description: $data['description'],
            reference: $data['reference'],
            receipt_url: $data['receipt_url'] ?? null,
            slug: $data['slug'] ?? null,
            created_at: $data['created_at'],
            updated_at: $data['updated_at'],
        );
    }

    public function getCreatedAt(): Carbon
    {
        return Carbon::parse($this->created_at);
    }

    public function getUpdatedAt(): Carbon
    {
        return Carbon::parse($this->updated_at);
    }

    public function getAmountInMajorUnits(): float
    {
        return (float) $this->amount;
    }

    public function getAmountInMinorUnits(): int
    {
        return (int) (((float) $this->amount) * 100);
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
            'receipt_url' => $this->receipt_url,
            'slug' => $this->slug,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

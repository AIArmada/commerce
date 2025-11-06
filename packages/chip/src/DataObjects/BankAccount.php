<?php

declare(strict_types=1);

namespace AIArmada\Chip\DataObjects;

use Carbon\Carbon;

final class BankAccount
{
    public function __construct(
        public readonly int $id,
        public readonly string $status,
        public readonly string $account_number,
        public readonly string $bank_code,
        public readonly ?int $group_id,
        public readonly string $name,
        public readonly ?string $reference,
        public readonly string $created_at,
        public readonly bool $is_debiting_account,
        public readonly bool $is_crediting_account,
        public readonly string $updated_at,
        public readonly ?string $deleted_at,
        public readonly ?string $rejection_reason,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            status: $data['status'] ?? 'pending',
            account_number: $data['account_number'] ?? '',
            bank_code: $data['bank_code'] ?? '',
            group_id: isset($data['group_id']) ? (int) $data['group_id'] : null,
            name: $data['name'] ?? '',
            reference: $data['reference'] ?? null,
            created_at: $data['created_at'],
            is_debiting_account: (bool) ($data['is_debiting_account'] ?? false),
            is_crediting_account: (bool) ($data['is_crediting_account'] ?? false),
            updated_at: $data['updated_at'],
            deleted_at: $data['deleted_at'] ?? null,
            rejection_reason: $data['rejection_reason'] ?? null,
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

    public function getDeletedAt(): ?Carbon
    {
        return $this->deleted_at ? Carbon::parse($this->deleted_at) : null;
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    public function canReceivePayments(): bool
    {
        return $this->is_crediting_account && $this->isVerified() && ! $this->isDeleted();
    }

    public function canSendPayments(): bool
    {
        return $this->is_debiting_account && $this->isVerified() && ! $this->isDeleted();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'account_number' => $this->account_number,
            'bank_code' => $this->bank_code,
            'group_id' => $this->group_id,
            'name' => $this->name,
            'reference' => $this->reference,
            'created_at' => $this->created_at,
            'is_debiting_account' => $this->is_debiting_account,
            'is_crediting_account' => $this->is_crediting_account,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'rejection_reason' => $this->rejection_reason,
        ];
    }
}

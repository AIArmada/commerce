<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

use Carbon\CarbonImmutable;

final readonly class BankAccountData
{
    public function __construct(
        public int $id,
        public string $status,
        public string $account_number,
        public string $bank_code,
        public ?int $group_id,
        public string $name,
        public ?string $reference,
        public string $created_at,
        public bool $is_debiting_account,
        public bool $is_crediting_account,
        public string $updated_at,
        public ?string $deleted_at,
        public ?string $rejection_reason,
    ) {}

    /** @param array<string, mixed> $data */
    public static function from(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            status: (string) $data['status'],
            account_number: (string) $data['account_number'],
            bank_code: (string) $data['bank_code'],
            group_id: isset($data['group_id']) ? (int) $data['group_id'] : null,
            name: (string) $data['name'],
            reference: $data['reference'] === null ? null : (string) $data['reference'],
            created_at: (string) $data['created_at'],
            is_debiting_account: (bool) $data['is_debiting_account'],
            is_crediting_account: (bool) $data['is_crediting_account'],
            updated_at: (string) $data['updated_at'],
            deleted_at: $data['deleted_at'] ?? null,
            rejection_reason: $data['rejection_reason'] ?? null,
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

    public function getDeletedAt(): ?CarbonImmutable
    {
        return $this->deleted_at ? CarbonImmutable::parse($this->deleted_at) : null;
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

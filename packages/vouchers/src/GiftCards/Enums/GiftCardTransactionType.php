<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\GiftCards\Enums;

/**
 * Types of transactions that can occur on a gift card.
 */
enum GiftCardTransactionType: string
{
    case Issue = 'issue';
    case Activate = 'activate';
    case Redeem = 'redeem';
    case TopUp = 'topup';
    case Refund = 'refund';
    case Transfer = 'transfer';
    case Expire = 'expire';
    case Fee = 'fee';
    case Adjustment = 'adjustment';
    case Merge = 'merge';

    /**
     * Get options for UI dropdowns.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Issue => 'Issued',
            self::Activate => 'Activated',
            self::Redeem => 'Redeemed',
            self::TopUp => 'Top Up',
            self::Refund => 'Refund',
            self::Transfer => 'Transfer',
            self::Expire => 'Expired',
            self::Fee => 'Fee',
            self::Adjustment => 'Adjustment',
            self::Merge => 'Merged',
        };
    }

    /**
     * Get description of the transaction type.
     */
    public function description(): string
    {
        return match ($this) {
            self::Issue => 'Gift card was issued with initial balance',
            self::Activate => 'Gift card was activated for use',
            self::Redeem => 'Balance was used for a purchase',
            self::TopUp => 'Additional balance was added',
            self::Refund => 'Balance was restored from a refund',
            self::Transfer => 'Balance was transferred to/from another card',
            self::Expire => 'Balance was forfeited due to expiration',
            self::Fee => 'Fee was deducted (e.g., inactivity fee)',
            self::Adjustment => 'Manual balance adjustment by admin',
            self::Merge => 'Balance was merged from another card',
        };
    }

    /**
     * Check if this transaction type credits (increases) the balance.
     */
    public function isCredit(): bool
    {
        return in_array($this, [
            self::Issue,
            self::TopUp,
            self::Refund,
            self::Merge,
        ], true);
    }

    /**
     * Check if this transaction type debits (decreases) the balance.
     */
    public function isDebit(): bool
    {
        return in_array($this, [
            self::Redeem,
            self::Transfer,
            self::Expire,
            self::Fee,
        ], true);
    }

    /**
     * Check if this transaction affects the balance (not just status).
     */
    public function affectsBalance(): bool
    {
        return $this !== self::Activate;
    }

    /**
     * Get the expected sign of amount for this transaction type.
     * Returns 1 for credits, -1 for debits, 0 for no balance change.
     */
    public function expectedSign(): int
    {
        if (! $this->affectsBalance()) {
            return 0;
        }

        return $this->isCredit() ? 1 : -1;
    }

    /**
     * Check if this transaction requires a reference (e.g., order, refund).
     */
    public function requiresReference(): bool
    {
        return in_array($this, [
            self::Redeem,
            self::Refund,
        ], true);
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Issue => 'primary',
            self::Activate => 'info',
            self::Redeem => 'warning',
            self::TopUp => 'success',
            self::Refund => 'success',
            self::Transfer => 'info',
            self::Expire => 'danger',
            self::Fee => 'danger',
            self::Adjustment => 'gray',
            self::Merge => 'primary',
        };
    }
}

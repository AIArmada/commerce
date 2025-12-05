<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Enums;

enum PayoutMethodType: string
{
    case BankTransfer = 'bank_transfer';
    case PayPal = 'paypal';
    case StripeConnect = 'stripe_connect';
    case Wise = 'wise';
    case Payoneer = 'payoneer';
    case Check = 'check';
    case Wire = 'wire';
    case Crypto = 'crypto';

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'Bank Transfer',
            self::PayPal => 'PayPal',
            self::StripeConnect => 'Stripe Connect',
            self::Wise => 'Wise',
            self::Payoneer => 'Payoneer',
            self::Check => 'Check',
            self::Wire => 'Wire Transfer',
            self::Crypto => 'Cryptocurrency',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::BankTransfer => 'heroicon-o-building-library',
            self::PayPal => 'heroicon-o-credit-card',
            self::StripeConnect => 'heroicon-o-credit-card',
            self::Wise => 'heroicon-o-globe-alt',
            self::Payoneer => 'heroicon-o-globe-alt',
            self::Check => 'heroicon-o-document-text',
            self::Wire => 'heroicon-o-arrows-right-left',
            self::Crypto => 'heroicon-o-currency-dollar',
        };
    }
}

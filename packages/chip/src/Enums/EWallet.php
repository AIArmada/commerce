<?php

declare(strict_types=1);

namespace AIArmada\Chip\Enums;

/**
 * E-Wallet Options for Direct Post
 *
 * These wallet codes are used when creating a direct post payment URL
 * to automatically redirect customers to a specific e-wallet.
 *
 * Usage: ?preferred={preferred}&razer_bank_code={code}
 *
 * Source: https://docs.chip-in.asia/chip-collect/overview/direct-post/e-wallet
 */
enum EWallet: string
{
    case GRABPAY = 'GrabPay';
    case TOUCH_N_GO = 'TNG-EWALLET';
    case SHOPEEPAY = 'ShopeePay';
    case MAYBANK_QR = 'MB2U_QRPay-Push';

    /**
     * Get all wallets as array
     *
     * @return array<string, array{label: string, preferred: string, code: string}>
     */
    public static function toArray(): array
    {
        $wallets = [];
        foreach (self::cases() as $wallet) {
            $wallets[$wallet->name] = [
                'label' => $wallet->label(),
                'preferred' => $wallet->preferred(),
                'code' => $wallet->value,
            ];
        }

        return $wallets;
    }

    /**
     * Get wallet by code (case-insensitive)
     */
    public static function fromCode(string $code): ?self
    {
        foreach (self::cases() as $wallet) {
            if (strcasecmp($wallet->value, $code) === 0) {
                return $wallet;
            }
        }

        return null;
    }

    /**
     * Get wallet by preferred value
     */
    public static function fromPreferred(string $preferred): ?self
    {
        foreach (self::cases() as $wallet) {
            if ($wallet->preferred() === $preferred) {
                return $wallet;
            }
        }

        return null;
    }

    /**
     * Get the preferred parameter value for the URL
     */
    public function preferred(): string
    {
        return match ($this) {
            self::GRABPAY => 'razer_grabpay',
            self::TOUCH_N_GO => 'razer_tng',
            self::SHOPEEPAY => 'razer_shopeepay',
            self::MAYBANK_QR => 'razer_maybankqr',
        };
    }

    /**
     * Get human-readable wallet name
     */
    public function label(): string
    {
        return match ($this) {
            self::GRABPAY => 'GrabPay',
            self::TOUCH_N_GO => "Touch 'n Go eWallet",
            self::SHOPEEPAY => 'ShopeePay',
            self::MAYBANK_QR => 'Maybank QR',
        };
    }

    /**
     * Build direct post URL parameters
     *
     * @return array{preferred: string, razer_bank_code: string}
     */
    public function urlParams(): array
    {
        return [
            'preferred' => $this->preferred(),
            'razer_bank_code' => $this->value,
        ];
    }
}

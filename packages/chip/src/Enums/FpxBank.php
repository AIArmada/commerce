<?php

declare(strict_types=1);

namespace AIArmada\Chip\Enums;

/**
 * FPX Bank Codes for Direct Post
 *
 * These bank codes are used when creating a direct post payment URL
 * to automatically redirect customers to a specific bank.
 *
 * Usage: ?preferred=fpx&fpx_bank_code={code}
 * For B2B: ?preferred=fpx_b2b1&fpx_bank_code={code}
 *
 * Source: https://docs.chip-in.asia/chip-collect/overview/direct-post/fpx
 *
 * @see FpxType for FPX type options (B2C vs B2B1)
 */
enum FpxBank: string
{
    case AFFIN_BANK = 'ABB0233';
    case ALLIANCE_BANK = 'ABMB0212';
    case AGRONET = 'AGRO01';
    case AMBANK = 'AMBB0209';
    case BANK_ISLAM = 'BIMB0340';
    case BANK_MUAMALAT = 'BMMB0341';
    case BANK_RAKYAT = 'BKRM0602';
    case BANK_OF_CHINA = 'BOCM01';
    case BSN = 'BSN0601';
    case CIMB_BANK = 'BCBB0235';
    case HONG_LEONG_BANK = 'HLB0224';
    case HSBC_BANK = 'HSBC0223';
    case KFH = 'KFH0346';
    case MAYBANK2E = 'MBB0228';
    case MAYBANK2U = 'MB2U0227';
    case OCBC_BANK = 'OCBC0229';
    case PUBLIC_BANK = 'PBB0233';
    case RHB_BANK = 'RHB0218';
    case STANDARD_CHARTERED = 'SCB0216';
    case UOB_BANK = 'UOB0226';

    /**
     * Get all bank codes as array
     *
     * @return array<string, string>
     */
    public static function toArray(): array
    {
        $banks = [];
        foreach (self::cases() as $bank) {
            $banks[$bank->value] = $bank->label();
        }

        return $banks;
    }

    /**
     * Get bank by code (case-insensitive)
     */
    public static function fromCode(string $code): ?self
    {
        $code = mb_strtoupper($code);
        foreach (self::cases() as $bank) {
            if (mb_strtoupper($bank->value) === $code) {
                return $bank;
            }
        }

        return null;
    }

    /**
     * Get human-readable bank name
     */
    public function label(): string
    {
        return match ($this) {
            self::AFFIN_BANK => 'Affin Bank',
            self::ALLIANCE_BANK => 'Alliance Bank (Personal)',
            self::AGRONET => 'AGRONet',
            self::AMBANK => 'AmBank',
            self::BANK_ISLAM => 'Bank Islam',
            self::BANK_MUAMALAT => 'Bank Muamalat',
            self::BANK_RAKYAT => 'Bank Rakyat',
            self::BANK_OF_CHINA => 'Bank Of China',
            self::BSN => 'BSN',
            self::CIMB_BANK => 'CIMB Bank',
            self::HONG_LEONG_BANK => 'Hong Leong Bank',
            self::HSBC_BANK => 'HSBC Bank',
            self::KFH => 'KFH',
            self::MAYBANK2E => 'Maybank2E',
            self::MAYBANK2U => 'Maybank2u',
            self::OCBC_BANK => 'OCBC Bank',
            self::PUBLIC_BANK => 'Public Bank',
            self::RHB_BANK => 'RHB Bank',
            self::STANDARD_CHARTERED => 'Standard Chartered',
            self::UOB_BANK => 'UOB Bank',
        };
    }
}

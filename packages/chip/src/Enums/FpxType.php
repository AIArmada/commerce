<?php

declare(strict_types=1);

namespace AIArmada\Chip\Enums;

/**
 * FPX Type Options
 *
 * Specifies the type of FPX payment to use.
 *
 * Source: https://docs.chip-in.asia/chip-collect/overview/direct-post/fpx
 */
enum FpxType: string
{
    /**
     * Standard FPX for individual/personal accounts
     */
    case B2C = 'fpx';

    /**
     * FPX for Business/Corporate Account
     */
    case B2B1 = 'fpx_b2b1';

    /**
     * Get human-readable FPX type name
     */
    public function label(): string
    {
        return match ($this) {
            self::B2C => 'FPX B2C (Standard FPX)',
            self::B2B1 => 'FPX B2B1 (Business/Corporate Account)',
        };
    }
}

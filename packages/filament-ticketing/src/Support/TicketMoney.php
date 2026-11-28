<?php

declare(strict_types=1);

namespace AIArmada\FilamentTicketing\Support;

/**
 * Display conversion for ticket prices, which core stores as integer
 * minor units. Parsing is strict: unparseable input returns null so it
 * can never silently become a zero price.
 */
final class TicketMoney
{
    public static function toDisplay(?int $minor): ?string
    {
        if ($minor === null) {
            return null;
        }

        $sign = $minor < 0 ? '-' : '';

        return $sign . abs(intdiv($minor, 100)) . '.' . mb_str_pad((string) abs($minor % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function toMinor(?string $display): ?int
    {
        if ($display === null || $display === '') {
            return null;
        }

        $value = mb_trim($display);

        if (preg_match('/^([+-]?)(\d+)(?:\.(\d+))?$/', $value, $matches) !== 1) {
            return null;
        }

        $sign = $matches[1] === '-' ? -1 : 1;
        $digits = mb_ltrim($matches[2], '0');
        $maxWhole = (string) intdiv(PHP_INT_MAX, 100);

        if (mb_strlen($digits) > mb_strlen($maxWhole) || (mb_strlen($digits) === mb_strlen($maxWhole) && strcmp($digits, $maxWhole) > 0)) {
            return null;
        }

        $whole = (int) $digits;
        $fraction = $matches[3] ?? '';
        $minor = (int) mb_str_pad(mb_substr($fraction, 0, 2), 2, '0');

        if (mb_strlen($fraction) > 2 && (int) mb_substr($fraction, 2, 1) >= 5) {
            $minor++;
        }

        if ($minor >= 100) {
            $whole++;
            $minor -= 100;
        }

        if ($whole > intdiv(PHP_INT_MAX - $minor, 100)) {
            return null;
        }

        return $sign * (($whole * 100) + $minor);
    }
}

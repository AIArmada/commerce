<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Support;

use Illuminate\Support\Str;

final class ConditionTargetLabels
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'cart@cart_subtotal/aggregate' => 'Cart Subtotal',
            'cart@grand_total/aggregate' => 'Cart Total',
            'items@item_discount/per-item' => 'Individual Items',
        ];
    }

    public static function label(?string $target): string
    {
        if (! is_string($target) || $target === '') {
            return '—';
        }

        $options = self::options();

        if (isset($options[$target])) {
            return $options[$target];
        }

        return Str::headline(str_replace(['@', '/', '_', '-'], ' ', $target));
    }
}

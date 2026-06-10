<?php

declare(strict_types=1);

namespace AIArmada\Products\Enums;

enum Visibility: string
{
    case Visible = 'visible';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Visible => __('products::enums.visibility.visible'),
            self::Hidden => __('products::enums.visibility.hidden'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Visible => 'success',
            self::Hidden => 'gray',
        };
    }
}

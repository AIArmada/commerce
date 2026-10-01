<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Contracts;

use Filament\Schemas\Components\Component;

interface TermFormExtension
{
    /**
     * @return array<int, Component>
     */
    public function components(): array;
}

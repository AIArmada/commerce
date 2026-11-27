<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Contracts;

interface AdminPanelContribution
{
    /** @return array<int, class-string> */
    public function pages(): array;

    /** @return array<int, class-string> */
    public function resources(): array;
}

<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Contracts;

interface AdminPanelContribution
{
    /** @return array<int, class-string> */
    public function resources(): array;
}

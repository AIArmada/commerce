<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Contracts;

interface AdminPanelContribution
{
    /** @return array<int, class-string> */
    public function resources(): array;

    /** @return array<int, class-string> */
    public function widgets(): array;
}

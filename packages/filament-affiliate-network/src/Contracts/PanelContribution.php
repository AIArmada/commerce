<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Contracts;

interface PanelContribution
{
    public function priority(): int;

    /** @return list<class-string> */
    public function resources(): array;

    /** @return list<class-string> */
    public function pages(): array;

    /** @return list<class-string> */
    public function widgets(): array;
}

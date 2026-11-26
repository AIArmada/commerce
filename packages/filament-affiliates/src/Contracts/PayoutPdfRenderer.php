<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Contracts;

interface PayoutPdfRenderer
{
    public function render(string $html): string;
}

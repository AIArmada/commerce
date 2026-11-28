<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Api;

interface ReadsFormMetrics
{
    /** @return array<string, int> */
    public function counts(): array;
}

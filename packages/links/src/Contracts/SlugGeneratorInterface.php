<?php

declare(strict_types=1);

namespace AIArmada\Links\Contracts;

interface SlugGeneratorInterface
{
    public function generate(int $length): string;
}

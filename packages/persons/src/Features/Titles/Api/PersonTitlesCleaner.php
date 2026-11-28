<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Titles\Api;

interface PersonTitlesCleaner
{
    public function cleanup(string $personId, string $personType): void;
}

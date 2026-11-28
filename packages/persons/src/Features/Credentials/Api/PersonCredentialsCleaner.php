<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Credentials\Api;

interface PersonCredentialsCleaner
{
    public function cleanup(string $personId, string $personType): void;
}

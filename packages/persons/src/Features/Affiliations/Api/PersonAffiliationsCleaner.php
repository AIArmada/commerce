<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Affiliations\Api;

interface PersonAffiliationsCleaner
{
    public function cleanup(string $personId, string $personType): void;
}

<?php

declare(strict_types=1);

namespace AIArmada\Persons\Runtime;

use AIArmada\Persons\Models\Person;

/**
 * Eloquent runtime integration for the formatted-name accessor, including
 * an already eager-loaded title graph. Not a cross-feature domain API.
 */
interface PersonTitleFormatting
{
    public function format(Person $person): string;
}

<?php

declare(strict_types=1);

namespace AIArmada\References\Persistence;

use AIArmada\References\Actions\SaveReference;
use AIArmada\References\Models\Reference;

/**
 * @mixin Reference
 */
trait SavesReferenceAtomically
{
    public function save(array $options = []): bool
    {
        return (new SaveReference)->handle($this, fn (): bool => parent::save($options));
    }
}

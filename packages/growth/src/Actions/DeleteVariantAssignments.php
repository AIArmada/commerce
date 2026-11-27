<?php

declare(strict_types=1);

namespace AIArmada\Growth\Actions;

use AIArmada\Growth\Models\Variant;

final readonly class DeleteVariantAssignments
{
    public function handle(Variant $variant): void
    {
        $variant->assignments()->delete();
    }
}

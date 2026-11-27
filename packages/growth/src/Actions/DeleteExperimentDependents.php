<?php

declare(strict_types=1);

namespace AIArmada\Growth\Actions;

use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;

final readonly class DeleteExperimentDependents
{
    public function handle(Experiment $experiment): void
    {
        $experiment->assignments()->delete();
        $experiment->variants()->eachById(static function (Variant $variant): void {
            $variant->delete();
        });
    }
}

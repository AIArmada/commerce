<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Numbering;

use AIArmada\Docs\Contracts\DocumentSequence;
use AIArmada\Docs\Features\Numbering\Actions\SequenceManager;
use AIArmada\Docs\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->singleton(SequenceManager::class);
        $app->bind(DocumentSequence::class, SequenceManager::class);
    }

    public function boot(Application $app): void {}
}

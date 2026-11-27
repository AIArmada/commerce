<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Features\ContactMethods;

use AIArmada\Contacting\Contracts\ContactMethodNormalizer;
use AIArmada\Contacting\Features\ContactMethods\Actions\NormalizeContactMethodAction;
use AIArmada\Contacting\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(ContactMethodNormalizer::class, NormalizeContactMethodAction::class);
    }

    public function boot(Application $app): void {}
}

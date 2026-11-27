<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses;

use AIArmada\Feedback\Features\Responses\Actions\DeleteResponseDependenciesAction;
use AIArmada\Feedback\Features\Responses\Api\DeletesResponseDependencies;
use AIArmada\Feedback\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(DeletesResponseDependencies::class, DeleteResponseDependenciesAction::class);
    }

    public function boot(Application $app): void {}
}

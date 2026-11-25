<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Venues;

use AIArmada\Events\Features\Venues\Models\EventLocation;
use AIArmada\Events\Features\Venues\Models\FacilityType;
use AIArmada\Events\Features\Venues\Models\Venue;
use AIArmada\Events\Features\Venues\Models\VenueSpace;
use AIArmada\Events\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $this->defaultModelConfig('venue', Venue::class);
        $this->defaultModelConfig('venue_space', VenueSpace::class);
        $this->defaultModelConfig('location', EventLocation::class);
        $this->defaultModelConfig('facility', FacilityType::class);
    }

    public function boot(Application $app): void {}

    /**
     * @param  class-string  $model
     */
    private function defaultModelConfig(string $key, string $model): void
    {
        if (config('events.models.' . $key) !== null) {
            return;
        }

        config(['events.models.' . $key => $model]);
    }
}

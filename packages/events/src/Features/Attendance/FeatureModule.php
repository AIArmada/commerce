<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Attendance;

use AIArmada\Events\Contracts\EventAdministrativeCheckIn;
use AIArmada\Events\Contracts\EventCheckInService;
use AIArmada\Events\Features\Attendance\Actions\DefaultEventAdministrativeCheckIn;
use AIArmada\Events\Features\Attendance\Actions\DefaultEventCheckInService;
use AIArmada\Events\Features\Attendance\Api\AttendanceApi;
use AIArmada\Events\Features\Attendance\Models\EventAttendance;
use AIArmada\Events\Features\Attendance\Models\EventHeadcountLog;
use AIArmada\Events\Features\Attendance\Models\EventWalkIn;
use AIArmada\Events\Features\Attendance\Queries\EloquentAttendanceApi;
use AIArmada\Events\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $this->defaultModelConfig('attendance', EventAttendance::class);
        $this->defaultModelConfig('walk_in', EventWalkIn::class);
        $this->defaultModelConfig('headcount_log', EventHeadcountLog::class);

        $app->bind(EventCheckInService::class, DefaultEventCheckInService::class);
        $app->bind(EventAdministrativeCheckIn::class, DefaultEventAdministrativeCheckIn::class);

        $app->bind(AttendanceApi::class, EloquentAttendanceApi::class);
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

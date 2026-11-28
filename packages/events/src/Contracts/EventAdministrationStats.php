<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Data\EventAdministrationStatsData;

interface EventAdministrationStats
{
    public function read(): EventAdministrationStatsData;
}

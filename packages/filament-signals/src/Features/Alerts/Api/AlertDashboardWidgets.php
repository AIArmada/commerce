<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Alerts\Api;

interface AlertDashboardWidgets
{
    /** @return list<class-string> */
    public function widgets(): array;
}

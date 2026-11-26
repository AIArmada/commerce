<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Features\PostalCodes\Api;

interface AreaCoverageMaintenance
{
    public function clearArea(string $areaId): void;
}

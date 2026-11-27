<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Contracts;

use AIArmada\Jnt\Data\PrintWaybillData;

interface WaybillWriter
{
    public function write(PrintWaybillData $waybill, string $path): bool;
}

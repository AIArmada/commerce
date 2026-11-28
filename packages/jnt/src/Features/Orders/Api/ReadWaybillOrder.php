<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Orders\Api;

interface ReadWaybillOrder
{
    public function find(string $id): WaybillOrder;
}

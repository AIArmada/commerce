<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Data\EventAddressData;

interface EventAddressable
{
    public function eventAddressData(): EventAddressData;
}

<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentProducts\Fixtures;

use AIArmada\CommerceSupport\Tests\Fixtures\TestOwner as SupportTestOwner;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class TestOwner extends SupportTestOwner
{
    use HasUuids;
}

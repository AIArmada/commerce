<?php

declare(strict_types=1);

namespace AIArmada\Cart\Contracts;

use AIArmada\Cart\Conditions\CartCondition;

interface CartConditionConvertible
{
    public function toCartCondition(): CartCondition;
}

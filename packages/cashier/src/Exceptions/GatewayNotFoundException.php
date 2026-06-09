<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Exceptions;

use AIArmada\Cashier\Exceptions\Gateway\GatewayNotFoundException as BaseGatewayNotFoundException;

final class GatewayNotFoundException extends BaseGatewayNotFoundException {}

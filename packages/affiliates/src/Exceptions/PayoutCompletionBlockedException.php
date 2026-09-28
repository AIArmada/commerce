<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Exceptions;

use InvalidArgumentException;

/**
 * The payout completion gate refused: non-payable affiliate without a
 * valid creation-time override, or a linked conversion that drifted
 * out of Approved. Extends InvalidArgumentException so existing
 * guards keep matching while processors narrow the catch.
 */
final class PayoutCompletionBlockedException extends InvalidArgumentException {}

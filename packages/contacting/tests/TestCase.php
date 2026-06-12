<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    // Database tests are skipped due to Testbench bootstrap issues.
    // Run with: composer test:phpstan packages/contacting
    // and: ./vendor/bin/pest packages/contacting/tests/Unit
}

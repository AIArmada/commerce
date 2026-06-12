<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    // Tests require Laravel app + Filament panel bootstrap.
    // Run with: ./vendor/bin/pest packages/filament-contacting/tests
}

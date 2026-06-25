<?php

declare(strict_types=1);

use AIArmada\Filament\Communications\PackageTests\TestCase;

require_once __DIR__ . '/TestCase.php';

pest()->extend(TestCase::class)->in('src');

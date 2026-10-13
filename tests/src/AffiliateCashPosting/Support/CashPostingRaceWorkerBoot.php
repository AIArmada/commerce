<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\AffiliateCashPosting\Support;

use AIArmada\Commerce\Tests\AffiliateCashPosting\CashPostingTestCase;

/**
 * Concrete harness boot for race workers in independent OS processes.
 *
 * Exists only so the standalone worker script can instantiate the
 * abstract cash-post case outside PHPUnit. Never referenced by Pest
 * test files directly.
 */
final class CashPostingRaceWorkerBoot extends CashPostingTestCase
{
    public function bootForWorker(string $database): void
    {
        $this->bootWorker($database);
    }
}

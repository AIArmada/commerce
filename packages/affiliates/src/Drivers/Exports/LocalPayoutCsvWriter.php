<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Drivers\Exports;

use AIArmada\Affiliates\Contracts\PayoutCsvWriter;
use League\Csv\Writer;
use RuntimeException;
use SplFileObject;

final readonly class LocalPayoutCsvWriter implements PayoutCsvWriter
{
    public function write(string $path, array $rows): void
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create payout export directory.');
        }

        $temporary = @tempnam($directory, '.payout-');

        if ($temporary === false) {
            throw new RuntimeException('Unable to create payout export file.');
        }

        try {
            if (realpath(dirname($temporary)) !== realpath($directory)) {
                throw new RuntimeException('Unable to create payout export file in destination directory.');
            }

            $file = new SplFileObject($temporary, 'w');
            $csv = Writer::createFromFileObject($file);
            $csv->insertOne(['affiliate_code', 'reference', 'commission_minor', 'currency', 'status']);
            $csv->insertAll($rows);
            unset($csv, $file);

            if (! @rename($temporary, $path)) {
                throw new RuntimeException('Unable to publish payout export file.');
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }
}

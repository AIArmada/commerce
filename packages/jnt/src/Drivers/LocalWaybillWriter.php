<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Drivers;

use AIArmada\Jnt\Contracts\WaybillWriter;
use AIArmada\Jnt\Data\PrintWaybillData;

final readonly class LocalWaybillWriter implements WaybillWriter
{
    public function write(PrintWaybillData $waybill, string $path): bool
    {
        $content = $waybill->getPdfContent();

        if ($content === null || ! str_starts_with($content, '%PDF-')) {
            return false;
        }

        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            return false;
        }

        return file_put_contents($path, $content, LOCK_EX) === strlen($content);
    }
}

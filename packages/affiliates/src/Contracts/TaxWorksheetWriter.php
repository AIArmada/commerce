<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Contracts;

use AIArmada\Affiliates\Data\TaxWorksheetRequest;

interface TaxWorksheetWriter
{
    public function write(TaxWorksheetRequest $request): string;

    public function delete(string $path): void;
}

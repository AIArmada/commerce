<?php

declare(strict_types=1);

namespace AIArmada\Docs\Contracts;

use AIArmada\Docs\Data\PdfRenderOptions;

interface PdfRenderer
{
    public function render(string $html, PdfRenderOptions $options): string;
}

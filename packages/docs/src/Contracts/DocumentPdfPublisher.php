<?php

declare(strict_types=1);

namespace AIArmada\Docs\Contracts;

interface DocumentPdfPublisher
{
    public function execute(string $id): string;
}

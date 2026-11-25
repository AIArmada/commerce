<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Api;

interface ProgramReferenceReader
{
    public function exists(string $id): bool;
}

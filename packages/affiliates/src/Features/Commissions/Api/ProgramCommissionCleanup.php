<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Commissions\Api;

interface ProgramCommissionCleanup
{
    /** Delete owned commission configuration before the program is deleted. */
    public function deleteForProgram(string $programId): void;
}

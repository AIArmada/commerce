<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Api;

final readonly class ProgramJoinRecord
{
    public function __construct(
        public ProgramJoinReference $reference,
        public string $affiliateCode,
        public ?string $approvedAt,
        public string $programName,
        public string $programMorphType,
        public bool $approved,
    ) {}
}

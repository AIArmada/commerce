<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

final readonly class EventAssignmentData
{
    public function __construct(
        public string $manageableType,
        public string $manageableId,
        public string $managerType,
        public string $managerId,
        public string $role,
    ) {}
}

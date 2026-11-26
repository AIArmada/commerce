<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Contracts;

use AIArmada\Addressing\Models\Address;

interface AddressAssignmentWriter
{
    /**
     * @param array<string, string|null> $assignments
     * @param array<string, mixed> $metadata
     */
    public function execute(Address $address, array $assignments, ?string $stateId = null, array $metadata = []): void;

    /**
     * @param array<string, string|null> $assignments
     * @return array<string, string>
     */
    public function validate(Address $address, array $assignments, ?string $stateId = null): array;

    public function pruneIncompatibleAssignments(Address $address): void;

    /**
     * @param array<string, string> $assignments
     * @return array<string, string>
     */
    public function narrowToValidAssignments(Address $address, array $assignments): array;
}

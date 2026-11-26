<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Features\AddressLifecycle\Actions;

use AIArmada\Addressing\Contracts\AddressAssignmentWriter;
use AIArmada\Addressing\Contracts\AddressWriter;
use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Support\AddressOwnerGuard;
use AIArmada\Addressing\Support\ModelResolver;
use LogicException;

final readonly class SaveAddressAction implements AddressWriter
{
    public function __construct(private AddressAssignmentWriter $assignments) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string|null>  $areaAssignments
     * @param  class-string<Address>|null  $modelClass
     */
    public function execute(array $data, array $areaAssignments, ?string $recordId = null, ?string $modelClass = null): string
    {
        $modelClass ??= ModelResolver::addressClass();
        if (! is_a($modelClass, Address::class, true)) {
            throw new LogicException('Configured address model must extend Address.');
        }

        $address = new $modelClass;

        return $address->getConnection()->transaction(function () use ($address, $recordId, $data, $areaAssignments): string {
            if ($recordId !== null) {
                AddressOwnerGuard::assertAddressIsWritable($recordId);
                $address = $address->newQuery()->whereKey($recordId)->lockForUpdate()->firstOrFail();
            }

            $address->fill($data);

            if (! $address->save()) {
                throw new LogicException('The address could not be saved.');
            }

            $this->assignments->execute((string) $address->getKey(), $areaAssignments, $address->state_id, ['source' => 'filament-addressing']);

            return (string) $address->getKey();
        });
    }
}

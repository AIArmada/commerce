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
    public function execute(array $data, array $areaAssignments, ?Address $address = null, ?string $modelClass = null): Address
    {
        $modelClass ??= ModelResolver::addressClass();
        $address ??= new $modelClass;

        if (! $address instanceof Address) {
            throw new LogicException('Configured address model must extend Address.');
        }

        return $address->getConnection()->transaction(function () use ($address, $data, $areaAssignments): Address {
            if ($address->exists) {
                AddressOwnerGuard::assertAddressIsWritable($address->getKey());
                $address = $address->newQuery()->whereKey($address->getKey())->lockForUpdate()->firstOrFail();
            }

            $address->fill($data);

            if (! $address->save()) {
                throw new LogicException('The address could not be saved.');
            }

            $this->assignments->execute($address, $areaAssignments, $address->state_id, ['source' => 'filament-addressing']);

            return $address->fresh() ?? $address;
        });
    }
}

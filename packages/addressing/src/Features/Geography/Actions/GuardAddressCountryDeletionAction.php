<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Features\Geography\Actions;

use AIArmada\Addressing\Contracts\AddressCountryDeletionGuard;
use AIArmada\Addressing\Support\ModelResolver;
use Illuminate\Validation\ValidationException;

final readonly class GuardAddressCountryDeletionAction implements AddressCountryDeletionGuard
{
    public function handle(string $key): void
    {
        if (ModelResolver::areaClass()::query()->where('country_id', $key)->exists()
            || ModelResolver::addressClass()::query()->withoutOwnerScope()->where('country_id', $key)->exists()) {
            throw ValidationException::withMessages([
                'country' => 'Delete or reassign this country\'s areas and addresses before deleting it.',
            ]);
        }
    }
}

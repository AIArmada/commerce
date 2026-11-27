<?php

declare(strict_types=1);

use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Features\Identity\Actions\SaveCustomerDetails;

it('persists admin status and consent without permitting ownership or identity changes', function (): void {
    $action = app(SaveCustomerDetails::class);

    $customer = $action->create([
        'first_name' => 'Admin',
        'last_name' => 'Created',
        'status' => CustomerStatus::Suspended->value,
        'accepts_marketing' => true,
        'user_id' => 'forged-user',
    ]);

    expect($customer->status)->toBe(CustomerStatus::Suspended)
        ->and($customer->accepts_marketing)->toBeTrue()
        ->and($customer->marketing_consented_at)->not->toBeNull()
        ->and($customer->user_id)->toBeNull();

    $customer = $action->update($customer, [
        'status' => CustomerStatus::Active->value,
        'accepts_marketing' => false,
    ]);

    expect($customer->status)->toBe(CustomerStatus::Active)
        ->and($customer->accepts_marketing)->toBeFalse()
        ->and($customer->marketing_revoked_at)->not->toBeNull();
});

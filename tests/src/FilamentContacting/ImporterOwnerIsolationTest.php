<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Contacting\Models\ContactMethod;
use AIArmada\Customers\Models\Customer;
use AIArmada\FilamentContacting\Imports\ContactMethodImporter;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Auth\Access\AuthorizationException;

it('rejects a contact-method CSV row that targets another owner', function (): void {
    config()->set('customers.features.owner.enabled', true);

    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $customerB = OwnerContext::withOwner($ownerB, fn (): Customer => Customer::query()->create([
        'first_name' => 'Owner',
        'last_name' => 'B',
        'email' => 'import-owner-b-' . uniqid() . '@example.com',
        'status' => 'active',
    ]));

    $importer = new ContactMethodImporter(new Import, [
        'contactable_type' => 'contactable_type',
        'contactable_id' => 'contactable_id',
        'type' => 'type',
        'value' => 'value',
    ], []);

    expect(fn () => OwnerContext::withOwner($ownerA, fn () => $importer([
        'contactable_type' => $customerB->getMorphClass(),
        'contactable_id' => $customerB->getKey(),
        'type' => 'email',
        'value' => 'forged@example.com',
    ])))->toThrow(AuthorizationException::class);

    expect(OwnerContext::withOwner($ownerB, fn (): int => ContactMethod::query()
        ->where('contactable_id', $customerB->getKey())
        ->count()))->toBe(0);
});

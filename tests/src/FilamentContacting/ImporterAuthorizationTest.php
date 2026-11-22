<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Contacting\Models\ContactMethod;
use AIArmada\Contacting\Models\SocialProfile;
use AIArmada\Customers\Models\Customer;
use AIArmada\FilamentContacting\Imports\ContactMethodImporter;
use AIArmada\FilamentContacting\Imports\SocialProfileImporter;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Gate;

final class DenyContactMethodImportPolicy
{
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ContactMethod $method): bool
    {
        return false;
    }
}

final class DenySocialProfileImportPolicy
{
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SocialProfile $profile): bool
    {
        return false;
    }
}

it('rejects contacting imports when resource is read-only or policy denies', function (): void {
    $owner = User::factory()->create();
    $this->actingAs($owner);

    $customer = OwnerContext::withOwner($owner, fn (): Customer => Customer::query()->create([
        'first_name' => 'Alice',
        'last_name' => 'Example',
        'email' => 'contact-auth-' . uniqid() . '@example.com',
        'status' => 'active',
    ]));

    $import = new Import;
    $import->user()->associate($owner);

    config()->set('filament-contacting.resources.contact_methods.read_only', true);

    $contactImporter = new ContactMethodImporter($import, [
        'contactable_type' => 'contactable_type',
        'contactable_id' => 'contactable_id',
        'type' => 'type',
        'value' => 'value',
    ], []);

    expect(fn () => OwnerContext::withOwner($owner, fn () => $contactImporter([
        'contactable_type' => $customer->getMorphClass(),
        'contactable_id' => $customer->getKey(),
        'type' => 'email',
        'value' => 'readonly@example.com',
    ])))->toThrow(RowImportFailedException::class, 'read only');

    config()->set('filament-contacting.resources.contact_methods.read_only', false);
    Gate::policy(ContactMethod::class, DenyContactMethodImportPolicy::class);

    expect(fn () => OwnerContext::withOwner($owner, fn () => $contactImporter([
        'contactable_type' => $customer->getMorphClass(),
        'contactable_id' => $customer->getKey(),
        'type' => 'email',
        'value' => 'denied@example.com',
    ])))->toThrow(RowImportFailedException::class, 'not authorized');

    Gate::policy(SocialProfile::class, DenySocialProfileImportPolicy::class);

    $socialImporter = new SocialProfileImporter($import, [
        'socialable_type' => 'socialable_type',
        'socialable_id' => 'socialable_id',
        'platform' => 'platform',
        'handle' => 'handle',
    ], []);

    expect(fn () => OwnerContext::withOwner($owner, fn () => $socialImporter([
        'socialable_type' => $customer->getMorphClass(),
        'socialable_id' => $customer->getKey(),
        'platform' => 'facebook',
        'handle' => 'denied-handle',
    ])))->toThrow(RowImportFailedException::class, 'not authorized');
});

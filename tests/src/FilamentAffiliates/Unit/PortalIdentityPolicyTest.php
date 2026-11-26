<?php

declare(strict_types=1);

use AIArmada\Affiliates\Features\Identity\Api\PortalProfileAdministration;
use AIArmada\Affiliates\Features\Identity\Api\PortalProfileInput;
use AIArmada\Affiliates\Features\Identity\Api\RegistrationApi;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\Affiliates\Features\Identity\Models\AffiliateClaim;
use AIArmada\Affiliates\States\Active;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\FilamentAffiliates\Features\Portal\EntryPoints\Filament\Concerns\InteractsWithAffiliate;
use AIArmada\FilamentAffiliates\Features\Portal\EntryPoints\Filament\Pages\PortalRegistration;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PortalIdentityPolicyPage
{
    use InteractsWithAffiliate;
}

function portalIdentityAffiliate(array $attributes = []): Affiliate
{
    return Affiliate::create(array_merge([
        'code' => 'PORTALID-' . Str::uuid(),
        'name' => 'Portal Identity Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ], $attributes));
}

function portalIdentityInput(string $website = 'https://affiliate.example.com'): PortalProfileInput
{
    return new PortalProfileInput(
        'Updated Name',
        'updated-handle',
        'updated@example.com',
        $website,
        'manual',
        'Manual transfer',
        'account-ref',
    );
}

beforeEach(function (): void {
    config(['affiliates.owner.enabled' => false]);
    AffiliateClaim::query()->delete();
    Affiliate::query()->delete();
    User::query()->where('email', '!=', 'default-owner@example.com')->delete();
});

it('requires an identity link to enter the portal', function (): void {
    $user = User::create([
        'name' => 'Ambiguous Portal User',
        'email' => 'ambiguous-' . Str::uuid() . '@example.com',
        'password' => 'secret',
    ]);

    portalIdentityAffiliate();

    $this->actingAs($user);

    expect((new PortalIdentityPolicyPage)->getAffiliate())->toBeNull();
});

it('resolves the registered actor independently of the affiliate owner and contact email', function (): void {
    $user = User::factory()->create();
    $owner = User::factory()->create();

    $affiliate = app(RegistrationApi::class)->register(
        ['name' => 'Registered Portal Affiliate', 'contact_email' => 'contact@example.com'],
        new RecordReference($owner->getMorphClass(), (string) $owner->getKey()),
        null,
        null,
        new RecordReference($user->getMorphClass(), (string) $user->getKey()),
    );

    $this->actingAs($user);

    expect((new PortalIdentityPolicyPage)->getAffiliate()?->getKey())->toBe($affiliate->id);
});

it('accepts a verified ownership claim even when the affiliate belongs to another owner', function (): void {
    $user = User::create([
        'name' => 'Claimed Portal User',
        'email' => 'claimed-' . Str::uuid() . '@example.com',
        'password' => 'secret',
    ]);
    $other = User::create([
        'name' => 'Other Owner',
        'email' => 'other-owner-' . Str::uuid() . '@example.com',
        'password' => 'secret',
    ]);
    $affiliate = portalIdentityAffiliate();
    $affiliate->forceFill(['owner_type' => $other->getMorphClass(), 'owner_id' => (string) $other->getKey()])->save();

    AffiliateClaim::create([
        'affiliate_id' => $affiliate->getKey(),
        'owner_type' => $other->getMorphClass(),
        'owner_id' => (string) $other->getKey(),
        'claimant_type' => $user->getMorphClass(),
        'claimant_id' => (string) $user->getKey(),
        'status' => 'active',
        'claimed_at' => now(),
    ]);

    $this->actingAs($user);

    expect((new PortalIdentityPolicyPage)->getAffiliate()?->getKey())->toBe($affiliate->getKey());
});

it('refuses profile writes for an affiliate the actor does not own', function (): void {
    $user = User::create([
        'name' => 'Profile Actor',
        'email' => 'profile-actor-' . Str::uuid() . '@example.com',
        'password' => 'secret',
    ]);
    $affiliate = portalIdentityAffiliate();
    $affiliate->forceFill(['owner_type' => $user->getMorphClass(), 'owner_id' => (string) $user->getKey()])->save();
    $foreign = portalIdentityAffiliate();

    expect(fn () => app(PortalProfileAdministration::class)->save(
        (string) $foreign->getKey(),
        $user->getMorphClass(),
        (string) $user->getKey(),
        portalIdentityInput(),
    ))->toThrow(AuthorizationException::class);
});

it('rejects non-http website URLs on the portal profile', function (): void {
    $user = User::create([
        'name' => 'Profile URL Actor',
        'email' => 'profile-url-' . Str::uuid() . '@example.com',
        'password' => 'secret',
    ]);
    $affiliate = portalIdentityAffiliate();
    $affiliate->forceFill(['owner_type' => $user->getMorphClass(), 'owner_id' => (string) $user->getKey()])->save();

    expect(fn () => app(PortalProfileAdministration::class)->save(
        (string) $affiliate->getKey(),
        $user->getMorphClass(),
        (string) $user->getKey(),
        portalIdentityInput('ftp://affiliate.example.com'),
    ))->toThrow(ValidationException::class);
});

it('rolls back the user when affiliate registration fails', function (): void {
    app()->instance(RegistrationApi::class, new class implements RegistrationApi
    {
        public function register(array $data, RecordReference $owner, ?string $referralCode, ?string $cookie, ?RecordReference $actor = null): never
        {
            throw new RuntimeException('Affiliate registration failed.');
        }
    });

    $email = 'rollback-' . Str::uuid() . '@example.com';
    $page = new PortalRegistration;
    $method = (new ReflectionClass($page))->getMethod('handleRegistration');

    expect(fn () => $method->invoke($page, [
        'name' => 'Rollback User',
        'email' => $email,
        'password' => 'secret',
        'affiliate_name' => 'Rollback Affiliate',
        'phone' => '0123456789',
    ]))->toThrow(RuntimeException::class, 'Affiliate registration failed.');

    expect(User::query()->where('email', $email)->exists())->toBeFalse();
});

<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Contacting\Data\SocialProfileData;
use AIArmada\Contacting\Models\ContactMethod;
use AIArmada\Contacting\Models\SocialProfile;
use AIArmada\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class ContactingTestOwner extends Model
{
    use HasUuids;

    protected $table = 'contacting_test_owners';

    protected $fillable = [
        'name',
    ];
}

beforeEach(function (): void {
    Schema::dropIfExists('contacting_test_owners');

    Schema::create('contacting_test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });
});

it('isolates contact methods and social profiles across owners', function (): void {
    $ownerA = ContactingTestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = ContactingTestOwner::query()->create(['name' => 'Owner B']);

    $customerA = OwnerContext::withOwner($ownerA, function (): Customer {
        return Customer::query()->create([
            'first_name' => 'Owner',
            'last_name' => 'A',
            'email' => 'contacting-owner-a-' . uniqid() . '@example.com',
            'status' => 'active',
        ]);
    });

    $customerB = OwnerContext::withOwner($ownerB, function (): Customer {
        return Customer::query()->create([
            'first_name' => 'Owner',
            'last_name' => 'B',
            'email' => 'contacting-owner-b-' . uniqid() . '@example.com',
            'status' => 'active',
        ]);
    });

    $contactMethodA = OwnerContext::withOwner($ownerA, function () use ($customerA): ContactMethod {
        return $customerA->addContactMethod(ContactMethodData::email('owner-a-' . uniqid() . '@example.com'));
    });

    $contactMethodB = OwnerContext::withOwner($ownerB, function () use ($customerB): ContactMethod {
        return $customerB->addContactMethod(ContactMethodData::email('owner-b-' . uniqid() . '@example.com'));
    });

    $socialProfileA = OwnerContext::withOwner($ownerA, function () use ($customerA): SocialProfile {
        return $customerA->addSocialProfile(new SocialProfileData(
            platform: 'facebook',
            handle: 'owner-a-' . uniqid(),
        ));
    });

    $socialProfileB = OwnerContext::withOwner($ownerB, function () use ($customerB): SocialProfile {
        return $customerB->addSocialProfile(new SocialProfileData(
            platform: 'facebook',
            handle: 'owner-b-' . uniqid(),
        ));
    });

    expect(OwnerContext::withOwner($ownerA, function (): array {
        return ContactMethod::query()->pluck('id')->all();
    }))->toEqual([$contactMethodA->id]);

    expect(OwnerContext::withOwner($ownerA, function (): array {
        return SocialProfile::query()->pluck('id')->all();
    }))->toEqual([$socialProfileA->id]);

    expect(OwnerContext::withOwner($ownerB, function (): array {
        return ContactMethod::query()->pluck('id')->all();
    }))->toEqual([$contactMethodB->id]);

    expect(OwnerContext::withOwner($ownerB, function (): array {
        return SocialProfile::query()->pluck('id')->all();
    }))->toEqual([$socialProfileB->id]);
});

it('blocks cross-owner contacting writes', function (): void {
    $ownerA = ContactingTestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = ContactingTestOwner::query()->create(['name' => 'Owner B']);

    $customerA = OwnerContext::withOwner($ownerA, function (): Customer {
        return Customer::query()->create([
            'first_name' => 'Owner',
            'last_name' => 'A',
            'email' => 'contacting-owner-a-write-' . uniqid() . '@example.com',
            'status' => 'active',
        ]);
    });

    $customerB = OwnerContext::withOwner($ownerB, function (): Customer {
        return Customer::query()->create([
            'first_name' => 'Owner',
            'last_name' => 'B',
            'email' => 'contacting-owner-b-write-' . uniqid() . '@example.com',
            'status' => 'active',
        ]);
    });

    expect(fn (): ContactMethod => OwnerContext::withOwner($ownerA, function () use ($customerB): ContactMethod {
        return $customerB->addContactMethod(ContactMethodData::email('cross-owner-' . uniqid() . '@example.com'));
    }))->toThrow(InvalidArgumentException::class);

    expect(fn (): SocialProfile => OwnerContext::withOwner($ownerA, function () use ($customerB): SocialProfile {
        return $customerB->addSocialProfile(new SocialProfileData(
            platform: 'facebook',
            handle: 'cross-owner-' . uniqid(),
        ));
    }))->toThrow(InvalidArgumentException::class);
});

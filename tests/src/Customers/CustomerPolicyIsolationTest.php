<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Policies\CustomerPolicy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

require_once __DIR__ . '/Fixtures/CustomersTestOwner.php';

beforeEach(function (): void {
    Schema::dropIfExists('test_owners');

    Schema::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });
});

it('does not allow viewing a customer outside owner scope even if user_id matches', function (): void {
    $ownerA = CustomersTestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = CustomersTestOwner::query()->create(['name' => 'Owner B']);

    $user = User::query()->create([
        'name' => 'Viewer',
        'email' => 'viewer-' . uniqid() . '@example.com',
        'password' => 'password',
    ]);

    $customerOutsideOwner = Customer::query()->create([
        'user_id' => $user->getKey(),
        'first_name' => 'Out',
        'last_name' => 'Scope',
        'email' => 'outside-' . uniqid() . '@example.com',
        'status' => CustomerStatus::Active,
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    $policy = new CustomerPolicy;

    OwnerContext::withOwner($ownerA, function () use ($policy, $user, $customerOutsideOwner): void {
        expect($policy->view($user, $customerOutsideOwner))->toBeFalse();
        expect($policy->update($user, $customerOutsideOwner))->toBeFalse();
        expect($policy->delete($user, $customerOutsideOwner))->toBeFalse();
    });
});

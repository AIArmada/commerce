<?php

declare(strict_types=1);

if (! class_exists('Facades\\Livewire\\Features\\SupportFileUploads\\GenerateSignedUploadUrl')) {
    require_once __DIR__ . '/Support/Shims/Facades/Livewire/Features/SupportFileUploads/GenerateSignedUploadUrl.php';
}

use AIArmada\Cart\Conditions\ConditionTarget;
use AIArmada\Commerce\Tests\FilamentInventory\FilamentInventoryTestCase;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Commerce\Tests\Jnt\JntTestCase;
use AIArmada\Commerce\Tests\Products\ProductsTestCase;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAuthz\Models\Role;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)->in(
    'src/Cart',
    'src/CartAI',
    'src/CartBlockchain',
    'src/CartCollaboration',
    'src/CartFraud',
    'src/CartGraphQL',
    'src/Checkout',
    'src/Chip',
    'src/Docs',
    'src/FilamentCart',
    'src/FilamentCashier',
    'src/FilamentChip',
    'src/FilamentAuthz',
    'src/FilamentAffiliates',
    'src/FilamentPromotions',
    'src/Stock',
    'src/Affiliates',
    'src/AffiliateNetwork',
    'src/FilamentAffiliateNetwork',
    'src/Vouchers',
    'src/Customers',
    'src/Orders',
    'src/Pricing',
    'src/Promotions',
    'src/FilamentCustomers',
    'src/Tax',
    'src/Shipping',
    'src/Support',
);

pest()->extend(ProductsTestCase::class)->in('src/Products');

pest()->extend(JntTestCase::class)->in('src/Jnt');

pest()->extend(InventoryTestCase::class)->in('src/Inventory');

pest()->extend(FilamentInventoryTestCase::class)->in('src/FilamentInventory');

// CashierChip tests use their own CashierChipTestCase via uses() in each test file
// Cashier (unified) tests use their own CashierTestCase via uses() in each test file

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

// expect()->extend('toBeCartable', function () {
//     return $this->toBeInstanceOf(AIArmada\Cart\Contracts\CartableInterface::class);
// });

expect()->extend('toHaveValidCartStructure', function () {
    return $this->toHaveKeys(['items', 'conditions', 'metadata']);
});

/*
|--------------------------------------------------------------------------
| Test Helpers
|--------------------------------------------------------------------------
*/

/**
 * Create a test user with optional roles assigned.
 *
 * @param  array<string>  $roles  Role names to assign to the user
 */
function createUserWithRoles(array $roles = []): User
{
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test' . uniqid() . '@example.com',
        'password' => bcrypt('password'),
    ]);

    if ($roles === []) {
        return $user;
    }

    OwnerContext::withOwner($user, function () use ($roles, $user): void {
        foreach ($roles as $roleName) {
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web']
            );
            $user->assignRole($role);
        }
    });

    return $user;
}

function createSampleCartData(): array
{
    return [
        [
            'id' => 'test-product-1',
            'name' => 'Test Product 1',
            'price' => 99.99,
            'quantity' => 2,
            'attributes' => ['color' => 'red', 'size' => 'large'],
        ],
        [
            'id' => 'test-product-2',
            'name' => 'Test Product 2',
            'price' => 149.99,
            'quantity' => 1,
            'attributes' => ['brand' => 'TestBrand'],
        ],
    ];
}

function createSampleConditionData(): array
{
    return [
        'discount' => [
            'name' => 'Test Discount',
            'type' => 'discount',
            'target' => 'cart@grand_total/aggregate',
            'target_definition' => ConditionTarget::from('cart@grand_total/aggregate')->toArray(),
            'value' => '-10%',
        ],
        'tax' => [
            'name' => 'Test Tax',
            'type' => 'tax',
            'target' => 'cart@grand_total/aggregate',
            'target_definition' => ConditionTarget::from('cart@grand_total/aggregate')->toArray(),
            'value' => '+8.5%',
        ],
        'shipping' => [
            'name' => 'Test Shipping',
            'type' => 'shipping',
            'target' => 'cart@shipping/aggregate',
            'target_definition' => ConditionTarget::from('cart@shipping/aggregate')->toArray(),
            'value' => '+15.00',
        ],
    ];
}

function conditionTargetDefinition(string $dsl): array
{
    return ConditionTarget::from($dsl)->toArray();
}

beforeEach(function (): void {
    config()->set('customers.features.owner.enabled', true);
    config()->set('customers.features.owner.include_global', false);

    OwnerContext::clearOverride();
})->in('src/Customers');

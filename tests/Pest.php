<?php

declare(strict_types=1);

if (! class_exists('Facades\\Livewire\\Features\\SupportFileUploads\\GenerateSignedUploadUrl')) {
    require_once __DIR__ . '/Support/Shims/Facades/Livewire/Features/SupportFileUploads/GenerateSignedUploadUrl.php';
}

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Cart\Conditions\ConditionTarget;
use AIArmada\Commerce\Tests\Feedback\FeedbackTestCase;
use AIArmada\Commerce\Tests\FilamentAuthz\FilamentAuthzTestCase;
use AIArmada\Commerce\Tests\FilamentInventory\FilamentInventoryTestCase;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Commerce\Tests\Jnt\JntTestCase;
use AIArmada\Commerce\Tests\Products\ProductsTestCase;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Models\Role;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Support\EventTicketScope;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\TicketType;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)->in(
    'src/Ticketing',
    'src/FilamentTicketing',
    'src/FilamentSeating',
    'src/Seating',
    'src/Addressing',
    'src/Authz',
    'src/FilamentAddressing',
    'src/Cart',
    'src/Checkout',
    'src/Chip',
    'src/CommerceSupport',
    'src/Contacting',
    'src/Docs',
    'src/Events',
    'src/Engagement',
    'src/FilamentEvents',
    'src/FilamentEngagement',
    'src/Growth',
    'src/FilamentCart',
    'src/FilamentCashier',
    'src/FilamentChip',
    'src/FilamentContacting',
    'src/FilamentGrowth',
    'src/FilamentAuthz',
    'src/FilamentAffiliates',
    'src/FilamentPromotions',
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
    'src/FilamentCommerceSupport',
    'src/Communications',
    'src/FilamentCommunications',
    'src/Moderation',
    'src/References',
);

pest()->extend(ProductsTestCase::class)->in('src/Products');

pest()->extend(JntTestCase::class)->in('src/Jnt');

pest()->extend(InventoryTestCase::class)->in('src/Inventory');

pest()->extend(FilamentInventoryTestCase::class)->in('src/FilamentInventory');

pest()->extend(FilamentAuthzTestCase::class)->in('src/FilamentAuthzScoped');

pest()->extend(FeedbackTestCase::class)->in('src/Feedback');

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

function createTestAffiliate(array $attributes = []): Affiliate
{
    return Affiliate::create(array_merge([
        'code' => 'AFF' . uniqid(),
        'name' => 'Test Affiliate',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ], $attributes));
}

function createEventTicketType(Event | EventOccurrence | EventSession $target, array $attributes = []): TicketType
{
    return TicketType::factory()->create(array_merge([
        'ticketable_type' => $target->getMorphClass(),
        'ticketable_id' => $target->getKey(),
    ], $attributes));
}

function createEventPass(TicketType $ticketType, ?EventRegistration $registration = null, array $attributes = []): Pass
{
    $ticketType->loadMissing('ticketable');

    $scopeIds = EventTicketScope::ids($ticketType);

    return Pass::factory()->create(array_merge([
        'ticketable_type' => $ticketType->ticketable_type,
        'ticketable_id' => $ticketType->ticketable_id,
        'ticket_type_id' => $ticketType->getKey(),
        'registration_type' => $registration?->getMorphClass(),
        'registration_id' => $registration?->getKey(),
        'occurrence_id' => $registration?->event_occurrence_id ?? $scopeIds['event_occurrence_id'],
        'session_id' => $registration?->event_session_id ?? $scopeIds['event_session_id'],
        'status' => 'issued',
        'issued_at' => now(),
    ], $attributes));
}

beforeEach(function (): void {
    config()->set('customers.features.owner.enabled', true);
    config()->set('customers.features.owner.include_global', false);
})->in('src/Customers'); // @phpstan-ignore method.notFound

<?php

declare(strict_types=1);

use AIArmada\CashierChip\Billing\Billable;
use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Contracts\BillableContract;
use AIArmada\Chip\Models\ChipCustomerLink;
use AIArmada\Commerce\Tests\FilamentCashierChip\Fixtures\User;
use AIArmada\Commerce\Tests\FilamentCashierChip\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\FilamentCashierChip\Resources\CustomerResource;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(TestCase::class);

beforeEach(function (): void {
    if (! Schema::hasTable('customer_scope_orgs')) {
        Schema::create('customer_scope_orgs', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('owned_billable_customers')) {
        Schema::create('owned_billable_customers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->nullableUuidMorphs('owner');
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('activity_log')) {
        Schema::create('activity_log', function (Blueprint $table): void {
            $table->id();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer');
            $table->json('attribute_changes')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();
        });
    }
});

function bindCustomerScopeOwner(?Model $owner): void
{
    app()->bind(OwnerResolverInterface::class, fn () => new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });
}

it('hides other tenants customers from the list', function (): void {
    config()->set('cashier-chip.features.owner.enabled', true);
    config()->set('cashier-chip.features.owner.include_global', false);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'customer-scope-a@example.com',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'customer-scope-b@example.com',
    ]);

    bindCustomerScopeOwner($ownerA);

    expect(CustomerResource::getEloquentQuery()->whereKey($ownerA->getKey())->exists())->toBeTrue()
        ->and(CustomerResource::getEloquentQuery()->whereKey($ownerB->getKey())->exists())->toBeFalse();
});

it('shows all customers in explicit global context', function (): void {
    config()->set('cashier-chip.features.owner.enabled', true);
    config()->set('cashier-chip.features.owner.include_global', false);

    $ownerA = User::query()->create([
        'name' => 'Global A',
        'email' => 'customer-scope-global-a@example.com',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Global B',
        'email' => 'customer-scope-global-b@example.com',
    ]);

    bindCustomerScopeOwner(null);

    OwnerContext::withOwner(null, function () use ($ownerA, $ownerB): void {
        expect(CustomerResource::getEloquentQuery()->whereKey($ownerA->getKey())->exists())->toBeTrue()
            ->and(CustomerResource::getEloquentQuery()->whereKey($ownerB->getKey())->exists())->toBeTrue();
    });
});

it('denies record access for cross-tenant customers', function (): void {
    config()->set('cashier-chip.features.owner.enabled', true);
    config()->set('cashier-chip.features.owner.include_global', false);

    $ownerA = User::query()->create([
        'name' => 'Access A',
        'email' => 'customer-scope-access-a@example.com',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Access B',
        'email' => 'customer-scope-access-b@example.com',
    ]);

    bindCustomerScopeOwner($ownerA);

    $owner = OwnerContext::resolve();

    expect(CustomerResource::customerOwnerResolver()->canAccess($ownerA, $owner))->toBeTrue()
        ->and(CustomerResource::customerOwnerResolver()->canAccess($ownerB, $owner))->toBeFalse();
});

it('scopes customers through owned chip links when owner differs', function (): void {
    config()->set('cashier-chip.features.owner.enabled', true);
    config()->set('cashier-chip.features.owner.include_global', false);

    $org = CustomerScopeTestOrg::create(['name' => 'Org A']);

    $customerA = User::query()->create([
        'name' => 'Linked Customer',
        'email' => 'customer-scope-linked@example.com',
    ]);

    $customerB = User::query()->create([
        'name' => 'Unlinked Customer',
        'email' => 'customer-scope-unlinked@example.com',
    ]);

    $link = new ChipCustomerLink;
    $link->forceFill([
        'subject_type' => $customerA->getMorphClass(),
        'subject_id' => (string) $customerA->getKey(),
        'chip_customer_id' => 'chip-cust-scope-a',
        'owner_type' => $org->getMorphClass(),
        'owner_id' => (string) $org->getKey(),
    ]);
    $link->save();

    bindCustomerScopeOwner($org);

    expect(CustomerResource::getEloquentQuery()->whereKey($customerA->getKey())->exists())->toBeTrue()
        ->and(CustomerResource::getEloquentQuery()->whereKey($customerB->getKey())->exists())->toBeFalse();

    $owner = OwnerContext::resolve();

    expect(CustomerResource::customerOwnerResolver()->canAccess($customerA, $owner))->toBeTrue()
        ->and(CustomerResource::customerOwnerResolver()->canAccess($customerB, $owner))->toBeFalse();
});

it('allows actions for owner-scoped billables without links or subscriptions', function (): void {
    config()->set('cashier-chip.features.owner.enabled', true);
    config()->set('cashier-chip.features.owner.include_global', false);

    Cashier::useCustomerModel(OwnedBillableCustomer::class);

    $orgA = CustomerScopeTestOrg::create(['name' => 'Tuple Org A']);
    $orgB = CustomerScopeTestOrg::create(['name' => 'Tuple Org B']);

    $customerA = OwnerContext::withOwner($orgA, function () use ($orgA): OwnedBillableCustomer {
        $customer = new OwnedBillableCustomer;
        $customer->forceFill([
            'name' => 'Tuple Customer A',
            'email' => 'tuple-customer-a@example.com',
            'owner_type' => $orgA->getMorphClass(),
            'owner_id' => (string) $orgA->getKey(),
        ]);
        $customer->save();

        return $customer;
    });

    $customerB = OwnerContext::withOwner($orgB, function () use ($orgB): OwnedBillableCustomer {
        $customer = new OwnedBillableCustomer;
        $customer->forceFill([
            'name' => 'Tuple Customer B',
            'email' => 'tuple-customer-b@example.com',
            'owner_type' => $orgB->getMorphClass(),
            'owner_id' => (string) $orgB->getKey(),
        ]);
        $customer->save();

        return $customer;
    });

    bindCustomerScopeOwner($orgA);

    expect(CustomerResource::getEloquentQuery()->whereKey($customerA->getKey())->exists())->toBeTrue()
        ->and(CustomerResource::getEloquentQuery()->whereKey($customerB->getKey())->exists())->toBeFalse();

    $owner = OwnerContext::resolve();

    expect(CustomerResource::customerOwnerResolver()->canAccess($customerA, $owner))->toBeTrue()
        ->and(CustomerResource::customerOwnerResolver()->canAccess($customerB, $owner))->toBeFalse();
});

it('limits explicit global context to global rows on owner-scoped billables', function (): void {
    config()->set('cashier-chip.features.owner.enabled', true);
    config()->set('cashier-chip.features.owner.include_global', false);

    Cashier::useCustomerModel(OwnedBillableCustomer::class);

    $org = CustomerScopeTestOrg::create(['name' => 'Global Tuple Org']);

    $globalCustomer = OwnerContext::withOwner(null, function (): OwnedBillableCustomer {
        $customer = new OwnedBillableCustomer;
        $customer->forceFill([
            'name' => 'Global Tuple Customer',
            'email' => 'global-tuple-customer@example.com',
        ]);
        $customer->save();

        return $customer;
    });

    $tenantCustomer = OwnerContext::withOwner($org, function () use ($org): OwnedBillableCustomer {
        $customer = new OwnedBillableCustomer;
        $customer->forceFill([
            'name' => 'Tenant Tuple Customer',
            'email' => 'tenant-tuple-customer@example.com',
            'owner_type' => $org->getMorphClass(),
            'owner_id' => (string) $org->getKey(),
        ]);
        $customer->save();

        return $customer;
    });

    bindCustomerScopeOwner(null);

    OwnerContext::withOwner(null, function () use ($globalCustomer, $tenantCustomer): void {
        expect(CustomerResource::getEloquentQuery()->whereKey($globalCustomer->getKey())->exists())->toBeTrue()
            ->and(CustomerResource::getEloquentQuery()->whereKey($tenantCustomer->getKey())->exists())->toBeFalse();

        $owner = OwnerContext::resolve();

        expect(CustomerResource::customerOwnerResolver()->canAccess($globalCustomer, $owner))->toBeTrue()
            ->and(CustomerResource::customerOwnerResolver()->canAccess($tenantCustomer, $owner))->toBeFalse();
    });
});

final class OwnedBillableCustomer extends Model implements BillableContract
{
    use Billable;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'cashier-chip.features.owner';

    protected $table = 'owned_billable_customers';

    protected $guarded = [];
}

final class CustomerScopeTestOrg extends Model
{
    public $incrementing = false;

    protected $table = 'customer_scope_orgs';

    protected $guarded = [];

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::creating(function (self $org): void {
            if ($org->getKey() === null) {
                $org->setAttribute($org->getKeyName(), (string) Str::uuid());
            }
        });
    }
}

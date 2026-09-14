<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\Address;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeKey;
use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Contacting\Models\ContactMethod;
use AIArmada\Customers\Actions\CreateCustomer;
use AIArmada\Customers\Actions\MergeCustomers;
use AIArmada\Customers\Actions\SetDefaultCustomerAddress;
use AIArmada\Customers\Actions\UpdateCustomerProfile;
use AIArmada\Customers\Concerns\HasCustomerProfile;
use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\CustomerGroup;
use AIArmada\Customers\Models\CustomerNote;
use AIArmada\Customers\Models\Segment;
use AIArmada\Customers\Services\CustomerResolver;
use AIArmada\Customers\Services\SegmentationService;
use AIArmada\Persons\Models\Person;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\File;
use Spatie\MediaLibrary\Support\FileNamer\DefaultFileNamer;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;
use Spatie\MediaLibrary\Support\UrlGenerator\DefaultUrlGenerator;

require_once __DIR__ . '/Fixtures/CustomersTestOwner.php';

if (! class_exists(RepairTestUser::class)) {
    class RepairTestUser extends Model
    {
        use HasCustomerProfile;
        use HasUuids;

        protected $table = 'users';

        protected $guarded = [];
    }
}

/**
 * @param  array<string, mixed>  $attributes
 */
function createRegressionTestCustomer(array $attributes): Customer
{
    $restricted = [];

    foreach (['user_id', 'status', 'is_guest', 'accepts_marketing', 'created_at', 'updated_at'] as $key) {
        if (array_key_exists($key, $attributes)) {
            $restricted[$key] = $attributes[$key];
            unset($attributes[$key]);
        }
    }

    $customer = Customer::query()->create($attributes);

    if ($restricted !== []) {
        $customer->forceFill($restricted)->save();
    }

    return $customer;
}

beforeEach(function (): void {
    Schema::dropIfExists('test_owners');

    Schema::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    config()->set('customers.features.owner.enabled', true);
    config()->set('customers.features.owner.include_global', false);
});

describe('session customer owner scoping', function (): void {
    it('ignores a foreign-owner session customer in resolveExisting', function (): void {
        $ownerA = CustomersTestOwner::query()->create(['name' => 'Owner A']);
        $ownerB = CustomersTestOwner::query()->create(['name' => 'Owner B']);

        $foreignGuest = OwnerContext::withOwner($ownerB, fn (): Customer => createRegressionTestCustomer([
            'first_name' => 'Foreign',
            'last_name' => 'Guest',
            'status' => CustomerStatus::Active,
            'is_guest' => true,
        ]));

        $resolver = new CustomerResolver(new CreateCustomer, new UpdateCustomerProfile);

        $resolved = OwnerContext::withOwner($ownerA, fn (): ?Customer => $resolver->resolveExisting(
            user: null,
            sessionCustomer: $foreignGuest,
            billingData: [],
            shippingData: [],
        ));

        expect($resolved)->toBeNull();
    });

    it('does not claim a foreign-owner session customer in resolve', function (): void {
        $ownerA = CustomersTestOwner::query()->create(['name' => 'Owner A']);
        $ownerB = CustomersTestOwner::query()->create(['name' => 'Owner B']);
        $email = 'foreign-session-' . uniqid() . '@example.com';

        $user = User::factory()->create(['email' => $email]);

        $foreignGuest = OwnerContext::withOwner($ownerB, function () use ($email): Customer {
            $guest = createRegressionTestCustomer([
                'first_name' => 'Foreign',
                'last_name' => 'Guest',
                'status' => CustomerStatus::Active,
                'is_guest' => true,
            ]);
            $guest->addContactMethod(ContactMethodData::email($email));

            return $guest;
        });

        $resolver = new CustomerResolver(new CreateCustomer, new UpdateCustomerProfile);

        $resolved = OwnerContext::withOwner($ownerA, fn (): ?Customer => $resolver->resolve(
            user: $user,
            sessionCustomer: $foreignGuest,
            billingData: ['email' => $email],
            shippingData: [],
        ));

        expect($resolved)->not->toBeNull()
            ->and($resolved?->getKey())->not->toBe($foreignGuest->getKey())
            ->and($foreignGuest->fresh()?->user_id)->toBeNull()
            ->and($foreignGuest->fresh()?->is_guest)->toBeTrue();
    });
});

describe('user relation owner verification', function (): void {
    it('does not resolve a foreign-owner profile through the user relation', function (): void {
        $ownerA = CustomersTestOwner::query()->create(['name' => 'Owner A']);
        $ownerB = CustomersTestOwner::query()->create(['name' => 'Owner B']);
        $email = 'relation-' . uniqid() . '@example.com';

        $user = RepairTestUser::query()->create([
            'name' => 'Relation User',
            'email' => $email,
            'password' => 'password',
        ]);

        $foreignProfile = OwnerContext::withOwner($ownerB, fn (): Customer => createRegressionTestCustomer([
            'user_id' => $user->getKey(),
            'first_name' => 'Foreign',
            'last_name' => 'Profile',
            'status' => CustomerStatus::Active,
        ]));

        $resolver = new CustomerResolver(new CreateCustomer, new UpdateCustomerProfile);

        $resolved = OwnerContext::withOwner($ownerA, fn (): ?Customer => $resolver->resolveExisting(
            user: $user,
            sessionCustomer: null,
            billingData: [],
            shippingData: [],
        ));

        expect($resolved)->toBeNull()
            ->and($foreignProfile->fresh()?->first_name)->toBe('Foreign');
    });
});

describe('user profile uniqueness', function (): void {
    it('ships a scoped unique index on user_id in the customers create', function (): void {
        $customersTable = config('customers.database.tables.customers', 'customers');

        expect(Schema::hasIndex($customersTable, 'customers_owner_user_unique'))->toBeTrue()
            ->and(Schema::hasIndex($customersTable, ['owner_type', 'owner_id', 'user_id']))->toBeTrue();
    });

    it('rejects duplicate user profiles within one owner scope', function (): void {
        $owner = CustomersTestOwner::query()->create(['name' => 'Owner']);
        $userId = (string) Str::uuid();

        OwnerContext::withOwner($owner, fn (): Customer => createRegressionTestCustomer([
            'user_id' => $userId,
            'first_name' => 'First',
            'last_name' => 'Profile',
            'status' => CustomerStatus::Active,
        ]));

        expect(fn (): Customer => OwnerContext::withOwner($owner, fn (): Customer => createRegressionTestCustomer([
            'user_id' => $userId,
            'first_name' => 'Second',
            'last_name' => 'Profile',
            'status' => CustomerStatus::Active,
        ])))->toThrow(UniqueConstraintViolationException::class);
    });

    it('allows the same user in different owner scopes', function (): void {
        $ownerA = CustomersTestOwner::query()->create(['name' => 'Owner A']);
        $ownerB = CustomersTestOwner::query()->create(['name' => 'Owner B']);
        $userId = (string) Str::uuid();

        $profileA = OwnerContext::withOwner($ownerA, fn (): Customer => createRegressionTestCustomer([
            'user_id' => $userId,
            'first_name' => 'A',
            'last_name' => 'Profile',
            'status' => CustomerStatus::Active,
        ]));
        $profileB = OwnerContext::withOwner($ownerB, fn (): Customer => createRegressionTestCustomer([
            'user_id' => $userId,
            'first_name' => 'B',
            'last_name' => 'Profile',
            'status' => CustomerStatus::Active,
        ]));

        expect($profileA->getKey())->not->toBe($profileB->getKey());
    });

    it('returns the existing profile when getOrCreate races a duplicate', function (): void {
        $owner = CustomersTestOwner::query()->create(['name' => 'Owner']);

        $user = RepairTestUser::query()->create([
            'name' => 'Race User',
            'email' => 'race-' . uniqid() . '@example.com',
            'password' => 'password',
        ]);

        [$first, $second] = OwnerContext::withOwner($owner, fn (): array => [
            $user->getOrCreateCustomerProfile(),
            $user->fresh()?->getOrCreateCustomerProfile(),
        ]);

        expect($second?->getKey())->toBe($first->getKey())
            ->and(OwnerContext::withOwner($owner, fn (): int => Customer::query()->where('user_id', $user->getKey())->count()))->toBe(1);
    });
});

describe('email uniqueness race', function (): void {
    it('surfaces a validation exception when a concurrent insert wins the race', function (): void {
        $owner = CustomersTestOwner::query()->create(['name' => 'Owner']);
        $email = 'race-' . uniqid() . '@example.com';

        $customer = OwnerContext::withOwner($owner, fn (): Customer => Customer::query()->create([
            'first_name' => 'Race',
            'last_name' => 'Loser',
            'status' => CustomerStatus::Active,
        ]));

        $rival = OwnerContext::withOwner($owner, fn (): Customer => Customer::query()->create([
            'first_name' => 'Race',
            'last_name' => 'Winner',
            'status' => CustomerStatus::Active,
        ]));

        $armed = true;

        ContactMethod::creating(function (ContactMethod $model) use (&$armed, $email, $owner, $rival): void {
            if (! $armed) {
                return;
            }

            $armed = false;

            OwnerContext::withOwner($owner, function () use ($email, $rival): void {
                $rival->addContactMethod(ContactMethodData::email($email));
            });
        });

        try {
            expect(fn (): ContactMethod => OwnerContext::withOwner($owner, fn (): ContactMethod => $customer->addContactMethod(
                ContactMethodData::email($email)
            )))->toThrow(ValidationException::class);
        } finally {
            $armed = false;
        }
    });
});

describe('unified segment semantics', function (): void {
    it('matches nothing in both paths for empty conditions', function (): void {
        $segment = Segment::query()->create([
            'name' => 'Empty Conditions ' . uniqid(),
            'slug' => 'empty-conditions-' . uniqid(),
            'is_automatic' => true,
            'is_active' => true,
            'conditions' => [],
        ]);

        $customer = Customer::query()->create([
            'first_name' => 'Empty',
            'last_name' => 'Match',
            'status' => CustomerStatus::Active,
            'accepts_marketing' => true,
        ]);

        $service = app(SegmentationService::class);

        expect($segment->getMatchingCustomers())->toBeEmpty()
            ->and($service->customerMatchesSegment($customer, $segment))->toBeFalse();
    });

    it('matches nothing in both paths for unknown fields and missing values', function (): void {
        $service = app(SegmentationService::class);

        $customer = Customer::query()->create([
            'first_name' => 'Unknown',
            'last_name' => 'Field',
            'status' => CustomerStatus::Active,
            'accepts_marketing' => true,
        ]);

        foreach ([
            [['field' => 'no_such_field', 'value' => 'x']],
            [['field' => 'accepts_marketing']],
            [['value' => true]],
        ] as $index => $conditions) {
            $segment = Segment::query()->create([
                'name' => 'Unknown ' . $index . ' ' . uniqid(),
                'slug' => 'unknown-' . $index . '-' . uniqid(),
                'is_automatic' => true,
                'is_active' => true,
                'conditions' => $conditions,
            ]);

            expect($segment->getMatchingCustomers())->toBeEmpty("query path case {$index}")
                ->and($service->customerMatchesSegment($customer, $segment))->toBeFalse("memory path case {$index}");
        }
    });

    it('excludes non-active customers in both paths', function (): void {
        $segment = Segment::query()->create([
            'name' => 'Suspended Excluded ' . uniqid(),
            'slug' => 'suspended-excluded-' . uniqid(),
            'is_automatic' => true,
            'is_active' => true,
            'conditions' => [
                ['field' => 'accepts_marketing', 'value' => true],
            ],
        ]);

        $suspended = createRegressionTestCustomer([
            'first_name' => 'Suspended',
            'last_name' => 'Customer',
            'status' => CustomerStatus::Suspended,
            'accepts_marketing' => true,
        ]);

        $service = app(SegmentationService::class);

        expect($segment->getMatchingCustomers()->pluck('id'))->not->toContain($suspended->id)
            ->and($service->customerMatchesSegment($suspended, $segment))->toBeFalse();
    });
});

describe('bounded segment operations', function (): void {
    it('computes segment stats without hydrating member models', function (): void {
        $segment = Segment::query()->create([
            'name' => 'Stats Aggregate ' . uniqid(),
            'slug' => 'stats-aggregate-' . uniqid(),
            'is_automatic' => false,
            'is_active' => true,
        ]);

        foreach ([
            [CustomerStatus::Active, true],
            [CustomerStatus::Active, false],
            [CustomerStatus::Suspended, true],
        ] as [$status, $marketing]) {
            $customer = createRegressionTestCustomer([
                'first_name' => 'Stats',
                'last_name' => uniqid(),
                'status' => $status,
                'accepts_marketing' => $marketing,
            ]);
            $segment->addCustomer($customer);
        }

        $stats = app(SegmentationService::class)->getSegmentStats($segment);

        expect($stats['customer_count'])->toBe(3)
            ->and($stats['active_count'])->toBe(2)
            ->and($stats['marketing_opted_in'])->toBe(2)
            ->and($stats['marketing_opted_in_percentage'])->toBe(66.7);
    });

    it('counts matching customers without hydrating them', function (): void {
        $segment = Segment::query()->create([
            'name' => 'Count Only ' . uniqid(),
            'slug' => 'count-only-' . uniqid(),
            'is_automatic' => true,
            'is_active' => true,
            'conditions' => [
                ['field' => 'accepts_marketing', 'value' => true],
            ],
        ]);

        createRegressionTestCustomer([
            'first_name' => 'Counted',
            'last_name' => 'Customer',
            'status' => CustomerStatus::Active,
            'accepts_marketing' => true,
        ]);

        expect($segment->countMatchingCustomers())->toBe($segment->getMatchingCustomers()->count())
            ->and($segment->countMatchingCustomers())->toBeGreaterThanOrEqual(1);
    });
});

describe('sargable email lookup', function (): void {
    it('matches stored normalized emails without wrapping the column', function (): void {
        $owner = CustomersTestOwner::query()->create(['name' => 'Owner']);
        $email = 'sargable-' . uniqid() . '@example.com';

        $guest = OwnerContext::withOwner($owner, function () use ($email): Customer {
            $guest = createRegressionTestCustomer([
                'first_name' => 'Sargable',
                'last_name' => 'Guest',
                'status' => CustomerStatus::Active,
                'is_guest' => true,
            ]);
            $guest->addContactMethod(ContactMethodData::email($email));

            return $guest;
        });

        $queries = [];

        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        try {
            $resolved = OwnerContext::withOwner($owner, fn (): ?Customer => (new CustomerResolver(
                new CreateCustomer,
                new UpdateCustomerProfile,
            ))->resolveExisting(
                user: null,
                sessionCustomer: null,
                billingData: ['email' => mb_strtoupper($email)],
                shippingData: [],
            ));
        } finally {
            DB::flushQueryLog();
        }

        $emailLookups = array_values(array_filter(
            $queries,
            fn (string $sql): bool => str_contains($sql, 'contact_methods') && str_contains($sql, 'normalized_value')
        ));

        expect($resolved?->getKey())->toBe($guest->getKey())
            ->and($emailLookups)->not->toBeEmpty();

        foreach ($emailLookups as $sql) {
            expect($sql)->toContain('"normalized_value" = ?')
                ->and($sql)->not->toContain('lower(trim(coalesce("normalized_value"');
        }
    });
});

describe('narrowed customer fillable', function (): void {
    it('ignores lifecycle timestamps and metadata passed to mass assignment', function (): void {
        $customer = Customer::query()->create([
            'first_name' => 'Guarded',
            'last_name' => 'Fields',
            'status' => CustomerStatus::Active,
            'registered_at' => '2020-01-01 00:00:00',
            'activated_at' => '2020-01-01 00:00:00',
            'deactivated_at' => '2020-01-01 00:00:00',
            'suspended_at' => '2020-01-01 00:00:00',
            'verified_at' => '2020-01-01 00:00:00',
            'marketing_consented_at' => '2020-01-01 00:00:00',
            'marketing_revoked_at' => '2020-01-01 00:00:00',
            'metadata' => ['forged' => true],
        ]);

        expect($customer->registered_at)->toBeNull()
            ->and($customer->activated_at)->toBeNull()
            ->and($customer->deactivated_at)->toBeNull()
            ->and($customer->suspended_at)->toBeNull()
            ->and($customer->verified_at)->toBeNull()
            ->and($customer->marketing_consented_at)->toBeNull()
            ->and($customer->marketing_revoked_at)->toBeNull()
            ->and($customer->metadata)->toBeNull();
    });

    it('still stamps marketing consent timestamps through opt-in and opt-out', function (): void {
        $customer = Customer::query()->create([
            'first_name' => 'Consent',
            'last_name' => 'Stamps',
            'status' => CustomerStatus::Active,
        ]);

        $customer->optInMarketing();

        expect($customer->accepts_marketing)->toBeTrue()
            ->and($customer->marketing_consented_at)->not->toBeNull();

        $customer->optOutMarketing();

        expect($customer->accepts_marketing)->toBeFalse()
            ->and($customer->fresh()?->marketing_revoked_at)->not->toBeNull();
    });
});

describe('restricted documents collection', function (): void {
    it('whitelists safe mime types and caps file size', function (): void {
        $customer = Customer::query()->create([
            'first_name' => 'Media',
            'last_name' => 'Guard',
            'status' => CustomerStatus::Active,
        ]);

        $collection = $customer->getMediaCollection('documents');

        expect($collection)->not->toBeNull()
            ->and($collection->acceptsMimeTypes)->toContain('application/pdf')
            ->and($collection->acceptsMimeTypes)->not->toContain('image/svg+xml')
            ->and($collection->acceptsMimeTypes)->not->toContain('text/html');

        $acceptsFile = $collection->acceptsFile;

        expect($acceptsFile(new File('small.pdf', 1024, 'application/pdf'), $customer))->toBeTrue()
            ->and($acceptsFile(new File('huge.pdf', 11 * 1024 * 1024, 'application/pdf'), $customer))->toBeFalse();
    });
});

describe('owner-safe address attach and merge', function (): void {
    it('refuses to attach a foreign-owner address as default', function (): void {
        $ownerA = CustomersTestOwner::query()->create(['name' => 'Owner A']);
        $ownerB = CustomersTestOwner::query()->create(['name' => 'Owner B']);

        $customer = OwnerContext::withOwner($ownerA, fn (): Customer => Customer::query()->create([
            'first_name' => 'Attach',
            'last_name' => 'Guard',
            'status' => CustomerStatus::Active,
        ]));

        $foreignAddress = OwnerContext::withOwner($ownerB, fn (): Address => Address::query()->create([
            'line1' => '1 Foreign Street',
            'city' => 'Kuala Lumpur',
            'postcode' => '50000',
            'country_code' => 'MY',
        ]));

        expect(fn (): mixed => OwnerContext::withOwner($ownerA, fn (): mixed => app(SetDefaultCustomerAddress::class)->execute(
            $customer,
            $foreignAddress,
            'shipping',
        )))->toThrow(InvalidArgumentException::class, 'same owner context');
    });

    it('preserves group pivot role and joined_at when merging', function (): void {
        $source = Customer::query()->create([
            'first_name' => 'Pivot',
            'last_name' => 'Source',
            'status' => CustomerStatus::Active,
            'is_guest' => true,
        ]);
        $target = Customer::query()->create([
            'first_name' => 'Pivot',
            'last_name' => 'Target',
            'status' => CustomerStatus::Active,
        ]);

        $group = CustomerGroup::query()->create(['name' => 'Pivot Group ' . uniqid()]);
        $joinedAt = now()->subDays(3)->toDateTimeString();
        $source->groups()->attach($group->getKey(), ['role' => 'admin', 'joined_at' => $joinedAt]);

        app(MergeCustomers::class)->execute($target, $source);

        $pivot = $target->fresh()?->groups()->whereKey($group->getKey())->first()?->pivot;

        expect($pivot?->role)->toBe('admin')
            ->and((string) $pivot?->joined_at)->toContain(mb_substr($joinedAt, 0, 16));
    });

    it('refuses to merge when the source has notes outside the target owner context', function (): void {
        $ownerA = CustomersTestOwner::query()->create(['name' => 'Owner A']);
        $ownerB = CustomersTestOwner::query()->create(['name' => 'Owner B']);

        [$source, $target] = OwnerContext::withOwner($ownerA, fn (): array => [
            Customer::query()->create([
                'first_name' => 'Note',
                'last_name' => 'Source',
                'status' => CustomerStatus::Active,
                'is_guest' => true,
            ]),
            Customer::query()->create([
                'first_name' => 'Note',
                'last_name' => 'Target',
                'status' => CustomerStatus::Active,
            ]),
        ]);

        OwnerContext::withOwner($ownerB, fn (): CustomerNote => CustomerNote::query()->create([
            'customer_id' => $source->getKey(),
            'content' => 'Foreign note',
        ]));

        expect(fn (): Customer => app(MergeCustomers::class)->execute($target, $source))
            ->toThrow(InvalidArgumentException::class, 'outside the target owner context');
    });
});

describe('checkout contact bloat', function (): void {
    it('does not duplicate phone rows on repeat profile updates', function (): void {
        $customer = Customer::query()->create([
            'first_name' => 'Phone',
            'last_name' => 'Dedupe',
            'status' => CustomerStatus::Active,
            'is_guest' => true,
        ]);

        $action = new UpdateCustomerProfile;

        $action->execute($customer, ['phone' => '+60123456789'], [], null);
        $action->execute($customer->fresh() ?? $customer, ['phone' => '+60123456789'], [], null);

        expect($customer->fresh()?->contactMethods()->where('type', 'phone')->count())->toBe(1);
    });

    it('does not duplicate addresses that differ only by case', function (): void {
        $resolver = new CustomerResolver(new CreateCustomer, new UpdateCustomerProfile);
        $email = 'case-address-' . uniqid() . '@example.com';

        $addressData = [
            'email' => $email,
            'line1' => '123 same street',
            'city' => 'kuala lumpur',
            'postcode' => '50000',
            'country' => 'MY',
        ];

        $first = $resolver->resolve(user: null, sessionCustomer: null, billingData: $addressData, shippingData: []);

        $second = $resolver->resolve(
            user: null,
            sessionCustomer: $first,
            billingData: array_merge($addressData, ['line1' => '123 SAME STREET', 'city' => 'KUALA LUMPUR']),
            shippingData: [],
        );

        expect($second?->getKey())->toBe($first?->getKey())
            ->and($first?->fresh()?->addresses()->wherePivot('type', 'billing')->count())->toBe(1);
    });
});

describe('slug conflict handling', function (): void {
    it('surfaces a validation exception when a concurrent insert wins the slug race', function (): void {
        $owner = CustomersTestOwner::query()->create(['name' => 'Owner']);
        $slug = 'race-slug-' . uniqid();
        $table = config('customers.database.tables.segments', 'customer_segments');
        $armed = true;

        Segment::saving(function (Segment $segment) use (&$armed, $owner, $slug, $table): void {
            if (! $armed || $segment->slug !== $slug) {
                return;
            }

            $armed = false;

            DB::table($table)->insert([
                'id' => (string) Str::uuid(),
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
                'owner_scope' => OwnerScopeKey::forOwner($owner),
                'name' => 'Race Winner',
                'slug' => $slug,
                'type' => 'custom',
                'is_automatic' => true,
                'priority' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        try {
            expect(fn (): Segment => OwnerContext::withOwner($owner, fn (): Segment => Segment::query()->create([
                'name' => 'Race Loser',
                'slug' => $slug,
            ])))->toThrow(ValidationException::class);
        } finally {
            $armed = false;
        }
    });
});

describe('marketing default and customer indexes', function (): void {
    it('defaults new customers to no marketing consent', function (): void {
        $customer = Customer::query()->create([
            'first_name' => 'Default',
            'last_name' => 'Consent',
            'status' => CustomerStatus::Active,
        ]);

        expect($customer->accepts_marketing)->toBeFalse()
            ->and($customer->fresh()?->accepts_marketing)->toBeFalse();
    });

    it('ships a created_at index in the customers create', function (): void {
        $customersTable = config('customers.database.tables.customers', 'customers');

        expect(Schema::hasIndex($customersTable, 'customers_created_at_index'))->toBeTrue();
    });
});

describe('reactivation clears deactivated_at', function (): void {
    it('clears deactivated_at when a segment is reactivated', function (): void {
        $segment = Segment::query()->create([
            'name' => 'Reactivate ' . uniqid(),
            'slug' => 'reactivate-' . uniqid(),
            'is_active' => true,
        ]);

        $segment->update(['is_active' => false]);

        expect($segment->fresh()?->deactivated_at)->not->toBeNull();

        $segment->update(['is_active' => true]);

        expect($segment->fresh()?->deactivated_at)->toBeNull();
    });

    it('clears deactivated_at when a group is reactivated', function (): void {
        $group = CustomerGroup::query()->create([
            'name' => 'Reactivate Group ' . uniqid(),
            'is_active' => true,
        ]);

        $group->update(['is_active' => false]);

        expect($group->fresh()?->deactivated_at)->not->toBeNull();

        $group->update(['is_active' => true]);

        expect($group->fresh()?->deactivated_at)->toBeNull();
    });
});

describe('customer delete cascades', function (): void {
    it('removes contact methods, social profiles, and media with the customer', function (): void {
        config()->set('media-library.file_namer', DefaultFileNamer::class);
        config()->set('media-library.path_generator', DefaultPathGenerator::class);
        config()->set('media-library.url_generator', DefaultUrlGenerator::class);
        config()->set('media-library.max_file_size', 10 * 1024 * 1024);
        Storage::fake('public');

        $customer = Customer::query()->create([
            'first_name' => 'Cascade',
            'last_name' => 'Delete',
            'status' => CustomerStatus::Active,
        ]);

        $contact = $customer->addContactMethod(ContactMethodData::email('cascade-' . uniqid() . '@example.com'));

        $social = $customer->socialProfiles()->create([
            'platform' => 'x',
            'handle' => 'cascade-' . uniqid(),
        ]);

        $customer->addMediaFromString('repair-regression')->usingFileName('repair-regression.txt')->toMediaCollection('documents');
        $mediaId = $customer->fresh()?->getFirstMedia('documents')?->getKey();

        expect($mediaId)->not->toBeNull();

        $customer->delete();

        expect(ContactMethod::query()->withoutOwnerScope()->whereKey($contact->getKey())->exists())->toBeFalse()
            ->and($customer->socialProfiles()->withoutOwnerScope()->whereKey($social->getKey())->exists())->toBeFalse()
            ->and($mediaId !== null && $customer->media()->whereKey($mediaId)->exists())->toBeFalse();
    });
});

describe('atomic person linkage', function (): void {
    beforeEach(function (): void {
        config()->set('persons.models.person', Person::class);

        Schema::dropIfExists('persons');

        Schema::create('persons', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->timestamps();
        });
    });

    it('rolls back customer creation when the person link fails', function (): void {
        $email = 'link-fail-' . uniqid() . '@example.com';
        $before = Customer::query()->withoutOwnerScope()->count();

        expect(fn (): Customer => (new CreateCustomer)->execute(
            $email,
            ['name' => 'Link Fail'],
            [],
            null,
            true,
            (string) Str::uuid(),
        ))->toThrow(ModelNotFoundException::class);

        expect(Customer::query()->withoutOwnerScope()->count())->toBe($before);
    });

    it('rolls back profile updates when the person link fails', function (): void {
        $customer = Customer::query()->create([
            'first_name' => 'Link',
            'last_name' => 'Rollback',
            'status' => CustomerStatus::Active,
        ]);

        expect(fn (): mixed => (new UpdateCustomerProfile)->execute(
            $customer,
            ['first_name' => 'Changed', 'last_name' => 'Name'],
            [],
            null,
            (string) Str::uuid(),
        ))->toThrow(ModelNotFoundException::class);

        expect($customer->fresh()?->first_name)->toBe('Link');
    });
});

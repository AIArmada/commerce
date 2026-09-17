<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Contacting\Actions\BuildContactLinksAction;
use AIArmada\Contacting\Actions\CreateContactSnapshotAction;
use AIArmada\Contacting\Actions\NormalizeContactMethodAction;
use AIArmada\Contacting\Actions\NormalizeSocialProfileAction;
use AIArmada\Contacting\Actions\UpdateContactMethodAction;
use AIArmada\Contacting\Actions\UpdateSocialProfileAction;
use AIArmada\Contacting\Concerns\HasContactMethods;
use AIArmada\Contacting\Concerns\HasSocialProfiles;
use AIArmada\Contacting\Contracts\ContactMethodNormalizer;
use AIArmada\Contacting\Contracts\SocialProfileNormalizer;
use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Contacting\Data\SocialProfileData;
use AIArmada\Contacting\Models\ContactMethod;
use AIArmada\Contacting\Models\ContactSnapshot;
use AIArmada\Contacting\Models\SocialProfile;
use AIArmada\Contacting\Support\NormalizesUrl;
use AIArmada\Contacting\Support\SocialProfileConfig;
use AIArmada\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class RepairCascadeParent extends Model
{
    use HasContactMethods;
    use HasSocialProfiles;
    use HasUuids;

    protected $table = 'contacting_repair_parents';

    protected $guarded = [];
}

beforeEach(function (): void {
    Schema::dropIfExists('contacting_repair_parents');

    Schema::create('contacting_repair_parents', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->timestamps();
    });
});

function regressionCustomer(string $prefix): Customer
{
    return Customer::create([
        'first_name' => 'Repair',
        'last_name' => $prefix,
        'email' => 'repair-' . $prefix . '-' . uniqid() . '@example.com',
        'status' => 'active',
    ]);
}

it('keeps flags and metadata on partial contact method updates', function (): void {
    $customer = regressionCustomer('flags');
    $method = $customer->addContactMethod(new ContactMethodData(
        type: 'email',
        purpose: 'general',
        value: 'partial-' . uniqid() . '@example.com',
        isPrimary: true,
        isVerified: true,
        metadata: ['k' => 'v'],
    ));

    $updated = app(UpdateContactMethodAction::class)->execute($method, new ContactMethodData(
        type: 'email',
        purpose: 'general',
        value: 'partial2-' . uniqid() . '@example.com',
    ));

    expect($updated->is_primary)->toBeTrue()
        ->and($updated->is_verified)->toBeTrue()
        ->and($updated->metadata)->toBe(['k' => 'v'])
        ->and($updated->value)->toStartWith('partial2-');

    $demoted = app(UpdateContactMethodAction::class)->execute($updated, new ContactMethodData(
        type: 'email',
        purpose: 'general',
        value: $updated->value,
        isPrimary: false,
        metadata: [],
    ));

    expect($demoted->is_primary)->toBeFalse()
        ->and($demoted->metadata)->toBe([]);
});

it('keeps flags and metadata on partial social profile updates', function (): void {
    $customer = regressionCustomer('social-flags');
    $profile = $customer->addSocialProfile(new SocialProfileData(
        platform: 'facebook',
        purpose: 'general',
        handle: 'keep-' . uniqid(),
        isPrimary: true,
        metadata: ['k' => 'v'],
        sortOrder: 3,
    ));

    $updated = app(UpdateSocialProfileAction::class)->execute($profile, new SocialProfileData(
        platform: 'facebook',
    ));

    expect($updated->is_primary)->toBeTrue()
        ->and($updated->metadata)->toBe(['k' => 'v'])
        ->and($updated->handle)->toBe($profile->handle)
        ->and($updated->sort_order)->toBe(3);
});

it('rejects empty and invalid contact data at the action boundary', function (): void {
    $customer = regressionCustomer('validation');

    expect(fn () => $customer->addContactMethod([]))->toThrow(ValidationException::class);
    expect(fn () => $customer->addContactMethod(ContactMethodData::email('not-an-email')))
        ->toThrow(ValidationException::class);
    expect(fn () => $customer->addContactMethod(new ContactMethodData(
        type: 'email',
        purpose: 'nope',
        value: 'a@example.com',
    )))->toThrow(ValidationException::class);

    expect(ContactMethod::query()->where('contactable_id', $customer->getKey())->count())->toBe(0);

    $padded = $customer->addContactMethod(ContactMethodData::email('  padded-' . uniqid() . '@example.com  '));

    expect($padded->normalized_value)->toBe(mb_strtolower(mb_trim($padded->value)));
});

it('skips rows with null normalized values in resolvers', function (): void {
    $customer = regressionCustomer('resolvers');

    ContactMethod::query()->create([
        'contactable_type' => $customer->getMorphClass(),
        'contactable_id' => $customer->getKey(),
        'type' => 'email',
        'purpose' => 'general',
        'value' => 'not-an-email',
        'is_primary' => true,
    ]);

    expect($customer->resolveEmail())->toBeNull()
        ->and($customer->resolveEmails())->toBe([])
        ->and($customer->primaryContactMethod('email'))->toBeNull();

    $good = $customer->addContactMethod(ContactMethodData::email('good-' . uniqid() . '@example.com'));

    expect($customer->resolveEmail())->toBe($good->normalized_value);
});

it('still creates contact methods from documented array shapes', function (): void {
    $customer = regressionCustomer('array-shape');

    $method = $customer->addContactMethod([
        'type' => 'email',
        'purpose' => 'admin',
        'label' => 'Admin',
        'value' => 'array-' . uniqid() . '@example.com',
        'is_primary' => true,
        'is_public' => true,
    ]);

    expect($method->is_primary)->toBeTrue()
        ->and($method->label)->toBe('Admin')
        ->and($method->is_verified)->toBeFalse()
        ->and($method->metadata)->toBe([]);
});

it('deletes mixed-owner children through model events when the parent is deleted', function (): void {
    $parent = RepairCascadeParent::query()->create([]);
    $ownerA = User::query()->create([
        'name' => 'Repair A',
        'email' => 'repair-a-' . uniqid() . '@example.com',
        'password' => bcrypt('password'),
    ]);
    $ownerB = User::query()->create([
        'name' => 'Repair B',
        'email' => 'repair-b-' . uniqid() . '@example.com',
        'password' => bcrypt('password'),
    ]);

    $methodA = OwnerContext::withOwner($ownerA, fn () => $parent->addContactMethod(
        ContactMethodData::email('cascade-a-' . uniqid() . '@example.com')
    ));
    $methodB = OwnerContext::withOwner($ownerB, fn () => $parent->addContactMethod(
        ContactMethodData::email('cascade-b-' . uniqid() . '@example.com')
    ));
    $methodGlobal = OwnerContext::withOwner(null, fn () => $parent->addContactMethod(
        ContactMethodData::email('cascade-g-' . uniqid() . '@example.com')
    ));
    $profileA = OwnerContext::withOwner($ownerA, fn () => $parent->addSocialProfile(
        new SocialProfileData(platform: 'facebook', handle: 'cascade-a-' . uniqid())
    ));

    $deletedMethods = [];
    ContactMethod::deleting(function (ContactMethod $method) use (&$deletedMethods): void {
        $deletedMethods[] = $method->id;
    });

    $parent->delete();

    expect(ContactMethod::query()->withoutOwnerScope()->whereKey([$methodA->id, $methodB->id, $methodGlobal->id])->count())
        ->toBe(0)
        ->and(SocialProfile::query()->withoutOwnerScope()->whereKey($profileA->id)->exists())->toBeFalse()
        ->and($deletedMethods)->toContain($methodA->id, $methodB->id, $methodGlobal->id);
});

it('honors explicit display, validity, ordering, and verification inputs', function (): void {
    $customer = regressionCustomer('honor-fields');

    $method = $customer->addContactMethod(new ContactMethodData(
        type: 'phone',
        purpose: 'general',
        value: '+60123456789',
        countryCode: 'MY',
        displayValue: '+60 12-345 6789 ext. 5',
        isVerified: true,
        verifiedAt: '2026-01-02 03:04:05',
        validFrom: '2026-01-01',
        validUntil: '2026-12-31',
        sortOrder: 7,
    ));

    expect($method->display_value)->toBe('+60 12-345 6789 ext. 5')
        ->and($method->valid_from->toDateString())->toBe('2026-01-01')
        ->and($method->valid_until->toDateString())->toBe('2026-12-31')
        ->and($method->sort_order)->toBe(7)
        ->and($method->verified_at->toDateTimeString())->toBe('2026-01-02 03:04:05');

    $updated = app(UpdateContactMethodAction::class)->execute($method, new ContactMethodData(
        type: 'phone',
        purpose: 'general',
        value: '+60123456789',
        countryCode: 'MY',
        displayValue: 'Custom display',
    ));

    expect($updated->display_value)->toBe('Custom display');

    $kept = app(UpdateContactMethodAction::class)->execute($updated, new ContactMethodData(
        type: 'phone',
        purpose: 'general',
        value: '+60123456789',
        countryCode: 'MY',
        label: 'Desk',
    ));

    expect($kept->display_value)->toBe('Custom display');
});

it('treats omitted country codes as null on create', function (): void {
    $customer = regressionCustomer('country');

    $method = $customer->addContactMethod(ContactMethodData::from([
        'type' => 'phone',
        'purpose' => 'general',
        'value' => '+60123456789',
    ]));

    expect($method->country_code)->toBeNull()
        ->and($method->normalized_value)->toBe('+60123456789');
});

it('builds only safe contact links', function (): void {
    $action = new BuildContactLinksAction;

    $make = function (string $type, ?string $normalized): ContactMethod {
        $method = new ContactMethod;
        $method->type = $type;
        $method->normalized_value = $normalized;
        $method->value = $normalized ?? '';

        return $method;
    };

    $links = $action->execute([
        $make('email', 'not-an-email'),
        $make('phone', "123\r\nBcc: x"),
        $make('whatsapp', '+60 (12) 345-6789'),
        $make('telegram', 'https://evil.example/x'),
        $make('telegram', 'my channel'),
        $make('telegram', 'https://t.me/mychannel'),
    ]);

    expect($links->mailtoUrl)->toBeNull()
        ->and($links->telUrl)->toBeNull()
        ->and($links->whatsappUrl)->toBe('https://wa.me/60123456789')
        ->and($links->links['telegram'] ?? [])->toBe([
            'https://t.me/my%20channel',
            'https://t.me/mychannel',
        ]);
});

it('encode handles and strip queries from social URLs', function (): void {
    $config = new SocialProfileConfig;

    expect($config->extractHandle('facebook', 'https://www.facebook.com/somehandle?ref=abc&x=1'))
        ->toBe('somehandle')
        ->and($config->extractHandle('facebook', 'https://www.facebook.com/somehandle#about'))
        ->toBe('somehandle')
        ->and($config->buildUrl('facebook', 'han dle/x?y=1'))
        ->toBe('https://www.facebook.com/han%20dle%2Fx%3Fy%3D1')
        ->and((new NormalizesUrl)->normalize('HTTP://EXAMPLE.COM/Path'))
        ->toBe('http://example.com/Path');
});

it('syncs verification timestamps with the flag', function (): void {
    $customer = regressionCustomer('verify');

    $method = $customer->addContactMethod(ContactMethodData::email('verify-' . uniqid() . '@example.com'));
    expect($method->verified_at)->toBeNull();

    $method->is_verified = true;
    $method->save();
    expect($method->fresh()?->verified_at)->not->toBeNull();

    $method->is_verified = false;
    $method->save();
    expect($method->fresh()?->verified_at)->toBeNull();

    $profile = $customer->addSocialProfile(new SocialProfileData(
        platform: 'facebook',
        handle: 'verify-' . uniqid(),
    ));
    $profile->is_verified = true;
    $profile->save();
    expect($profile->fresh()?->verified_at)->not->toBeNull();
});

it('uses collision-safe expressions in the mysql primary backstop', function (): void {
    $migrationBase = dirname(__DIR__, 3) . '/packages/contacting/database/migrations/';

    foreach ([
        '2000_01_01_000001_create_contact_methods_table.php',
        '2000_01_01_000003_create_contact_social_profiles_table.php',
    ] as $file) {
        $migration = (string) file_get_contents($migrationBase . $file);

        expect($migration)->toContain('JSON_ARRAY')
            ->and($migration)->not->toContain('CONCAT_WS');
    }
});

it('snapshots bundles in bulk with per-source owners', function (): void {
    $customer = regressionCustomer('bundle');

    $method = $customer->addContactMethod(ContactMethodData::email('bundle-' . uniqid() . '@example.com'));
    $phone = $customer->addContactMethod(ContactMethodData::phone('+60123456789'));
    $profile = $customer->addSocialProfile(new SocialProfileData(
        platform: 'facebook',
        handle: 'bundle-' . uniqid(),
    ));

    $snapshots = (new CreateContactSnapshotAction)->fromBundle(
        $customer,
        [$method, $phone],
        [$profile],
        'bundle-test',
    );

    expect($snapshots)->toHaveCount(3)
        ->and(ContactSnapshot::query()->where('reason', 'bundle-test')->count())->toBe(3);

    foreach ($snapshots as $snapshot) {
        expect($snapshot->exists)->toBeTrue()
            ->and($snapshot->snapshotable_id)->toBe($customer->getKey())
            ->and($snapshot->owner_type)->toBe($method->owner_type)
            ->and($snapshot->owner_id)->toBe($method->owner_id);
    }

    expect($snapshots->where('snapshot_type', 'contact_method')->first()?->source_id)->toBe($method->id)
        ->and($snapshots->where('snapshot_type', 'social_profile')->first()?->source_id)->toBe($profile->id)
        ->and((new CreateContactSnapshotAction)->fromBundle($customer, [], [], 'bundle-empty'))->toHaveCount(0);
});

it('indexes snapshot lineage lookups', function (): void {
    $table = config('contacting.database.tables.contact_snapshots', 'contact_snapshots');

    expect(Schema::hasIndex($table, 'contact_snapshots_source_type_source_id_index'))->toBeTrue();
});

it('limits contact link building when asked', function (): void {
    $customer = regressionCustomer('link-limit');

    $customer->addContactMethod(ContactMethodData::email('limit1-' . uniqid() . '@example.com'));
    $customer->addContactMethod(ContactMethodData::email('limit2-' . uniqid() . '@example.com'));

    expect((new BuildContactLinksAction)->forContactable($customer, 1)->links['email'] ?? [])->toHaveCount(1)
        ->and((new BuildContactLinksAction)->forContactable($customer)->links['email'] ?? [])->toHaveCount(2);
});

it('treats snapshots as append-only with action-owned lineage', function (): void {
    $customer = regressionCustomer('immutable');

    $method = $customer->addContactMethod(ContactMethodData::email('locked-' . uniqid() . '@example.com'));
    $snapshot = (new CreateContactSnapshotAction)->fromContactMethod($customer, $method, 'locked');

    expect($snapshot->source_id)->toBe($method->id)
        ->and(fn () => $snapshot->update(['label' => 'changed']))->toThrow(LogicException::class)
        ->and(fn () => $snapshot->delete())->toThrow(LogicException::class);

    $filled = new ContactSnapshot(['source_id' => 'spoofed', 'source_type' => 'spoofed']);

    expect($filled->source_id)->toBeNull()
        ->and($filled->source_type)->toBeNull()
        ->and($filled->getFillable())->not->toContain('source_id', 'source_type');
});

it('wires the normalizer contracts', function (): void {
    expect(app(ContactMethodNormalizer::class))->toBeInstanceOf(NormalizeContactMethodAction::class)
        ->and(app(SocialProfileNormalizer::class))->toBeInstanceOf(NormalizeSocialProfileAction::class);
});

it('leaves parentless primaries undemoted by design', function (): void {
    $first = ContactMethod::query()->create([
        'type' => 'email',
        'purpose' => 'general',
        'value' => 'orphan1-' . uniqid() . '@example.com',
        'is_primary' => true,
    ]);
    $second = ContactMethod::query()->create([
        'type' => 'email',
        'purpose' => 'general',
        'value' => 'orphan2-' . uniqid() . '@example.com',
        'is_primary' => true,
    ]);

    expect($first->fresh()?->is_primary)->toBeTrue()
        ->and($second->fresh()?->is_primary)->toBeTrue();
});

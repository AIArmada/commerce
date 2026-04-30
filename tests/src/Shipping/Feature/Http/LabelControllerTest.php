<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    Schema::dropIfExists('test_shipping_label_owners');

    Schema::create('test_shipping_label_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });
});

it('serves a signed cached label when token, user, and owner payload match', function (): void {
    $user = User::query()->create([
        'name' => 'Label User',
        'email' => 'label-user@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);
    $owner = OwnerContext::resolve();

    $token = 'token-ok-123';

    Cache::put("shipping_label:{$token}", [
        'content' => 'PDF-CONTENT',
        'format' => 'pdf',
        'tracking_number' => 'TRK-OK-1',
        'owner_type' => $owner?->getMorphClass(),
        'owner_id' => $owner?->getKey(),
        'user_id' => $user->getAuthIdentifier(),
    ], now()->addMinutes(5));

    $url = URL::temporarySignedRoute('shipping.labels.show', now()->addMinutes(5), [
        'trackingNumber' => 'TRK-OK-1',
        'token' => $token,
    ]);

    $this->get($url)
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertContent('PDF-CONTENT');
});

it('rejects a signed label request when cache payload user does not match authenticated user', function (): void {
    $userA = User::query()->create([
        'name' => 'Label User A',
        'email' => 'label-user-a@example.com',
        'password' => bcrypt('password'),
    ]);

    $userB = User::query()->create([
        'name' => 'Label User B',
        'email' => 'label-user-b@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($userA);

    $token = 'token-user-mismatch';

    Cache::put("shipping_label:{$token}", [
        'content' => 'PDF-CONTENT',
        'format' => 'pdf',
        'tracking_number' => 'TRK-USER-MISMATCH',
        'owner_type' => null,
        'owner_id' => null,
        'user_id' => $userB->getAuthIdentifier(),
    ], now()->addMinutes(5));

    $url = URL::temporarySignedRoute('shipping.labels.show', now()->addMinutes(5), [
        'trackingNumber' => 'TRK-USER-MISMATCH',
        'token' => $token,
    ]);

    $this->get($url)->assertForbidden();
});

it('rejects a signed label request when owner payload does not match current owner context', function (): void {
    $ownerModel = new class extends Model
    {
        use HasUuids;

        protected $table = 'test_shipping_label_owners';

        protected $fillable = ['name'];
    };

    $ownerModelClass = $ownerModel::class;

    $ownerA = $ownerModelClass::query()->create(['name' => 'Owner A']);
    $ownerB = $ownerModelClass::query()->create(['name' => 'Owner B']);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $user = User::query()->create([
        'name' => 'Owner Label User',
        'email' => 'owner-label-user@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $token = 'token-owner-mismatch';

    Cache::put("shipping_label:{$token}", [
        'content' => 'PDF-CONTENT',
        'format' => 'pdf',
        'tracking_number' => 'TRK-OWNER-MISMATCH',
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
        'user_id' => $user->getAuthIdentifier(),
    ], now()->addMinutes(5));

    $url = URL::temporarySignedRoute('shipping.labels.show', now()->addMinutes(5), [
        'trackingNumber' => 'TRK-OWNER-MISMATCH',
        'token' => $token,
    ]);

    $this->get($url)->assertForbidden();
});

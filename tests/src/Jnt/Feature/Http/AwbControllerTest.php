<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Features\SignedDownloads\Api\SignedDownloadApi;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::dropIfExists('test_jnt_awb_owners');

    Schema::create('test_jnt_awb_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });
});

it('serves a signed cached awb when token, user, and owner payload match', function (): void {
    $user = User::query()->create([
        'name' => 'AWB User',
        'email' => 'jnt-awb-user@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);
    $owner = OwnerContext::resolve();

    $url = app(SignedDownloadApi::class)->issueUrl(
        cachePrefix: 'jnt_awb',
        routeName: 'jnt.awb.show',
        routeParameters: ['orderId' => 'ORD-AWB-1'],
        payload: [
            'content' => 'PDF-CONTENT',
            'format' => 'pdf',
            'order_id' => 'ORD-AWB-1',
        ],
        ttl: 300,
        owner: EloquentRecordReference::fromModel($owner),
        userId: $user->getAuthIdentifier(),
    );

    $this->get($url)
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertContent('PDF-CONTENT');
});

it('rejects a signed awb request when cache payload user does not match authenticated user', function (): void {
    $userA = User::query()->create([
        'name' => 'AWB User A',
        'email' => 'jnt-awb-user-a@example.com',
        'password' => bcrypt('password'),
    ]);

    $userB = User::query()->create([
        'name' => 'AWB User B',
        'email' => 'jnt-awb-user-b@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($userA);

    $url = app(SignedDownloadApi::class)->issueUrl(
        cachePrefix: 'jnt_awb',
        routeName: 'jnt.awb.show',
        routeParameters: ['orderId' => 'ORD-AWB-2'],
        payload: [
            'content' => 'PDF-CONTENT',
            'format' => 'pdf',
            'order_id' => 'ORD-AWB-2',
        ],
        ttl: 300,
        owner: null,
        userId: $userB->getAuthIdentifier(),
    );

    $this->get($url)->assertForbidden();
});

it('rejects a signed awb request when owner payload does not match current owner context', function (): void {
    $ownerModel = new class extends Model
    {
        use HasUuids;

        protected $table = 'test_jnt_awb_owners';

        protected $fillable = ['name'];
    };

    $ownerModelClass = $ownerModel::class;

    $ownerA = $ownerModelClass::query()->create(['name' => 'Owner A']);
    $ownerB = $ownerModelClass::query()->create(['name' => 'Owner B']);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?RecordReference
        {
            return EloquentRecordReference::fromModel($this->owner);
        }
    });

    $user = User::query()->create([
        'name' => 'AWB Owner User',
        'email' => 'jnt-awb-owner-user@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $url = app(SignedDownloadApi::class)->issueUrl(
        cachePrefix: 'jnt_awb',
        routeName: 'jnt.awb.show',
        routeParameters: ['orderId' => 'ORD-AWB-3'],
        payload: [
            'content' => 'PDF-CONTENT',
            'format' => 'pdf',
            'order_id' => 'ORD-AWB-3',
        ],
        ttl: 300,
        owner: EloquentRecordReference::fromModel($ownerB),
        userId: $user->getAuthIdentifier(),
    );

    $this->get($url)->assertForbidden();
});

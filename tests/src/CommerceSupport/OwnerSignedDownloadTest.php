<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Features\SignedDownloads\Api\SignedDownloadApi;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

beforeEach(function (): void {
    if (! Route::has('support.download.test')) {
        Route::get('/_support/download/{resource}', static fn (): string => 'ok')
            ->name('support.download.test');
    }
});

it('issues a temporary signed download url and stores scoped payload metadata', function (): void {
    $owner = User::query()->create([
        'name' => 'Owner One',
        'email' => 'support-owner-one@example.com',
        'password' => 'secret',
    ]);

    $url = app(SignedDownloadApi::class)->issueUrl(
        cachePrefix: 'support_download',
        routeName: 'support.download.test',
        routeParameters: ['resource' => 'resource-1'],
        payload: [
            'resource_id' => 'resource-1',
            'content' => 'payload-content',
            'format' => 'pdf',
        ],
        ttl: 600,
        owner: EloquentRecordReference::fromModel($owner),
        userId: '42',
    );

    $request = Request::create($url, 'GET');
    $payload = app(SignedDownloadApi::class)->payloadFromToken($request->query(SignedDownloadApi::TOKEN_QUERY_PARAM), 'support_download');

    expect($payload)
        ->toBeArray()
        ->and($payload['resource_id'] ?? null)->toBe('resource-1')
        ->and($payload['content'] ?? null)->toBe('payload-content')
        ->and($payload['owner_type'] ?? null)->toBe($owner->getMorphClass())
        ->and((string) ($payload['owner_id'] ?? ''))->toBe((string) $owner->getKey())
        ->and((string) ($payload['user_id'] ?? ''))->toBe('42');
});

it('authorizes payload when resource owner and user match', function (): void {
    $owner = User::query()->create([
        'name' => 'Owner Match',
        'email' => 'support-owner-match@example.com',
        'password' => 'secret',
    ]);

    $url = app(SignedDownloadApi::class)->issueUrl(
        cachePrefix: 'support_download_auth',
        routeName: 'support.download.test',
        routeParameters: ['resource' => 'resource-auth'],
        payload: [
            'resource_id' => 'resource-auth',
            'content' => 'payload-content',
            'format' => 'pdf',
        ],
        ttl: 600,
        owner: EloquentRecordReference::fromModel($owner),
        userId: '7',
    );

    $payload = app(SignedDownloadApi::class)->payloadFromToken(
        Request::create($url, 'GET')->query(SignedDownloadApi::TOKEN_QUERY_PARAM),
        'support_download_auth',
    );

    expect($payload)->toBeArray();

    expect(app(SignedDownloadApi::class)->isAuthorizedPayload(
        payload: $payload,
        resourceIdKey: 'resource_id',
        expectedResourceId: 'resource-auth',
        owner: EloquentRecordReference::fromModel($owner),
        userId: '7',
    ))->toBeTrue();
});

it('rejects payload when owner or user do not match', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'support-owner-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'support-owner-b@example.com',
        'password' => 'secret',
    ]);

    $url = app(SignedDownloadApi::class)->issueUrl(
        cachePrefix: 'support_download_auth_fail',
        routeName: 'support.download.test',
        routeParameters: ['resource' => 'resource-fail'],
        payload: [
            'resource_id' => 'resource-fail',
            'content' => 'payload-content',
            'format' => 'pdf',
        ],
        ttl: 600,
        owner: EloquentRecordReference::fromModel($ownerA),
        userId: '77',
    );

    $payload = app(SignedDownloadApi::class)->payloadFromToken(
        Request::create($url, 'GET')->query(SignedDownloadApi::TOKEN_QUERY_PARAM),
        'support_download_auth_fail',
    );

    expect($payload)->toBeArray();

    expect(app(SignedDownloadApi::class)->isAuthorizedPayload(
        payload: $payload,
        resourceIdKey: 'resource_id',
        expectedResourceId: 'resource-fail',
        owner: EloquentRecordReference::fromModel($ownerB),
        userId: '77',
    ))->toBeFalse()
        ->and(app(SignedDownloadApi::class)->isAuthorizedPayload(
            payload: $payload,
            resourceIdKey: 'resource_id',
            expectedResourceId: 'resource-fail',
            owner: EloquentRecordReference::fromModel($ownerA),
            userId: '78',
        ))->toBeFalse();
});

it('rejects malformed resource identifiers and user ids', function (): void {
    $payload = [
        'resource_id' => ['not-a-scalar'],
        'owner_type' => null,
        'owner_id' => null,
        'user_id' => '7',
    ];

    expect(app(SignedDownloadApi::class)->isAuthorizedPayload($payload, 'resource_id', 'resource-x', null, '7'))->toBeFalse()
        ->and(app(SignedDownloadApi::class)->isAuthorizedPayload(
            ['resource_id' => 'resource-x', 'user_id' => ['array'], 'owner_type' => null, 'owner_id' => null],
            'resource_id',
            'resource-x',
            null,
            '7',
        ))->toBeFalse()
        ->and(app(SignedDownloadApi::class)->isAuthorizedPayload(
            ['resource_id' => 'resource-x', 'user_id' => '', 'owner_type' => null, 'owner_id' => null],
            'resource_id',
            'resource-x',
            null,
            '7',
        ))->toBeFalse()
        ->and(app(SignedDownloadApi::class)->isAuthorizedPayload(
            ['resource_id' => 'resource-x', 'user_id' => '7', 'owner_type' => null, 'owner_id' => null],
            'resource_id',
            '',
            null,
            '7',
        ))->toBeFalse();
});

it('allows global payloads with a null owner when owner mode is disabled', function (): void {
    config()->set('commerce-support.owner.enabled', false);

    $payload = [
        'resource_id' => 'resource-global',
        'owner_type' => null,
        'owner_id' => null,
        'user_id' => '7',
    ];

    expect(app(SignedDownloadApi::class)->isAuthorizedPayload($payload, 'resource_id', 'resource-global', null, '7'))->toBeTrue();
});

it('requires explicit global context for global payloads when owner mode is enabled', function (): void {
    config()->set('commerce-support.owner.enabled', true);

    $payload = [
        'resource_id' => 'resource-global-mode',
        'owner_type' => null,
        'owner_id' => null,
        'user_id' => '7',
    ];

    expect(app(SignedDownloadApi::class)->isAuthorizedPayload($payload, 'resource_id', 'resource-global-mode', null, '7'))->toBeFalse();

    $inExplicitGlobal = OwnerContext::withOwner(null, fn (): bool => app(SignedDownloadApi::class)->isAuthorizedPayload(
        $payload,
        'resource_id',
        'resource-global-mode',
        null,
        '7',
    ));

    expect($inExplicitGlobal)->toBeTrue();
});

it('rejects malformed owner metadata instead of treating it as global', function (): void {
    config()->set('commerce-support.owner.enabled', false);

    expect(app(SignedDownloadApi::class)->isAuthorizedPayload(
        ['resource_id' => 'resource-x', 'user_id' => '7', 'owner_type' => ['invalid'], 'owner_id' => null],
        'resource_id',
        'resource-x',
        null,
        '7',
    ))->toBeFalse()
        ->and(app(SignedDownloadApi::class)->isAuthorizedPayload(
            ['resource_id' => 'resource-x', 'user_id' => '7', 'owner_type' => 'user', 'owner_id' => ['invalid']],
            'resource_id',
            'resource-x',
            null,
            '7',
        ))->toBeFalse();
});

it('rejects empty or whitespace cache prefixes when issuing and looking up tokens', function (): void {
    $owner = User::query()->create([
        'name' => 'Prefix Owner',
        'email' => 'support-prefix-owner@example.com',
        'password' => 'secret',
    ]);

    $api = app(SignedDownloadApi::class);

    foreach (['', ' ', 'has space', "has\ttab"] as $prefix) {
        expect(fn () => $api->issueUrl(
            cachePrefix: $prefix,
            routeName: 'support.download.test',
            routeParameters: ['resource' => 'resource-prefix'],
            payload: ['resource_id' => 'resource-prefix'],
            ttl: 600,
            owner: EloquentRecordReference::fromModel($owner),
            userId: '1',
        ))->toThrow(InvalidArgumentException::class, 'Invalid signed download cache prefix.');

        expect($api->payloadFromToken(str_repeat('a', 64), $prefix))->toBeNull();
    }
});

it('issues an unpredictable 64-character hex token even when random string generation is overridden', function (): void {
    $owner = User::query()->create([
        'name' => 'Entropy Owner',
        'email' => 'support-entropy-owner@example.com',
        'password' => 'secret',
    ]);

    Str::createRandomStringsUsing(static fn (int $length = 0): string => str_repeat('Z', max(1, $length)));

    try {
        $url = app(SignedDownloadApi::class)->issueUrl(
            cachePrefix: 'support_download_entropy',
            routeName: 'support.download.test',
            routeParameters: ['resource' => 'resource-entropy'],
            payload: ['resource_id' => 'resource-entropy'],
            ttl: 600,
            owner: EloquentRecordReference::fromModel($owner),
            userId: '1',
        );
    } finally {
        Str::createRandomStringsNormally();
    }

    $token = Request::create($url, 'GET')->query(SignedDownloadApi::TOKEN_QUERY_PARAM);

    expect($token)->toBeString()
        ->and($token)->toMatch('/^[0-9a-f]{64}$/');

    expect(app(SignedDownloadApi::class)->payloadFromToken($token, 'support_download_entropy'))->toBeArray();
});

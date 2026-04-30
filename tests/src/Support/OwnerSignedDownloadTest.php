<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerSignedDownload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

    $url = OwnerSignedDownload::issueUrl(
        cachePrefix: 'support_download',
        routeName: 'support.download.test',
        routeParameters: ['resource' => 'resource-1'],
        payload: [
            'resource_id' => 'resource-1',
            'content' => 'payload-content',
            'format' => 'pdf',
        ],
        ttl: 600,
        owner: $owner,
        userId: '42',
    );

    $request = Request::create($url, 'GET');
    $payload = OwnerSignedDownload::payloadFromRequestToken($request, 'support_download');

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

    $url = OwnerSignedDownload::issueUrl(
        cachePrefix: 'support_download_auth',
        routeName: 'support.download.test',
        routeParameters: ['resource' => 'resource-auth'],
        payload: [
            'resource_id' => 'resource-auth',
            'content' => 'payload-content',
            'format' => 'pdf',
        ],
        ttl: 600,
        owner: $owner,
        userId: '7',
    );

    $payload = OwnerSignedDownload::payloadFromRequestToken(
        Request::create($url, 'GET'),
        'support_download_auth',
    );

    expect($payload)->toBeArray();

    expect(OwnerSignedDownload::isAuthorizedPayload(
        payload: $payload,
        resourceIdKey: 'resource_id',
        expectedResourceId: 'resource-auth',
        owner: $owner,
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

    $url = OwnerSignedDownload::issueUrl(
        cachePrefix: 'support_download_auth_fail',
        routeName: 'support.download.test',
        routeParameters: ['resource' => 'resource-fail'],
        payload: [
            'resource_id' => 'resource-fail',
            'content' => 'payload-content',
            'format' => 'pdf',
        ],
        ttl: 600,
        owner: $ownerA,
        userId: '77',
    );

    $payload = OwnerSignedDownload::payloadFromRequestToken(
        Request::create($url, 'GET'),
        'support_download_auth_fail',
    );

    expect($payload)->toBeArray();

    expect(OwnerSignedDownload::isAuthorizedPayload(
        payload: $payload,
        resourceIdKey: 'resource_id',
        expectedResourceId: 'resource-fail',
        owner: $ownerB,
        userId: '77',
    ))->toBeFalse()
        ->and(OwnerSignedDownload::isAuthorizedPayload(
            payload: $payload,
            resourceIdKey: 'resource_id',
            expectedResourceId: 'resource-fail',
            owner: $ownerA,
            userId: '78',
        ))->toBeFalse();
});

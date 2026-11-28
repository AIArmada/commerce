<?php

declare(strict_types=1);

use AIArmada\Docs\Contracts\DocumentSharing;
use AIArmada\Docs\Data\ShareLinkData;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocShareLink;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('never persists the plain token on the share link model', function (): void {
    $doc = Doc::factory()->create();
    $issued = app(DocumentSharing::class)->issue((string) $doc->getKey(), new ShareLinkData);
    $link = DocShareLink::query()->findOrFail($issued->id);
    $token = $issued->plainToken();

    expect($link->token_hash)->toBe(hash('sha256', $token))
        ->and($link->toArray())->not->toHaveKey('plainToken')
        ->and($link->toArray())->not->toHaveKey('plain_token')
        ->and($link->toJson())->not->toContain($token)
        ->and(serialize($link))->not->toContain($token)
        ->and(json_encode($issued))->not->toContain($token);
});

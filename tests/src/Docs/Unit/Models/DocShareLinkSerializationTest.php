<?php

declare(strict_types=1);

use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocShareLink;
use Illuminate\Foundation\Testing\RefreshDatabase;

\uses(RefreshDatabase::class);

it('does not leak plainToken through toArray or toJson', function (): void {
    $doc = Doc::factory()->create();
    $link = new DocShareLink;
    $link->forceFill([
        'doc_id' => $doc->id,
        'token_hash' => hash('sha256', 'secret-token'),
        'allowed_actions' => ['view'],
    ]);
    $link->save();
    $link->setPlainToken('secret-token');

    $array = $link->toArray();
    expect($array)->not->toHaveKey('plainToken');
    expect($array)->not->toHaveKey('plain_token');

    $json = $link->toJson();
    expect($json)->not->toContain('plainToken');
    expect($json)->not->toContain('plain_token');
    expect($json)->not->toContain('secret-token');
});

it('does not leak plainToken through serialization when freshly created', function (): void {
    $doc = Doc::factory()->create();
    $link = new DocShareLink;
    $link->forceFill([
        'doc_id' => $doc->id,
        'token_hash' => hash('sha256', 'another-token'),
        'allowed_actions' => ['edit'],
    ]);
    $link->save();
    $link->setPlainToken('another-token');

    $serialized = serialize($link);
    expect($serialized)->not->toContain('another-token');
});

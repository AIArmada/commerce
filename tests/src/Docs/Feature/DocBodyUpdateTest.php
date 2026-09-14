<?php

declare(strict_types=1);

use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Services\DocService;

it('stores a rich content array body unchanged on update', function (): void {
    $doc = Doc::factory()->create();

    $body = [
        'type' => 'doc',
        'content' => [
            [
                'type' => 'paragraph',
                'content' => [
                    ['type' => 'text', 'text' => 'Hello'],
                ],
            ],
        ],
    ];

    $updated = app(DocService::class)->update($doc, ['body' => $body]);

    expect($updated->fresh()->body)->toBe($body);
});

it('normalizes an html string body to the stored array shape on update', function (): void {
    $doc = Doc::factory()->create();

    $updated = app(DocService::class)->update($doc, ['body' => '<p>Hello</p>']);

    $stored = $updated->fresh()->body;

    expect($stored)->toBeArray()
        ->and($stored['type'])->toBe('doc')
        ->and(data_get($stored, 'content.0.content.0.text'))->toBe('<p>Hello</p>')
        ->and(data_get($stored, 'content.0.content.0.type'))->toBe('text');
});

it('treats a blank string body as empty on update', function (): void {
    $doc = Doc::factory()->create([
        'body' => ['type' => 'doc', 'content' => []],
    ]);

    $updated = app(DocService::class)->update($doc, ['body' => '   ']);

    expect($updated->fresh()->body)->toBeNull();
});

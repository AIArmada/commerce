<?php

declare(strict_types=1);

use AIArmada\Docs\Contracts\DocumentEmailTemplates;
use AIArmada\Docs\Features\Delivery\Models\DocEmailTemplate;
use Illuminate\Validation\ValidationException;

it('manages email template mutations without accepting owner fields', function (): void {
    $templates = app(DocumentEmailTemplates::class);
    $id = $templates->create([
        'name' => str_repeat('a', 255),
        'slug' => str_repeat('b', 255),
        'doc_type' => 'invoice',
        'trigger' => 'send',
        'subject' => 'Hello',
        'body' => 'Body',
        'owner_type' => 'invalid',
        'owner_id' => 'invalid',
    ]);

    $templates->update($id, ['subject' => 'Updated']);
    $first = DocEmailTemplate::query()->findOrFail($templates->duplicate($id));
    $second = DocEmailTemplate::query()->findOrFail($templates->duplicate($id));

    expect($first->subject)->toBe('Updated')
        ->and($first->owner_id)->toBeNull()
        ->and(mb_strlen($first->slug))->toBeLessThanOrEqual(255)
        ->and(mb_strlen($first->name))->toBeLessThanOrEqual(255)
        ->and($second->slug)->not->toBe($first->slug);

    expect(fn () => $templates->update($id, ['trigger' => 'invalid']))
        ->toThrow(ValidationException::class);

    $templates->remove([$id, (string) $first->getKey(), (string) $second->getKey()]);
    expect(DocEmailTemplate::query()->whereKey($id)->exists())->toBeFalse();
});

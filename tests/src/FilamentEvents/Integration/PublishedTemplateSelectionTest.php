<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\EventTemplateService;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventTemplate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

it('lists and creates events only from published event templates in the current owner scope', function (): void {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();

    $foreign = OwnerContext::withOwner($outsider, fn (): EventTemplate => EventTemplate::factory()->create([
        'name' => 'Template foreign', 'status' => 'published',
    ]));

    OwnerContext::withOwner($owner, function () use ($owner, $outsider, $foreign): void {
        $published = EventTemplate::factory()->create(['name' => 'Template published', 'status' => 'published']);
        $draft = EventTemplate::factory()->create(['name' => 'Template draft']);
        $occurrence = EventTemplate::factory()->create(['name' => 'Template occurrence', 'template_type' => 'occurrence', 'status' => 'published']);

        $service = app(EventTemplateService::class);

        expect(array_keys($service->publishedEventOptions('Template')))->toBe([$published->id])
            ->and($service->publishedEventLabel($foreign->id))->toBeNull()
            ->and($service->publishedEventLabel($draft->id))->toBeNull();

        foreach ([$foreign, $draft, $occurrence] as $blocked) {
            expect(fn () => $service->createFromPublishedEventTemplate($blocked->id, $owner))
                ->toThrow(ValidationException::class);
        }

        expect(fn () => $service->createFromPublishedEventTemplate($published->id, $outsider))
            ->toThrow(AuthorizationException::class);

        $event = Event::query()->findOrFail($service->createFromPublishedEventTemplate($published->id, $owner));

        expect($event->owner_id)->toBe((string) $owner->getKey())
            ->and($event->title)->toBe('Template published');
    });
});

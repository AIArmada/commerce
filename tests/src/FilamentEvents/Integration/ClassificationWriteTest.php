<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Taxonomy\Actions\ManageEventClassificationAction;
use AIArmada\Events\Features\Taxonomy\Models\EventTaxonomy;
use AIArmada\Events\Features\Taxonomy\Models\EventTerm;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

it('validates classification terms and actor permissions before saving', function (): void {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();

    OwnerContext::withOwner($owner, function () use ($owner, $outsider): void {
        $event = Event::factory()->create();
        $taxonomy = EventTaxonomy::factory()->create();
        $otherTaxonomy = EventTaxonomy::factory()->create();
        $term = EventTerm::factory()->create(['event_taxonomy_id' => $taxonomy->id]);
        $data = ['event_taxonomy_id' => $taxonomy->id, 'event_term_id' => $term->id, 'is_primary' => true];
        $action = app(ManageEventClassificationAction::class);

        expect(fn () => $action->save($event->id, null, $data, $outsider))->toThrow(AuthorizationException::class);
        expect(fn () => $action->save($event->id, null, [...$data, 'event_taxonomy_id' => $otherTaxonomy->id], $owner))->toThrow(ValidationException::class);

        $classification = $action->save($event->id, null, $data, $owner);

        expect($classification->event_id)->toBe($event->id)
            ->and($classification->taxonomy_code)->toBe($taxonomy->code)
            ->and($classification->term_code)->toBe($term->code);

        expect($action->delete($event->id, $classification->id, $owner))->toBeTrue();
    });
});

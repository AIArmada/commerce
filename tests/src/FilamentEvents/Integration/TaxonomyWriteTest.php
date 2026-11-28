<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Events\Features\Taxonomy\Actions\ManageEventTaxonomyAction;
use AIArmada\Events\Features\Taxonomy\Actions\ManageEventTermAction;
use AIArmada\Events\Features\Taxonomy\Models\EventTaxonomy;
use Illuminate\Validation\ValidationException;

it('prevents cross-taxonomy parents, cycles, and flattening a populated hierarchy', function (): void {
    $actor = User::factory()->create();
    \Illuminate\Support\Facades\Gate::define('manageSharedEventCatalog', fn (User $user): bool => $user->is($actor));
    $taxonomy = EventTaxonomy::factory()->create(['is_hierarchical' => true]);
    $other = EventTaxonomy::factory()->create(['is_hierarchical' => true]);
    $action = app(ManageEventTermAction::class);
    $root = $action->save($taxonomy->id, null, ['name' => 'Root', 'code' => 'root'], $actor);
    $child = $action->save($taxonomy->id, null, ['name' => 'Child', 'code' => 'child', 'parent_id' => $root->id], $actor);

    expect($child->parent_id)->toBe($root->id);
    expect(fn () => $action->save($other->id, null, ['name' => 'Wrong', 'code' => 'wrong', 'parent_id' => $root->id], $actor))->toThrow(ValidationException::class);
    expect(fn () => $action->save($taxonomy->id, $root->id, ['parent_id' => $child->id], $actor))->toThrow(ValidationException::class);
    expect(fn () => app(ManageEventTaxonomyAction::class)->save($taxonomy->id, ['is_hierarchical' => false], $actor))->toThrow(ValidationException::class);
    expect($root->refresh()->parent_id)->toBeNull();
});

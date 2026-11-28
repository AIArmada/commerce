<?php

declare(strict_types=1);

use AIArmada\Engagement\Enums\BookmarkStatus;
use AIArmada\Engagement\Features\Interactions\Actions\ManageInteractionsAction;
use AIArmada\Engagement\Features\Interactions\Models\Bookmark;

beforeEach(function (): void {
    $this->manager = app(ManageInteractionsAction::class);
    $this->actor = engagementActor();
    $this->subject = engagementSubject();
});

it('creates a bookmark', function (): void {
    $bookmark = $this->manager->bookmark($this->actor, $this->subject);

    expect($bookmark->status)->toBe(BookmarkStatus::Active);
});

it('removes bookmark via status', function (): void {
    $this->manager->bookmark($this->actor, $this->subject);
    $this->manager->removeBookmark($this->actor, $this->subject);

    expect(Bookmark::query()->where('status', 'removed')->count())->toBe(1);
});

it('clears archive timestamps when restoring a bookmark', function (): void {
    $bookmark = $this->manager->bookmark($this->actor, $this->subject);
    $this->manager->archiveBookmark($this->actor, $this->subject);

    expect($bookmark->fresh()->archived_at)->not->toBeNull();

    $restored = $this->manager->bookmark($this->actor, $this->subject);

    expect($restored->is($bookmark))->toBeTrue()
        ->and($restored->status)->toBe(BookmarkStatus::Active)
        ->and($restored->archived_at)->toBeNull();
});

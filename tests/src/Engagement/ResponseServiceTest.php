<?php

declare(strict_types=1);

use AIArmada\Engagement\Enums\ResponseStatus;
use AIArmada\Engagement\Features\Interactions\Actions\ManageInteractionsAction;
use AIArmada\Engagement\Features\Interactions\Models\Response;

beforeEach(function (): void {
    $this->manager = app(ManageInteractionsAction::class);
    $this->actor = engagementActor();
    $this->subject = engagementSubject();
});

it('creates a response', function (): void {
    $response = $this->manager->respond($this->actor, $this->subject, 'interested');

    expect($response->response_type)->toBe('interested');
});

it('changes response', function (): void {
    $this->manager->respond($this->actor, $this->subject, 'interested');
    $this->manager->respond($this->actor, $this->subject, 'going');

    $response = Response::query()->where('status', 'active')->first();
    expect($response->response_type)->toBe('going');
});

it('restores a cancelled response without creating a duplicate', function (): void {
    $this->manager->respond($this->actor, $this->subject, 'interested');
    $this->manager->cancelResponse($this->actor, $this->subject);
    $restored = $this->manager->respond($this->actor, $this->subject, 'going');

    expect($restored->status)->toBe(ResponseStatus::Active)
        ->and($restored->cancelled_at)->toBeNull()
        ->and(Response::query()->count())->toBe(1);
});

it('honors explicit response visibility changes and preserves privacy when omitted', function (): void {
    $response = $this->manager->respond($this->actor, $this->subject, 'interested', ['visibility' => 'public']);
    $updated = $this->manager->respond($this->actor, $this->subject, 'interested', ['visibility' => 'private']);
    $changed = $this->manager->respond($this->actor, $this->subject, 'going');

    expect($updated->is($response))->toBeTrue()
        ->and($updated->visibility)->toBe('private')
        ->and($changed->visibility)->toBe('private');

    $this->manager->cancelResponse($this->actor, $this->subject);
    $restored = $this->manager->respond($this->actor, $this->subject, 'going', ['visibility' => 'public']);

    expect($restored->visibility)->toBe('public')
        ->and(Response::query()->count())->toBe(1);
});

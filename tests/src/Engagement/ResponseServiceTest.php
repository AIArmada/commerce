<?php

declare(strict_types=1);

use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\Engagement\Enums\ResponseStatus;
use AIArmada\Engagement\Models\Response;
use AIArmada\Engagement\Tests\Fixtures\EngagementActor;
use AIArmada\Engagement\Tests\Fixtures\EngagementSubject;

beforeEach(function (): void {
    $this->manager = app(EngagementManager::class);
    $this->actor = new EngagementActor;
    $this->subject = new EngagementSubject;
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

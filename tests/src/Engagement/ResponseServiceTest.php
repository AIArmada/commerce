<?php

declare(strict_types=1);

use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\Engagement\Contracts\Respondable;
use AIArmada\Engagement\Models\Response;

beforeEach(function () {
    $this->manager = app(EngagementManager::class);
    $this->actor = new class
    {
        public function getMorphClass(): string { return 'user'; }
        public function getKey(): string { return 'user-1'; }
    };
    $this->subject = new class implements Respondable
    {
        public function allowedResponseTypes(): array { return ['interested', 'going', 'maybe']; }
        public function defaultResponseVisibility(): string { return 'public'; }
        public function allowsMultipleResponsesFromSameResponder(): bool { return false; }
        public function getMorphClass(): string { return 'event_occurrence'; }
        public function getKey(): string { return 'occ-1'; }
    };
});

it('creates a response', function () {
    $response = $this->manager->respond($this->actor, $this->subject, 'interested');

    expect($response->response_type)->toBe('interested');
});

it('changes response', function () {
    $this->manager->respond($this->actor, $this->subject, 'interested');
    $this->manager->respond($this->actor, $this->subject, 'going');

    $response = Response::query()->where('status', 'active')->first();
    expect($response->response_type)->toBe('going');
});

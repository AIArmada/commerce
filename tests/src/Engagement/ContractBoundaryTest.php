<?php

declare(strict_types=1);

use AIArmada\Engagement\Contracts\CanInteract;
use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\Engagement\Tests\Fixtures\EngagementActor;
use AIArmada\Engagement\Tests\Fixtures\EngagementSubject;
use Illuminate\Database\Eloquent\Model;

it('rejects non-marker models at the manager signature', function (): void {
    $manager = app(EngagementManager::class);
    $actor = new EngagementActor;
    $subject = new EngagementSubject;
    $nonEngageable = new class extends Model {};

    expect(fn (): mixed => $manager->follow($nonEngageable, $subject))
        ->toThrow(TypeError::class)
        ->and(fn (): mixed => $manager->respond($actor, $nonEngageable, 'interested'))
        ->toThrow(TypeError::class);
});

it('rejects marker objects that are not Eloquent models at the boundary', function (): void {
    $manager = app(EngagementManager::class);
    $actor = new class implements CanInteract
    {
        public function interactionDisplayName(): string
        {
            return 'Invalid actor';
        }

        public function interactionNotificationRoute(?string $channel = null): mixed
        {
            return null;
        }
    };

    expect(fn (): mixed => $manager->follow($actor, new EngagementSubject))
        ->toThrow(InvalidArgumentException::class);
});

<?php

declare(strict_types=1);

use AIArmada\Engagement\Contracts\CanInteract;
use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\Engagement\Contracts\Followable;
use AIArmada\Engagement\Traits\CanFollow;
use AIArmada\Engagement\Traits\HasFollowers;
use Illuminate\Database\Eloquent\Model;

it('keeps actor and subject trait calls equivalent to manager calls', function (): void {
    $actor = new class extends Model implements CanInteract
    {
        use CanFollow;

        public function interactionDisplayName(): string
        {
            return 'Trait actor';
        }

        public function interactionNotificationRoute(?string $channel = null): mixed
        {
            return null;
        }
    };
    $subject = new class extends Model implements Followable
    {
        use HasFollowers;

        public function followableName(): string
        {
            return 'Trait subject';
        }

        public function followableUrl(): ?string
        {
            return null;
        }

        public function followableImage(): ?string
        {
            return null;
        }

        public function defaultFollowNotificationLevel(): ?string
        {
            return 'all';
        }
    };

    $actor->setAttribute('id', 'trait-actor');
    $subject->setAttribute('id', 'trait-subject');

    $manager = app(EngagementManager::class);
    $viaTrait = $actor->follow($subject);
    $viaManager = $manager->follow($actor, $subject);

    expect($viaManager->getKey())->toBe($viaTrait->getKey())
        ->and($subject->followersCount())->toBe(1);
});

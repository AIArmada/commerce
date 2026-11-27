<?php

declare(strict_types=1);

use AIArmada\Engagement\Contracts\CanInteract;
use AIArmada\Engagement\Features\Interactions\Actions\ManageInteractionsAction;
use AIArmada\Engagement\Tests\Fixtures\EngagementActor;
use AIArmada\Engagement\Tests\Fixtures\EngagementSubject;
use AIArmada\Events\Contracts\EventEngagementManager;
use Illuminate\Database\Eloquent\Model;

it('rejects non-marker models at the manager signature', function (): void {
    $manager = app(ManageInteractionsAction::class);
    $actor = new EngagementActor;
    $subject = new EngagementSubject;
    $nonEngageable = new class extends Model {};

    expect(fn (): mixed => $manager->follow($nonEngageable, $subject))
        ->toThrow(TypeError::class)
        ->and(fn (): mixed => $manager->respond($actor, $nonEngageable, 'interested'))
        ->toThrow(TypeError::class);
});

it('rejects marker objects that are not Eloquent models at the boundary', function (): void {
    $manager = app(ManageInteractionsAction::class);
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

it('rejects non-model state inputs at the events adapter boundary', function (): void {
    $adapter = app(EventEngagementManager::class);

    expect(fn (): array => $adapter->stateFor(new EngagementActor, 'not-a-model'))
        ->toThrow(InvalidArgumentException::class);
});

it('returns detached records from the event engagement integration', function (): void {
    $adapter = app(EventEngagementManager::class);
    $actor = new EngagementActor;
    $subject = new EngagementSubject;

    $follow = $adapter->follow($actor, $subject);
    $repeat = $adapter->follow($actor, $subject);
    $bookmark = $adapter->bookmark($actor, $subject);
    $response = $adapter->respond($actor, $subject, 'going');
    $subscription = $adapter->subscribe($actor, $subject);
    $reminder = $adapter->remind($actor, $subject, [
        'reminder_type' => 'before_start',
        'remind_at' => now()->addHour(),
    ]);
    $share = $adapter->share($actor, $subject);

    expect($follow)->toBeArray()
        ->and($repeat['id'])->toBe($follow['id'])
        ->and($bookmark['id'])->toBeString()
        ->and($response['response_type'])->toBe('going')
        ->and($subscription['id'])->toBeString()
        ->and($reminder['id'])->toBeString()
        ->and($share['share_url'])->toBeString()
        ->and($adapter->stateFor($actor, $subject)['is_following'])->toBeTrue();
});

it('rejects interactions with missing actor or subject identities', function (): void {
    $manager = app(ManageInteractionsAction::class);
    $actor = new EngagementActor;
    $subject = new EngagementSubject;
    $actor->setAttribute($actor->getKeyName(), null);

    expect(fn () => $manager->follow($actor, $subject))->toThrow(InvalidArgumentException::class);

    $actor = new EngagementActor;
    $subject->setAttribute($subject->getKeyName(), '');

    expect(fn () => $manager->bookmark($actor, $subject))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $manager->share($actor, $subject))->toThrow(InvalidArgumentException::class);
});

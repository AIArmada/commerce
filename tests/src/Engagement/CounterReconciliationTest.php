<?php

declare(strict_types=1);

use AIArmada\Engagement\Contracts\EngagementCounterService;
use AIArmada\Engagement\Enums\ReactionStatus;
use AIArmada\Engagement\Enums\ResponseStatus;
use AIArmada\Engagement\Models\EngagementCounter;
use AIArmada\Engagement\Models\Reaction;
use AIArmada\Engagement\Models\Response;
use AIArmada\Engagement\Tests\Fixtures\EngagementActor;
use AIArmada\Engagement\Tests\Fixtures\EngagementSubject;

it('reconciles keyed response and reaction counters after direct writes', function (): void {
    $actor = new EngagementActor;
    $subject = new EngagementSubject;

    Response::query()->create([
        'responder_type' => $actor->getMorphClass(),
        'responder_id' => $actor->getKey(),
        'respondable_type' => $subject->getMorphClass(),
        'respondable_id' => $subject->getKey(),
        'response_type' => 'interested',
        'status' => ResponseStatus::Active,
        'visibility' => 'public',
    ]);
    Reaction::query()->create([
        'reactor_type' => $actor->getMorphClass(),
        'reactor_id' => $actor->getKey(),
        'reactable_type' => $subject->getMorphClass(),
        'reactable_id' => $subject->getKey(),
        'reaction_type' => 'love',
        'status' => ReactionStatus::Active,
    ]);
    EngagementCounter::query()->create([
        'subject_type' => $subject->getMorphClass(),
        'subject_id' => $subject->getKey(),
        'counter_type' => 'responses',
        'counter_key' => 'stale',
        'count_value' => 9,
    ]);

    app(EngagementCounterService::class)->recalculate($subject);

    $counters = app(EngagementCounterService::class);

    expect($counters->value($subject, 'responses'))->toBe(1)
        ->and($counters->value($subject, 'responses', 'interested'))->toBe(1)
        ->and($counters->value($subject, 'responses', 'stale'))->toBe(0)
        ->and($counters->value($subject, 'reactions'))->toBe(1)
        ->and($counters->value($subject, 'reactions', 'love'))->toBe(1);
});

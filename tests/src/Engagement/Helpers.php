<?php

declare(strict_types=1);

use AIArmada\Engagement\Tests\Fixtures\EngagementActor;
use AIArmada\Engagement\Tests\Fixtures\EngagementContextSubject;
use AIArmada\Engagement\Tests\Fixtures\EngagementSubject;

function engagementActor(array $attributes = []): EngagementActor
{
    return EngagementActor::query()->create(array_merge([
        'name' => 'Engagement actor',
        'email' => 'engagement-actor-' . uniqid() . '@example.com',
        'password' => 'secret',
    ], $attributes));
}

function engagementSubject(array $attributes = []): EngagementSubject
{
    return EngagementSubject::query()->create(array_merge([
        'name' => 'Engagement subject',
        'email' => 'engagement-subject-' . uniqid() . '@example.com',
        'password' => 'secret',
    ], $attributes));
}

function engagementContextSubject(array $attributes = []): EngagementContextSubject
{
    return EngagementContextSubject::query()->create(array_merge([
        'name' => 'Engagement context subject',
        'email' => 'engagement-context-' . uniqid() . '@example.com',
        'password' => 'secret',
    ], $attributes));
}

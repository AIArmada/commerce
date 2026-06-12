<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;

/**
 * Create a test actor for engagement operations.
 */
function createEngagementActor(): User
{
    return User::query()->create([
        'name' => 'Engagement Actor',
        'email' => 'engagement-actor-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
}

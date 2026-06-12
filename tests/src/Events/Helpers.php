<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;

/**
 * Create a test event owner.
 */
function createEventOwner(): User
{
    return User::query()->create([
        'name' => 'Event Owner',
        'email' => 'event-owner-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
}

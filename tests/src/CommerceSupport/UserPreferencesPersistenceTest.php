<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Features\UserPreferences\Actions\DeleteNotificationPreference;
use AIArmada\CommerceSupport\Features\UserPreferences\Actions\DeleteSavedSearch;
use AIArmada\CommerceSupport\Features\UserPreferences\Actions\UpdateSavedSearch;

it('rejects unpersisted users during saved search and notification preference mutations', function (): void {
    $user = new User([
        'name' => 'Draft User',
        'email' => 'draft-user@example.com',
        'password' => 'secret',
    ]);

    expect($user->exists)->toBeFalse();

    expect(fn () => (new UpdateSavedSearch)->execute($user, 'search-id', ['name' => 'x']))
        ->toThrow(InvalidArgumentException::class, 'User must be persisted.');

    expect(fn () => (new DeleteSavedSearch)->execute($user, 'search-id'))
        ->toThrow(InvalidArgumentException::class, 'User must be persisted.');

    expect(fn () => (new DeleteNotificationPreference)->execute($user, 'preference-id'))
        ->toThrow(InvalidArgumentException::class, 'User must be persisted.');
});

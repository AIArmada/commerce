<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Contracts\DestinationResolver;
use AIArmada\Communications\Features\RecipientRouting\Actions\SaveRecipientDestination;
use AIArmada\Communications\Features\RecipientRouting\Actions\SaveRecipientPreference;
use Illuminate\Auth\Access\AuthorizationException;

it('routes to verified destinations ahead of unverified ones and protects recipient identity', function (): void {
    $recipient = User::factory()->create();
    $other = User::factory()->create();

    OwnerContext::withOwner(null, function () use ($recipient, $other): void {
        $action = app(SaveRecipientDestination::class);
        $verified = $action->create($recipient, [
            'channel' => 'mail',
            'address' => 'verified@example.com',
            'verified_at' => now(),
            'is_primary' => true,
            'recipient_id' => $other->getKey(),
        ]);

        $action->create($recipient, [
            'channel' => 'mail',
            'address' => 'unverified@example.com',
            'is_primary' => true,
        ]);

        expect($verified->recipient_id)->toBe($recipient->getKey())
            ->and(app(DestinationResolver::class)->resolve($recipient, 'mail')?->destination)->toBe('verified@example.com');

        expect(fn () => $action->update($other, $verified, ['address' => 'forged@example.com']))
            ->toThrow(AuthorizationException::class);

        $preference = app(SaveRecipientPreference::class)->create($recipient, ['channel' => 'mail']);
        expect(fn () => app(SaveRecipientPreference::class)->update($other, $preference, ['category' => 'marketing']))
            ->toThrow(AuthorizationException::class);
    });
});

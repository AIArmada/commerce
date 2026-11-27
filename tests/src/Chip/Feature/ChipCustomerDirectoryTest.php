<?php

declare(strict_types=1);

use AIArmada\Chip\Contracts\ChipCustomerDirectoryInterface;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use Illuminate\Auth\Access\AuthorizationException;

describe('ChipCustomerDirectory', function (): void {
    it('links a subject to a chip customer and scopes lookup by owner', function (): void {
        config()->set('chip.owner.enabled', true);
        config()->set('chip.owner.include_global', false);

        $ownerOne = User::query()->create([
            'name' => 'Owner One',
            'email' => 'owner-one@example.com',
            'password' => 'secret',
        ]);

        $ownerTwo = User::query()->create([
            'name' => 'Owner Two',
            'email' => 'owner-two@example.com',
            'password' => 'secret',
        ]);

        $subject = User::query()->create([
            'name' => 'Billable Subject',
            'email' => 'billable-subject@example.com',
            'password' => 'secret',
        ]);

        $directory = app(ChipCustomerDirectoryInterface::class);

        $link = OwnerContext::withOwner($ownerOne, fn () => $directory->link(new RecordReference($subject->getMorphClass(), (string) $subject->getKey()), 'chip_customer_test_123', [
            'source' => 'chip-customer-directory-test',
        ]));

        $scopedChipCustomerId = OwnerContext::withOwner($ownerOne, fn () => $directory->getChipCustomerId(new RecordReference($subject->getMorphClass(), (string) $subject->getKey())));

        expect($link->subject->equals(new RecordReference($subject->getMorphClass(), (string) $subject->getKey())))->toBeTrue()
            ->and($link->chip_customer_id)->toBe('chip_customer_test_123')
            ->and($link->owner?->type)->toBe($ownerOne->getMorphClass())
            ->and($link->owner?->id)->toBe((string) $ownerOne->getKey())
            ->and(OwnerContext::withOwner($ownerOne, fn () => $directory->hasChipCustomerId(new RecordReference($subject->getMorphClass(), (string) $subject->getKey()))))->toBeTrue()
            ->and($scopedChipCustomerId)->toBe('chip_customer_test_123')
            ->and($directory->findByChipCustomerId('chip_customer_test_123', new RecordReference($ownerOne->getMorphClass(), (string) $ownerOne->getKey()))?->id)->toBe($link->id)
            ->and($directory->findByChipCustomerId('chip_customer_test_123', new RecordReference($ownerTwo->getMorphClass(), (string) $ownerTwo->getKey())))->toBeNull();

        expect(fn () => OwnerContext::withOwner($ownerTwo, fn () => $directory->assertCanCreate(new RecordReference($subject->getMorphClass(), (string) $subject->getKey()))))
            ->toThrow(AuthorizationException::class);

        expect(fn () => OwnerContext::withOwner(
            $ownerTwo,
            fn () => $directory->link(new RecordReference($subject->getMorphClass(), (string) $subject->getKey()), 'chip_customer_cross_owner')
        ))->toThrow(AuthorizationException::class);
    });

    it('updates the existing subject link instead of creating duplicates', function (): void {
        $subject = User::query()->create([
            'name' => 'Relinked Subject',
            'email' => 'relinked-subject@example.com',
            'password' => 'secret',
        ]);

        $directory = app(ChipCustomerDirectoryInterface::class);

        $firstLink = $directory->link(new RecordReference($subject->getMorphClass(), (string) $subject->getKey()), 'chip_customer_first', ['source' => 'first']);
        $updatedLink = $directory->link(new RecordReference($subject->getMorphClass(), (string) $subject->getKey()), 'chip_customer_second', ['source' => 'second']);

        expect($updatedLink->id)->toBe($firstLink->id)
            ->and($directory->findForSubject(new RecordReference($subject->getMorphClass(), (string) $subject->getKey()))?->chip_customer_id)->toBe('chip_customer_second')
            ->and($directory->findForSubject(new RecordReference($subject->getMorphClass(), (string) $subject->getKey()))?->metadata)->toBe(['source' => 'second']);
    });
});

it('rejects a missing owner before linking any customer in owner mode', function (): void {
    config()->set('chip.owner.enabled', true);
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver(null));
    $subject = User::query()->create(['name' => 'Subject', 'email' => 'missing-owner-subject@example.com', 'password' => 'secret']);
    $reference = new RecordReference($subject->getMorphClass(), (string) $subject->getKey());

    expect(fn () => app(ChipCustomerDirectoryInterface::class)->link($reference, 'foreign-customer'))
        ->toThrow(NoCurrentOwnerException::class);
});

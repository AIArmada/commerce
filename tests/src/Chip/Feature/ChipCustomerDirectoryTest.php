<?php

declare(strict_types=1);

use AIArmada\Chip\Contracts\ChipCustomerDirectoryInterface;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
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

        $link = OwnerContext::withOwner($ownerOne, fn () => $directory->link($subject, 'chip_customer_test_123', [
            'source' => 'chip-customer-directory-test',
        ]));

        $scopedChipCustomerId = OwnerContext::withOwner($ownerOne, fn () => $directory->getChipCustomerId($subject));

        expect($link->subject?->is($subject))->toBeTrue()
            ->and($link->chip_customer_id)->toBe('chip_customer_test_123')
            ->and($link->owner_type)->toBe($ownerOne->getMorphClass())
            ->and($link->owner_id)->toBe((string) $ownerOne->getKey())
            ->and(OwnerContext::withOwner($ownerOne, fn () => $directory->hasChipCustomerId($subject)))->toBeTrue()
            ->and($scopedChipCustomerId)->toBe('chip_customer_test_123')
            ->and($directory->findByChipCustomerId('chip_customer_test_123', $ownerOne)?->is($link))->toBeTrue()
            ->and($directory->findByChipCustomerId('chip_customer_test_123', $ownerTwo))->toBeNull();

        expect(fn () => OwnerContext::withOwner(
            $ownerTwo,
            fn () => $directory->link($subject, 'chip_customer_cross_owner')
        ))->toThrow(AuthorizationException::class);
    });

    it('updates the existing subject link instead of creating duplicates', function (): void {
        $subject = User::query()->create([
            'name' => 'Relinked Subject',
            'email' => 'relinked-subject@example.com',
            'password' => 'secret',
        ]);

        $directory = app(ChipCustomerDirectoryInterface::class);

        $firstLink = $directory->link($subject, 'chip_customer_first', ['source' => 'first']);
        $updatedLink = $directory->link($subject, 'chip_customer_second', ['source' => 'second']);

        expect($updatedLink->is($firstLink))->toBeTrue()
            ->and($directory->findForSubject($subject)?->chip_customer_id)->toBe('chip_customer_second')
            ->and($directory->findForSubject($subject)?->metadata)->toBe(['source' => 'second']);
    });
});

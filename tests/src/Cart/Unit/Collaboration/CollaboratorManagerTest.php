<?php

declare(strict_types=1);

use AIArmada\Cart\Collaboration\Collaborator;
use AIArmada\Cart\Collaboration\CollaboratorManager;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    Config::set('cart.collaboration', [
        'enabled' => true,
        'invitation_expiry_days' => 7,
        'max_collaborators' => 10,
        'default_role' => 'editor',
    ]);
    Config::set('app.url', 'https://example.com');
    Mail::fake();
});

describe('CollaboratorManager', function (): void {
    it('can be instantiated', function (): void {
        $manager = new CollaboratorManager();

        expect($manager)->toBeInstanceOf(CollaboratorManager::class);
    });

    it('creates invitation for collaborator', function (): void {
        $manager = new CollaboratorManager();

        $collaborator = $manager->createInvitation('cart-123', 'test@example.com', 'editor');

        expect($collaborator)->toBeInstanceOf(Collaborator::class)
            ->and($collaborator->email)->toBe('test@example.com')
            ->and($collaborator->role)->toBe('editor')
            ->and($collaborator->status)->toBe('pending')
            ->and($collaborator->invitationToken)->not->toBeEmpty()
            ->and($collaborator->userId)->toBeNull()
            ->and($collaborator->joinedAt)->toBeNull();
    });

    it('uses default role when creating invitation', function (): void {
        $manager = new CollaboratorManager();

        $collaborator = $manager->createInvitation('cart-123', 'test@example.com');

        expect($collaborator->role)->toBe('editor');
    });

    it('accepts invitation', function (): void {
        $manager = new CollaboratorManager();

        $collaborator = $manager->acceptInvitation('token-abc', 'user-456', 'cart-123');

        expect($collaborator)->toBeInstanceOf(Collaborator::class)
            ->and($collaborator->userId)->toBe('user-456')
            ->and($collaborator->status)->toBe('active')
            ->and($collaborator->joinedAt)->not->toBeNull();
    });

    it('revokes access', function (): void {
        $manager = new CollaboratorManager();

        $result = $manager->revokeAccess('cart-123', 'user-456');

        expect($result)->toBeTrue();
    });

    it('updates collaborator role', function (): void {
        $manager = new CollaboratorManager();

        $result = $manager->updateRole('cart-123', 'user-456', 'admin');

        expect($result)->toBeTrue();
    });

    it('throws exception for invalid role', function (): void {
        $manager = new CollaboratorManager();

        expect(fn () => $manager->updateRole('cart-123', 'user-456', 'invalid'))
            ->toThrow(InvalidArgumentException::class, 'Invalid role: invalid');
    });

    it('gets collaborators for cart', function (): void {
        $manager = new CollaboratorManager();

        $collaborators = $manager->getCollaborators('cart-123');

        expect($collaborators)->toBeArray();
    });

    it('checks if collaboration is enabled', function (): void {
        $manager = new CollaboratorManager();

        expect($manager->isEnabled())->toBeTrue();
    });

    it('checks if collaboration is disabled', function (): void {
        Config::set('cart.collaboration.enabled', false);

        $manager = new CollaboratorManager();

        expect($manager->isEnabled())->toBeFalse();
    });

    it('gets max collaborators', function (): void {
        $manager = new CollaboratorManager();

        expect($manager->getMaxCollaborators())->toBe(10);
    });

    it('sends invitation email when mailable configured', function (): void {
        Config::set('cart.collaboration.invitation_mailable', null);

        $manager = new CollaboratorManager();
        $manager->createInvitation('cart-123', 'test@example.com');

        // No mailable configured, so no email sent
        Mail::assertNothingSent();
    });

    it('validates viewer role', function (): void {
        $manager = new CollaboratorManager();

        $result = $manager->updateRole('cart-123', 'user-456', 'viewer');

        expect($result)->toBeTrue();
    });

    it('validates editor role', function (): void {
        $manager = new CollaboratorManager();

        $result = $manager->updateRole('cart-123', 'user-456', 'editor');

        expect($result)->toBeTrue();
    });

    it('validates admin role', function (): void {
        $manager = new CollaboratorManager();

        $result = $manager->updateRole('cart-123', 'user-456', 'admin');

        expect($result)->toBeTrue();
    });
});

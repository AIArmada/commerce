<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Feedback\Actions\CreateFeedbackFormAction;
use AIArmada\Feedback\Actions\GenerateFeedbackInvitationUrlAction;
use AIArmada\Feedback\Actions\ResolveFeedbackInvitationTokenAction;
use AIArmada\Feedback\Data\CreateFeedbackFormData;
use AIArmada\Feedback\Models\FeedbackInvitation;

it('resolves a secure invitation token across owner contexts', function (): void {
    $invitationOwner = User::query()->create([
        'name' => 'Invitation Owner',
        'email' => 'invitation-owner-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
    $otherOwner = User::query()->create([
        'name' => 'Other Invitation Owner',
        'email' => 'other-invitation-owner-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
    $rawToken = 'secure-raw-token';

    $invitation = OwnerContext::withOwner($invitationOwner, function () use ($rawToken): FeedbackInvitation {
        $form = app(CreateFeedbackFormAction::class)
            ->execute(new CreateFeedbackFormData(name: 'Invitation Form'));

        return FeedbackInvitation::query()->create([
            'feedback_form_id' => $form->id,
            'token_hash' => hash('sha256', $rawToken),
            'status' => 'pending',
        ]);
    });

    $resolved = OwnerContext::withOwner(
        $otherOwner,
        fn (): FeedbackInvitation => app(ResolveFeedbackInvitationTokenAction::class)
            ->execute($rawToken),
    );

    expect($resolved->id)->toBe($invitation->id);
});

it('requires the original raw token when regenerating an invitation url', function (): void {
    $rawToken = 'original-token';
    $form = app(CreateFeedbackFormAction::class)
        ->execute(new CreateFeedbackFormData(name: 'URL Form'));
    $invitation = FeedbackInvitation::query()->create([
        'feedback_form_id' => $form->id,
        'token_hash' => hash('sha256', $rawToken),
        'status' => 'pending',
    ]);

    $url = app(GenerateFeedbackInvitationUrlAction::class)->execute($invitation, $rawToken);

    expect($url)->toEndWith('/feedback/invitations/' . $rawToken)
        ->and(fn () => app(GenerateFeedbackInvitationUrlAction::class)
            ->execute($invitation, 'wrong-token'))
        ->toThrow(InvalidArgumentException::class);
});

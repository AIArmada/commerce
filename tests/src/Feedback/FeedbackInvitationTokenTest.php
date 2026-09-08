<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Feedback\Actions\CreateFeedbackFormAction;
use AIArmada\Feedback\Actions\GenerateFeedbackInvitationUrlAction;
use AIArmada\Feedback\Actions\ResolveFeedbackInvitationTokenAction;
use AIArmada\Feedback\Actions\SendFeedbackInvitationAction;
use AIArmada\Feedback\Data\CreateFeedbackFormData;
use AIArmada\Feedback\Events\FeedbackInvitationCreated;
use AIArmada\Feedback\Models\FeedbackInvitation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

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

it('generates cryptographic tokens and excludes raw tokens from events', function (): void {
    $form = app(CreateFeedbackFormAction::class)
        ->execute(new CreateFeedbackFormData(name: 'Generated Token Form'));
    Event::fake();

    $result = app(SendFeedbackInvitationAction::class)->execute(
        form: $form,
        email: 'recipient@example.com',
    );
    $invitation = $result['invitation'];
    $rawToken = basename(parse_url($result['url'], PHP_URL_PATH));

    expect($rawToken)->toMatch('/^[a-f0-9]{64}$/')
        ->and($invitation->token_hash)->toBe(hash('sha256', $rawToken))
        ->and($invitation->getAttributes())->not->toHaveKey('token')
        ->and($invitation->getAttributes())->not->toContain($rawToken);

    Event::assertDispatched(FeedbackInvitationCreated::class, function (FeedbackInvitationCreated $event) use ($rawToken): bool {
        $attributes = $event->invitation->getAttributes();

        return ! array_key_exists('token', $attributes)
            && ! in_array($rawToken, $attributes, true);
    });
});

it('expires an invitation during token resolution', function (): void {
    $form = app(CreateFeedbackFormAction::class)
        ->execute(new CreateFeedbackFormData(name: 'Expired Token Form'));
    $rawToken = bin2hex(random_bytes(32));
    $invitation = FeedbackInvitation::query()->create([
        'feedback_form_id' => $form->id,
        'token_hash' => hash('sha256', $rawToken),
        'status' => 'pending',
        'expires_at' => CarbonImmutable::now()->subMinute(),
    ]);

    expect(fn () => app(ResolveFeedbackInvitationTokenAction::class)->execute($rawToken))
        ->toThrow(RuntimeException::class, 'expired')
        ->and($invitation->fresh()->status->value)->toBe('expired');
});

it('rejects forged invitation tokens', function (): void {
    expect(fn () => app(ResolveFeedbackInvitationTokenAction::class)
        ->execute(bin2hex(random_bytes(32))))
        ->toThrow(RuntimeException::class, 'Invalid invitation token');
});

it('throttles repeated invitation token attempts', function (): void {
    config()->set('feedback.security.invitation_rate_limit.max_attempts', 1);
    config()->set('feedback.security.invitation_rate_limit.decay_seconds', 60);
    $rawToken = bin2hex(random_bytes(32));

    expect(fn () => app(ResolveFeedbackInvitationTokenAction::class)->execute($rawToken))
        ->toThrow(RuntimeException::class, 'Invalid invitation token');

    expect(fn () => app(ResolveFeedbackInvitationTokenAction::class)->execute($rawToken))
        ->toThrow(RuntimeException::class, 'Too many invitation token attempts');
});

<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\Feedback\Contracts\InvitationUrlGenerator;
use AIArmada\Feedback\Enums\FeedbackInvitationStatus;
use AIArmada\Feedback\Events\FeedbackInvitationCreated;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackInvitation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class SendFeedbackInvitationAction
{
    public function __construct(
        private readonly InvitationUrlGenerator $urlGenerator,
    ) {}

    public function execute(
        FeedbackForm $form,
        ?Model $recipient = null,
        ?string $email = null,
        ?string $phone = null,
        ?int $expiryDays = null,
    ): array {
        $rawToken = Str::random(64);
        $tokenHash = hash('sha256', $rawToken);

        $invitation = FeedbackInvitation::create([
            'feedback_form_id' => $form->id,
            'recipient_type' => $recipient?->getMorphClass(),
            'recipient_id' => $recipient?->getKey(),
            'email' => $email,
            'phone' => $phone,
            'token_hash' => $tokenHash,
            'status' => FeedbackInvitationStatus::Pending,
            'expires_at' => CarbonImmutable::now()->addDays($expiryDays ?? (int) config('feedback.defaults.invitation_expiry_days', 14)),
        ]);

        $url = $this->urlGenerator->generate($invitation, $rawToken);

        FeedbackInvitationCreated::dispatch($invitation);

        return [
            'invitation' => $invitation,
            'url' => $url,
        ];
    }
}

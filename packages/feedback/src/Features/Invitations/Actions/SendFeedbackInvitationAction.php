<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Invitations\Actions;

use AIArmada\Feedback\Contracts\InvitationUrlGenerator;
use AIArmada\Feedback\Enums\FeedbackInvitationStatus;
use AIArmada\Feedback\Events\FeedbackInvitationCreated;
use AIArmada\Feedback\Events\FeedbackInvitationSent;
use AIArmada\Feedback\Features\Forms\Api\ReadsOwnedFormIdentity;
use AIArmada\Feedback\Features\Invitations\Models\FeedbackInvitation;
use AIArmada\Feedback\Support\FeedbackModelReferenceGuard;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class SendFeedbackInvitationAction
{
    public function __construct(
        private readonly InvitationUrlGenerator $urlGenerator,
        private readonly FeedbackModelReferenceGuard $referenceGuard,
        private readonly ReadsOwnedFormIdentity $forms,
    ) {}

    public function execute(
        string $formId,
        ?Model $recipient = null,
        ?string $email = null,
        ?string $phone = null,
        ?int $expiryDays = null,
    ): array {
        if ($recipient !== null) {
            $this->referenceGuard->validate($recipient);
        }

        $days = $expiryDays ?? (int) config('feedback.defaults.invitation_expiry_days', 14);
        if ($days < 1) {
            throw new InvalidArgumentException('Invitation expiry must be at least one day.');
        }

        return (new FeedbackInvitation)->getConnection()->transaction(function () use ($formId, $recipient, $email, $phone, $days): array {
            $form = $this->forms->requireOwned($formId, lock: true);
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);

            $invitation = FeedbackInvitation::create([
                'feedback_form_id' => $form->id,
                'recipient_type' => $recipient?->getMorphClass(),
                'recipient_id' => $recipient?->getKey(),
                'email' => $email,
                'phone' => $phone,
                'token_hash' => $tokenHash,
                'status' => FeedbackInvitationStatus::Sent,
                'sent_at' => CarbonImmutable::now(),
                'expires_at' => CarbonImmutable::now()->addDays($days),
            ]);

            $url = $this->urlGenerator->generate($invitation->id, $rawToken);

            FeedbackInvitationCreated::dispatch($invitation->id, $invitation->owner_type, $invitation->owner_id);
            FeedbackInvitationSent::dispatch($invitation->id, $invitation->owner_type, $invitation->owner_id);

            return [
                'invitation' => $invitation,
                'url' => $url,
            ];
        });
    }
}

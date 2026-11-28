<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Enums\FeedbackFormStatus;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use Illuminate\Support\Arr;
use RuntimeException;

final readonly class UpdateFeedbackFormAction
{
    public function __construct(
        private PublishFeedbackFormAction $publish,
        private CloseFeedbackFormAction $close,
        private ArchiveFeedbackFormAction $archive,
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(FeedbackForm $form, array $data): FeedbackForm
    {
        return $form->getConnection()->transaction(function () use ($form, $data): FeedbackForm {
            if (FeedbackForm::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(FeedbackForm::class, $form->id);
            }

            $form = FeedbackForm::query()->whereKey($form->id)->lockForUpdate()->firstOrFail();
            $form->fill(Arr::only($data, [
                'name', 'slug', 'purpose', 'visibility', 'is_anonymous_allowed',
                'is_anonymity_optional', 'is_login_required', 'is_one_response_per_respondent',
                'is_edit_after_submit_allowed', 'opens_at', 'closes_at',
            ]))->save();

            $status = $data['status'] ?? $form->status;
            $status = $status instanceof FeedbackFormStatus ? $status : FeedbackFormStatus::from($status);

            if ($form->status === FeedbackFormStatus::Archived && $status !== FeedbackFormStatus::Archived) {
                throw new RuntimeException('Archived feedback forms cannot be reactivated.');
            }

            return match ($status) {
                FeedbackFormStatus::Published => $this->publish->execute($form),
                FeedbackFormStatus::Closed => $this->close->execute($form),
                FeedbackFormStatus::Archived => $this->archive->execute($form),
                FeedbackFormStatus::Draft => $this->draft($form),
            };
        });
    }

    private function draft(FeedbackForm $form): FeedbackForm
    {
        if ($form->status !== FeedbackFormStatus::Draft) {
            $form->forceFill(['status' => FeedbackFormStatus::Draft])->save();
        }

        return $form;
    }
}

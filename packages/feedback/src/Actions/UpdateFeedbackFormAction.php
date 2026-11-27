<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Enums\FeedbackFormStatus;
use AIArmada\Feedback\Models\FeedbackForm;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

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
        $guarded = OwnerWriteGuard::findOrFailForOwner(FeedbackForm::class, $form->id);

        return DB::transaction(function () use ($guarded, $data): FeedbackForm {
            $form = FeedbackForm::query()->whereKey($guarded->id)->lockForUpdate()->firstOrFail();
            $form->fill(Arr::only($data, [
                'name', 'slug', 'purpose', 'visibility', 'is_anonymous_allowed',
                'is_anonymity_optional', 'is_login_required', 'is_one_response_per_respondent',
                'is_edit_after_submit_allowed', 'opens_at', 'closes_at',
            ]))->save();

            $status = $data['status'] ?? $form->status;
            $status = $status instanceof FeedbackFormStatus ? $status : FeedbackFormStatus::from($status);

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

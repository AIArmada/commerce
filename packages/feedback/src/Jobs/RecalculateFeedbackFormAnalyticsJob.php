<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Jobs;

use AIArmada\CommerceSupport\Contracts\OwnerScopedJob;
use AIArmada\CommerceSupport\Support\OwnerJobContext;
use AIArmada\CommerceSupport\Traits\OwnerContextJob;
use AIArmada\Feedback\Actions\RecalculateFeedbackFormAnalyticsAction;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackResponse;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

final class RecalculateFeedbackFormAnalyticsJob implements OwnerScopedJob, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use OwnerContextJob;
    use Queueable;

    public function __construct(
        public readonly string $formId,
        public readonly ?string $ownerType,
        public readonly string | int | null $ownerId,
        public readonly bool $ownerIsGlobal = false,
    ) {}

    public static function forResponse(FeedbackResponse $response): self
    {
        return new self(
            formId: (string) $response->feedback_form_id,
            ownerType: $response->owner_type,
            ownerId: $response->owner_id,
            ownerIsGlobal: $response->owner_type === null && $response->owner_id === null,
        );
    }

    public static function forForm(FeedbackForm $form): self
    {
        return new self(
            formId: (string) $form->getKey(),
            ownerType: $form->owner_type,
            ownerId: $form->owner_id,
            ownerIsGlobal: $form->owner_type === null && $form->owner_id === null,
        );
    }

    public function ownerContext(): OwnerJobContext
    {
        return new OwnerJobContext(
            ownerType: $this->ownerType,
            ownerId: $this->ownerId,
            ownerIsGlobal: $this->ownerIsGlobal,
        );
    }

    protected function performJob(): void
    {
        $form = FeedbackForm::query()->whereKey($this->formId)->first();

        if (! $form instanceof FeedbackForm) {
            return;
        }

        app(RecalculateFeedbackFormAnalyticsAction::class)->execute($form);
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Analytics\EntryPoints\Jobs;

use AIArmada\CommerceSupport\Contracts\OwnerScopedJob;
use AIArmada\CommerceSupport\Data\OwnerJobContext;
use AIArmada\CommerceSupport\Traits\OwnerContextJob;
use AIArmada\Feedback\Features\Analytics\Actions\RecalculateFeedbackFormAnalyticsAction;
use AIArmada\Feedback\Features\Forms\Api\ReadsOwnedFormIdentity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

final class RecalculateFeedbackFormAnalyticsJob implements OwnerScopedJob, ShouldBeUnique, ShouldQueue
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

    public function ownerContext(): OwnerJobContext
    {
        return new OwnerJobContext(
            ownerType: $this->ownerType,
            ownerId: $this->ownerId,
            ownerIsGlobal: $this->ownerIsGlobal,
        );
    }

    public function uniqueId(): string
    {
        return 'feedback-analytics:' . $this->formId;
    }

    protected function performJob(): void
    {
        if (app(ReadsOwnedFormIdentity::class)->find($this->formId) === null) {
            return;
        }

        app(RecalculateFeedbackFormAnalyticsAction::class)->execute($this->formId);
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Jobs;

use AIArmada\Cart\Actions\RemoveStoredConditions;
use AIArmada\Cart\Models\Condition;
use AIArmada\CommerceSupport\Contracts\OwnerScopedJob;
use AIArmada\CommerceSupport\Support\OwnerJobContext;
use AIArmada\CommerceSupport\Traits\OwnerContextJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

final class RemoveConditionFromAllCartsJob implements OwnerScopedJob, ShouldQueue
{
    use Dispatchable;
    use OwnerContextJob;
    use Queueable;

    public function __construct(
        public readonly string $conditionId,
        public readonly OwnerJobContext $jobOwnerContext,
    ) {}

    public function ownerContext(): OwnerJobContext
    {
        return $this->jobOwnerContext;
    }

    public static function forCondition(Condition $condition): self
    {
        $ownerType = $condition->getAttribute('owner_type');
        $ownerId = $condition->getAttribute('owner_id');

        $context = is_string($ownerType) && $ownerType !== '' && ($ownerId !== null && $ownerId !== '')
            ? new OwnerJobContext(ownerType: $ownerType, ownerId: $ownerId)
            : OwnerJobContext::explicitGlobal();

        return new self((string) $condition->getKey(), $context);
    }

    protected function performJob(): void
    {
        $condition = Condition::query()->find($this->conditionId);

        if (! $condition instanceof Condition) {
            Log::warning('RemoveConditionFromAllCartsJob skipped: condition no longer exists.', [
                'condition_id' => $this->conditionId,
            ]);

            return;
        }

        $result = app(RemoveStoredConditions::class)->handle($condition);

        Log::info('RemoveConditionFromAllCartsJob completed.', [
            'condition_id' => $this->conditionId,
            'carts_processed' => $result['carts_processed'],
            'carts_updated' => $result['carts_updated'],
            'errors' => $result['errors'],
        ]);
    }
}

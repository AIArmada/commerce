<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Suppressions\Queries;

use AIArmada\Communications\Contracts\SuppressionResolver;
use AIArmada\Communications\Data\SuppressionDecisionData;
use AIArmada\Communications\Features\Suppressions\Models\CommunicationSuppression;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class ActiveSuppressionResolver implements SuppressionResolver
{
    public function resolveSuppression(
        ?string $recipientType,
        ?string $recipientId,
        ?string $destinationHash,
        ?string $channel,
        ?string $category,
    ): SuppressionDecisionData {
        if ($destinationHash === null || $destinationHash === '' || $channel === null) {
            return new SuppressionDecisionData(suppressed: false);
        }

        $now = CarbonImmutable::now();

        $suppression = CommunicationSuppression::query()
            ->where('destination_hash', $destinationHash)
            ->where('channel', $channel)
            ->whereNull('lifted_at')
            ->where(fn (Builder $query): Builder => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $query): Builder => $query->whereNull('expires_at')->orWhere('expires_at', '>', $now))
            ->where(function (Builder $query) use ($recipientType, $recipientId): void {
                $query->where(fn (Builder $global): Builder => $global->whereNull('recipient_type')->whereNull('recipient_id'));

                if ($recipientType !== null && $recipientId !== null) {
                    $query->orWhere(fn (Builder $recipient): Builder => $recipient->where('recipient_type', $recipientType)->where('recipient_id', $recipientId));
                }
            })
            ->where(function (Builder $query) use ($category): void {
                $query->whereNull('category');

                if ($category !== null) {
                    $query->orWhere('category', $category);
                }
            })
            ->orderByDesc('starts_at')
            ->orderBy('id')
            ->first();

        return $suppression === null
            ? new SuppressionDecisionData(suppressed: false)
            : new SuppressionDecisionData(
                suppressed: true,
                reason: $suppression->reason->value,
                suppressionId: $suppression->id,
            );
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Support;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Signals\Models\SignalGoal;
use AIArmada\Signals\Models\SignalSegment;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class SavedSignalReportMutationGuard
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sanitize(array $data): array
    {
        $data['tracked_property_id'] = $this->sanitizeTrackedPropertyId($data['tracked_property_id'] ?? null);
        $data['signal_segment_id'] = $this->sanitizeSignalSegmentId($data['signal_segment_id'] ?? null);

        if (! is_array($data['settings'] ?? null)) {
            return $data;
        }

        /** @var array<string, mixed> $settings */
        $settings = $data['settings'];

        if (! is_array($settings['funnel_steps'] ?? null)) {
            return $data;
        }

        $trackedPropertyId = is_string($data['tracked_property_id']) && $data['tracked_property_id'] !== ''
            ? $data['tracked_property_id']
            : null;

        /** @var array<int, mixed> $funnelSteps */
        $funnelSteps = $settings['funnel_steps'];

        foreach ($funnelSteps as $index => $step) {
            if (! is_array($step)) {
                continue;
            }

            $goalSlug = $step['goal_slug'] ?? null;
            if (! is_string($goalSlug) || $goalSlug === '') {
                continue;
            }

            $goal = SignalGoal::query()
                ->forOwner()
                ->where('slug', $goalSlug)
                ->where('is_active', true)
                ->when(
                    $trackedPropertyId !== null,
                    fn (Builder $query): Builder => $query->where(function (Builder $goalQuery) use ($trackedPropertyId): void {
                        $goalQuery->where('tracked_property_id', $trackedPropertyId)
                            ->orWhereNull('tracked_property_id');
                    }),
                )
                ->first();

            if ($goal instanceof SignalGoal) {
                continue;
            }

            throw ValidationException::withMessages([
                sprintf('settings.funnel_steps.%d.goal_slug', $index) => 'Selected goal is not accessible.',
            ]);
        }

        return $data;
    }

    private function sanitizeTrackedPropertyId(mixed $trackedPropertyId): ?string
    {
        if (! is_string($trackedPropertyId) || $trackedPropertyId === '') {
            return null;
        }

        try {
            OwnerWriteGuard::findOrFailForOwner(
                TrackedProperty::class,
                $trackedPropertyId,
                includeGlobal: false,
                message: 'Selected tracked property is not accessible.',
            );
        } catch (AuthorizationException | InvalidArgumentException) {
            throw ValidationException::withMessages([
                'tracked_property_id' => 'Selected tracked property is not accessible.',
            ]);
        }

        return $trackedPropertyId;
    }

    private function sanitizeSignalSegmentId(mixed $signalSegmentId): ?string
    {
        if (! is_string($signalSegmentId) || $signalSegmentId === '') {
            return null;
        }

        try {
            OwnerWriteGuard::findOrFailForOwner(
                SignalSegment::class,
                $signalSegmentId,
                includeGlobal: false,
                message: 'Selected segment is not accessible.',
            );
        } catch (AuthorizationException | InvalidArgumentException) {
            throw ValidationException::withMessages([
                'signal_segment_id' => 'Selected segment is not accessible.',
            ]);
        }

        return $signalSegmentId;
    }
}

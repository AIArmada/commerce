<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Enums\RegistrationStatus;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Events\Models\Registration;
use Illuminate\Support\Facades\DB;

final class PromoteWaitlistedRegistrationsAction
{
    /**
     * Promote waitlisted registrations when capacity opens on an occurrence.
     */
    public function handle(Occurrence $occurrence, ?int $slotsToFree = null): int
    {
        if (! config('events.lifecycle.registration.auto_promote_waitlist', false)) {
            return 0;
        }

        $capacity = $occurrence->capacity;

        if ($capacity === null) {
            return 0;
        }

        $blockingCount = Registration::query()
            ->where('occurrence_id', $occurrence->id)
            ->whereIn('status', RegistrationStatus::capacityBlockingValues())
            ->count();

        $available = max(0, $capacity - $blockingCount);

        if ($slotsToFree !== null && $slotsToFree < $available) {
            $available = $slotsToFree;
        }

        if ($available <= 0) {
            return 0;
        }

        $promoted = 0;

        DB::transaction(function () use ($occurrence, $available, &$promoted): void {
            $waitlisted = Registration::query()
                ->where('occurrence_id', $occurrence->id)
                ->where('status', RegistrationStatus::Waitlisted->value)
                ->orderBy('created_at')
                ->limit($available)
                ->get();

            foreach ($waitlisted as $registration) {
                $registration->update(['status' => RegistrationStatus::Pending->value]);
                $promoted++;
            }
        });

        return $promoted;
    }
}

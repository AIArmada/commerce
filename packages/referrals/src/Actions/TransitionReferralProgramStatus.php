<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Actions;

use AIArmada\Referrals\Enums\ReferralProgramStatus;
use AIArmada\Referrals\Models\ReferralProgram;
use AIArmada\Referrals\Support\ReferralProgramGuard;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class TransitionReferralProgramStatus
{
    public function __construct(private ReferralProgramGuard $guard) {}

    public function handle(string $programId, ReferralProgramStatus $target, ReferralProgramStatus $expected): ReferralProgram
    {
        return DB::transaction(function () use ($programId, $target, $expected): ReferralProgram {
            $program = $this->guard->program($programId, lock: true);

            $allowed = match ($program->status) {
                ReferralProgramStatus::Draft => [ReferralProgramStatus::Active, ReferralProgramStatus::Archived],
                ReferralProgramStatus::Active => [ReferralProgramStatus::Paused, ReferralProgramStatus::Archived],
                ReferralProgramStatus::Paused => [ReferralProgramStatus::Active, ReferralProgramStatus::Archived],
                ReferralProgramStatus::Archived => [],
            };

            if ($program->status !== $expected || ! in_array($target, $allowed, true)) {
                throw new LogicException('The program status changed or the requested transition is not allowed.');
            }

            $program->status = $target;
            $program->save();

            return $program;
        });
    }
}

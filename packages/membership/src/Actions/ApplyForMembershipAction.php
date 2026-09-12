<?php

declare(strict_types=1);

namespace AIArmada\Membership\Actions;

use AIArmada\Membership\Contracts\MembershipApplicationNotifier;
use AIArmada\Membership\Enums\ApplicationStatus;
use AIArmada\Membership\Events\MembershipApplicationSubmitted;
use AIArmada\Membership\Models\MembershipApplication;
use AIArmada\Membership\Support\MembershipSubjectGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use ValueError;

final class ApplyForMembershipAction
{
    private const string UNIQUE_INDEX = 'membership_applications_subject_applicant_status_unique';

    use AsAction;

    public function handle(Model $subject, Model $user, string $justification, array $meta = []): MembershipApplication
    {
        app(MembershipSubjectGuard::class)->validate($subject);

        if ($justification === '') {
            throw new ValueError('Justification cannot be empty.');
        }

        $subjectType = $subject->getMorphClass();
        $subjectId = (string) $subject->getKey();
        $applicantId = (string) $user->getKey();
        $created = false;

        try {
            $application = DB::transaction(function () use ($applicantId, $justification, $meta, $subjectId, $subjectType, &$created): MembershipApplication {
                $existing = $this->pendingApplicationQuery($subjectType, $subjectId, $applicantId)
                    ->lockForUpdate()
                    ->first();

                if ($existing instanceof MembershipApplication) {
                    return $existing;
                }

                $created = true;

                return MembershipApplication::query()->create([
                    'subject_type' => $subjectType,
                    'subject_id' => $subjectId,
                    'applicant_id' => $applicantId,
                    'status' => ApplicationStatus::Pending,
                    'justification' => $justification,
                    'meta' => $meta,
                ]);
            });
        } catch (QueryException $exception) {
            if (! $this->isApplicationUniquenessViolation($exception)) {
                throw $exception;
            }

            $created = false;
            $application = $this->pendingApplicationQuery($subjectType, $subjectId, $applicantId)->firstOrFail();
        }

        if (! $created) {
            return $application;
        }

        MembershipApplicationSubmitted::dispatch($application);

        if (app()->bound(MembershipApplicationNotifier::class)) {
            app(MembershipApplicationNotifier::class)->notifySubmitted($application);
        }

        return $application;
    }

    /**
     * @return Builder<MembershipApplication>
     */
    private function pendingApplicationQuery(string $subjectType, string $subjectId, string $applicantId): Builder
    {
        return MembershipApplication::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('applicant_id', $applicantId)
            ->where('status', ApplicationStatus::Pending);
    }

    private function isApplicationUniquenessViolation(QueryException $exception): bool
    {
        if (! in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
            return false;
        }

        $message = mb_strtolower($exception->getMessage());

        if (str_contains($message, self::UNIQUE_INDEX)) {
            return true;
        }

        $tableName = mb_strtolower((new MembershipApplication)->getTable());

        return str_contains($message, $tableName)
            && str_contains($message, 'subject_type')
            && str_contains($message, 'subject_id')
            && str_contains($message, 'applicant_id')
            && str_contains($message, 'status');
    }
}

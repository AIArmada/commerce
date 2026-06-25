<?php

declare(strict_types=1);

namespace AIArmada\Membership\Actions;

use AIArmada\Membership\Contracts\MembershipApplicationNotifier;
use AIArmada\Membership\Enums\ApplicationStatus;
use AIArmada\Membership\Events\MembershipApplicationSubmitted;
use AIArmada\Membership\Models\MembershipApplication;
use AIArmada\Membership\Support\MembershipSubjectGuard;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;
use ValueError;

final class ApplyForMembershipAction
{
    use AsAction;

    public function handle(Model $subject, Model $user, string $justification, array $meta = []): MembershipApplication
    {
        app(MembershipSubjectGuard::class)->validate($subject);

        if ($justification === '') {
            throw new ValueError('Justification cannot be empty.');
        }

        $application = MembershipApplication::query()->create([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'applicant_id' => $user->getKey(),
            'status' => ApplicationStatus::Pending,
            'justification' => $justification,
            'meta' => $meta,
        ]);

        MembershipApplicationSubmitted::dispatch($application);

        if (app()->bound(MembershipApplicationNotifier::class)) {
            app(MembershipApplicationNotifier::class)->notifySubmitted($application);
        }

        return $application;
    }
}

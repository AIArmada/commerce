<?php

declare(strict_types=1);

namespace AIArmada\Membership\Queries;

use AIArmada\Membership\Data\MembershipApplicationData;
use AIArmada\Membership\Models\MembershipApplication;

final readonly class MembershipApplicationMapper
{
    public static function snapshot(MembershipApplication $application): MembershipApplicationData
    {
        return new MembershipApplicationData(
            id: (string) $application->getKey(),
            subjectType: $application->subject_type,
            subjectId: $application->subject_id,
            applicantId: $application->applicant_id,
            status: $application->status->value,
            justification: $application->justification,
            grantedRole: $application->granted_role,
            reviewerId: $application->reviewer_id,
            reviewerNote: $application->reviewer_note,
            reviewedAt: $application->reviewed_at,
            meta: $application->meta,
        );
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Attendance\Extensions;

use AIArmada\Events\Contracts\EventScopeCascadeDeleter;
use AIArmada\Events\Features\Attendance\Actions\DeleteAttendanceScopeDependents;

final class AttendanceDeleteCascadeExtension implements EventScopeCascadeDeleter
{
    public function __construct(
        private readonly DeleteAttendanceScopeDependents $deleteScopeDependents,
    ) {}

    public function deleteForScope(string $scopeType, string $scopeId, ?string $morphClass = null): void
    {
        $this->deleteScopeDependents->deleteForScope($scopeType, $scopeId, $morphClass);
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\EventChangeLog;

interface EventChangeNoticeWorkflow
{
    public function publishNotice(EventChangeLog $changeLog, array $options = []): void;

    public function retractNotice(EventChangeLog $changeLog): void;
}

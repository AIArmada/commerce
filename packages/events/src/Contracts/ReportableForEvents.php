<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface ReportableForEvents
{
    public function eventReportTitle(): string;
}

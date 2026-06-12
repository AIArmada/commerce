<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventSubmission;

interface EventSubmissionConverter
{
    public function convert(EventSubmission $submission): Event;
}

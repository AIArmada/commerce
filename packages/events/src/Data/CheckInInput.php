<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class CheckInInput extends Data
{
    public function __construct(
        public readonly string $event_id,
        public readonly string $event_occurrence_id,
        public readonly string|null|Optional $event_session_id,
        public readonly string|null|Optional $event_pass_id,
        public readonly string|null|Optional $event_registration_id,
        public readonly string|null|Optional $event_registration_participant_id,
        public readonly string $attendance_type = 'registered',
        public readonly string $check_in_source = 'manual',
    ) {}
}

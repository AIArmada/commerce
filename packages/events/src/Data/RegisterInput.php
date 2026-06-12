<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class RegisterInput extends Data
{
    public function __construct(
        public readonly string $event_id,
        public readonly string | null | Optional $event_occurrence_id,
        public readonly string | null | Optional $event_session_id,
        public readonly string | null | Optional $registrant_type,
        public readonly string | null | Optional $registrant_id,
        public readonly string $registration_type,
        public readonly string $source,
        /** @var array<ParticipantInput> */
        public readonly array $participants,
    ) {}
}

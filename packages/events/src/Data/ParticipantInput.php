<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class ParticipantInput extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly string|null|Optional $email,
        public readonly string|null|Optional $phone,
        public readonly string|null|Optional $relationship_to_registrant,
        public readonly string|null|Optional $event_occurrence_id,
        public readonly string|null|Optional $event_session_id,
        public readonly int|null|Optional $age,
        public readonly string|null|Optional $gender,
        public readonly bool $is_primary = false,
    ) {}
}

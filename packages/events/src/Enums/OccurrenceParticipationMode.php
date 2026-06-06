<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums;

enum OccurrenceParticipationMode: string
{
    case None = 'none';
    case RegistrationRequired = 'registration_required';
    case WalkInOnly = 'walk_in_only';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::None => 'No Attendance Tracking',
            self::RegistrationRequired => 'Registration Required',
            self::WalkInOnly => 'Walk-in Only',
            self::Hybrid => 'Registration and Walk-in',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::None => 'gray',
            self::RegistrationRequired => 'primary',
            self::WalkInOnly => 'info',
            self::Hybrid => 'success',
        };
    }

    public function acceptsRegistrations(): bool
    {
        return in_array($this, [self::RegistrationRequired, self::Hybrid], true);
    }

    public function acceptsWalkIns(): bool
    {
        return in_array($this, [self::WalkInOnly, self::Hybrid], true);
    }

    public function tracksParticipants(): bool
    {
        return $this !== self::None;
    }
}

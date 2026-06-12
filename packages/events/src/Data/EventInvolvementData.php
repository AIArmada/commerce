<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use AIArmada\Events\Models\EventInvolvement;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class EventInvolvementData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string|null|Optional $role_code,
        public readonly string|null|Optional $involveable_type,
        public readonly string|null|Optional $involveable_id,
        public readonly string|null|Optional $display_name,
        public readonly string $prominence,
        public readonly bool $is_featured,
        public readonly bool $is_primary,
        public readonly string $status,
    ) {}

    public static function fromEventInvolvement(EventInvolvement $involvement): self
    {
        $displayName = null;
        if ($involvement->relationLoaded('involveable') && $involvement->involveable) {
            $displayName = method_exists($involvement->involveable, 'getName')
                ? $involvement->involveable->getName()
                : $involvement->involveable->name ?? $involvement->involveable->title ?? null;
        }

        return new self(
            id: $involvement->id,
            role_code: $involvement->role_code,
            involveable_type: $involvement->involveable_type,
            involveable_id: $involvement->involveable_id,
            display_name: $displayName,
            prominence: $involvement->prominence,
            is_featured: $involvement->is_featured,
            is_primary: $involvement->is_primary,
            status: $involvement->status,
        );
    }
}

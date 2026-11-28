<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Numbering\Support;

use AIArmada\CommerceSupport\Support\OwnerScopeKey;
use Illuminate\Database\Eloquent\Model;

final readonly class SequenceWriteLock
{
    public static function key(?Model $owner): string
    {
        return 'docs.sequence-first-use.' . OwnerScopeKey::forOwner($owner);
    }
}

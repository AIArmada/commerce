<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums;

enum EventEngagementType: string
{
    case View = 'view';
    case Click = 'click';
    case Share = 'share';
    case Rsvp = 'rsvp';
    case Follow = 'follow';
    case Bookmark = 'bookmark';

    public function label(): string
    {
        return match ($this) {
            self::View => 'View',
            self::Click => 'Click',
            self::Share => 'Share',
            self::Rsvp => 'RSVP',
            self::Follow => 'Follow',
            self::Bookmark => 'Bookmark',
        };
    }
}

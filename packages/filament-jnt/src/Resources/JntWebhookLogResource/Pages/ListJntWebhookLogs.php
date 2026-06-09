<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Resources\JntWebhookLogResource\Pages;

use AIArmada\CommerceSupport\Filament\Pages\ReadOnlyListRecords;
use AIArmada\FilamentJnt\Resources\JntWebhookLogResource;
use Override;

final class ListJntWebhookLogs extends ReadOnlyListRecords
{
    protected static string $resource = JntWebhookLogResource::class;

    #[Override]
    public function getTitle(): string
    {
        return 'J&T Webhook Logs';
    }

    #[Override]
    public function getSubheading(): string
    {
        return 'Monitor incoming webhook notifications from J&T Express.';
    }
}

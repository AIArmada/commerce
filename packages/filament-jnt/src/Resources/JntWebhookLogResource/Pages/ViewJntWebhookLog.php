<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Resources\JntWebhookLogResource\Pages;

use AIArmada\FilamentJnt\Resources\JntWebhookLogResource;
use AIArmada\FilamentJnt\Resources\Pages\ReadOnlyViewRecord;
use Filament\Support\Icons\Heroicon;
use Override;

final class ViewJntWebhookLog extends ReadOnlyViewRecord
{
    protected static string $resource = JntWebhookLogResource::class;

    #[Override]
    public function getTitle(): string
    {
        $record = $this->getRecord();

        return sprintf('Webhook %s', $record->tracking_number ?? $record->getKey());
    }

    public function getHeadingIcon(): Heroicon
    {
        return Heroicon::OutlinedBolt;
    }
}

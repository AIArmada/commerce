<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources\SignalInteractionRuleResource\Pages;

use AIArmada\FilamentSignals\Resources\SignalInteractionRuleResource;
use Filament\Resources\Pages\EditRecord;

final class EditSignalInteractionRule extends EditRecord
{
    protected static string $resource = SignalInteractionRuleResource::class;

    public function getTitle(): string
    {
        return 'Edit interaction rule';
    }

    public function getSubheading(): ?string
    {
        return 'Update selector, page scope, or event mapping for this tracked browser interaction.';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

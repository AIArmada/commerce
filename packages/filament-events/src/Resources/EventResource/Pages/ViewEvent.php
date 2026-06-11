<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventResource\Pages;

use AIArmada\FilamentEvents\Actions\ApproveEventAction;
use AIArmada\FilamentEvents\Actions\RejectEventAction;
use AIArmada\FilamentEvents\Actions\RequestChangesAction;
use AIArmada\FilamentEvents\Actions\SubmitForReviewAction;
use AIArmada\FilamentEvents\Resources\EventResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

final class ViewEvent extends ViewRecord
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SubmitForReviewAction::make(),
            ApproveEventAction::make(),
            RequestChangesAction::make(),
            RejectEventAction::make(),
            Actions\EditAction::make(),
        ];
    }
}

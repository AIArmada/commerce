<?php

declare(strict_types=1);

namespace AIArmada\FilamentLinks\Resources\LinkResource\Pages;

use AIArmada\FilamentLinks\Resources\LinkResource;
use AIArmada\FilamentLinks\Resources\LinkResource\Schemas\LinkForm;
use AIArmada\Links\Actions\GenerateLinkUrl;
use AIArmada\Links\Actions\UpdateLink as UpdateLinkAction;
use AIArmada\Links\Models\Link;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditLink extends EditRecord
{
    protected static string $resource = LinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('open')
                ->label('Open cloaked URL')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (): string => $this->cloakedUrl())
                ->openUrlInNewTab(),

            Actions\DeleteAction::make()
                ->authorize(fn (Link $record): bool => LinkResource::canDelete($record))
                ->visible(fn (Link $record): bool => LinkResource::canDelete($record)),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Link $record */
        if (LinkForm::isProtectedSubject($record)) {
            unset($data['slug'], $data['destination_url'], $data['parameters'], $data['utm_defaults'], $data['require_signature']);
        }

        return UpdateLinkAction::run($record, $data);
    }

    private function cloakedUrl(): string
    {
        /** @var Link $record */
        $record = $this->getRecord();

        return GenerateLinkUrl::run($record);
    }
}

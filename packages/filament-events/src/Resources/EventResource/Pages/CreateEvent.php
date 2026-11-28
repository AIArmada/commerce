<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventResource\Pages;

use AIArmada\Events\Contracts\EventTemplateService;
use AIArmada\FilamentEvents\Resources\EventResource;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createFromTemplate')
                ->label('From Template')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->form([
                    Select::make('event_template_id')
                        ->label('Template')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => app(EventTemplateService::class)->publishedEventOptions($search))
                        ->getOptionLabelUsing(fn (string $value): ?string => app(EventTemplateService::class)->publishedEventLabel($value))
                        ->required(),
                ])
                ->authorize(fn (): bool => EventResource::canCreate())
                ->action(function (array $data): void {
                    try {
                        $eventId = app(EventTemplateService::class)->createFromPublishedEventTemplate((string) $data['event_template_id'], Filament::auth()->user());
                        redirect(EventResource::getUrl('edit', ['record' => $eventId]));
                    } catch (AuthorizationException | ValidationException $e) {
                        throw $e;
                    } catch (Throwable $e) {
                        report($e);
                        Notification::make()
                            ->title('Failed to create from template')
                            ->body('The event could not be created. Please try again.')
                            ->danger()
                            ->send();
                    }
                })
                ->modalWidth('lg'),
        ];
    }
}

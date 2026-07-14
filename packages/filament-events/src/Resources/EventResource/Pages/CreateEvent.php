<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventResource\Pages;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\EventTemplateService;
use AIArmada\Events\Models\EventTemplate;
use AIArmada\FilamentEvents\Resources\EventResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Throwable;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;

    public function boot(): void
    {
        OwnerContext::setForRequest(null);
    }

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
                        ->options(
                            EventTemplate::query()
                                ->where('template_type', 'event')
                                ->where('status', 'published')
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (EventTemplate $t): array => [$t->id => "{$t->name} ({$t->code})"])
                                ->toArray(),
                        )
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $template = EventTemplate::query()->findOrFail($data['event_template_id']);

                    try {
                        $event = app(EventTemplateService::class)->createFromTemplate($template);
                        redirect(EventResource::getUrl('edit', ['record' => $event]));
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Failed to create from template')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->modalWidth('lg'),
        ];
    }
}

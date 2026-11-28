<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventSessionResource\Pages;

use AIArmada\Events\Features\Catalog\Actions\UpdateEventSessionAction;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\FilamentEvents\Resources\EventSessionResource;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use LogicException;

final class EditEventSession extends EditRecord
{
    protected static string $resource = EventSessionResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof EventSession) {
            throw new LogicException('Unexpected session record.');
        }

        Gate::forUser(Filament::auth()->user())->authorize('update', $record);

        return app(UpdateEventSessionAction::class)->handle($record, $data)['session'];
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

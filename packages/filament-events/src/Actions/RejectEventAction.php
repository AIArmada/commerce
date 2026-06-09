<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Actions;

use AIArmada\Events\Contracts\EventModerationWorkflow;
use AIArmada\Events\Models\Event;
use AIArmada\FilamentEvents\Resources\EventResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Illuminate\Database\Eloquent\Model;

final class RejectEventAction
{
    public static function make(): Action
    {
        return Action::make('rejectEvent')
            ->label('Reject')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Event $record): bool => in_array('reject', EventResource::reviewSchema($record)->actions, true))
            ->schema([
                Select::make('reason_key')
                    ->label('Reason')
                    ->options(fn (): array => EventResource::reasonCodeOptions())
                    ->required(),

                Textarea::make('note')
                    ->label('Note')
                    ->rows(3)
                    ->required(),
            ])
            ->action(function (Event $record, array $data): void {
                $actor = auth()->user();
                app(EventModerationWorkflow::class)->reject($record, $actor instanceof Model ? $actor : null, [
                    'source' => 'filament',
                    'reason_key' => $data['reason_key'] ?? null,
                    'note' => $data['note'] ?? null,
                ]);
            });
    }
}

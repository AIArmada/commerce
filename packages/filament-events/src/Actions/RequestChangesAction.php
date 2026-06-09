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

final class RequestChangesAction
{
    public static function make(): Action
    {
        return Action::make('requestChanges')
            ->label('Request Changes')
            ->icon('heroicon-o-pencil-square')
            ->color('warning')
            ->visible(fn (Event $record): bool => in_array('request_changes', EventResource::reviewSchema($record)->actions, true))
            ->schema([
                Select::make('reason_key')
                    ->label('Reason')
                    ->options(fn (): array => EventResource::reasonCodeOptions())
                    ->required()
                    ->helperText('A reason code is required when requesting changes.'),

                Textarea::make('note')
                    ->label('Note')
                    ->rows(3)
                    ->required()
                    ->helperText('A reviewer note is required when requesting changes.'),
            ])
            ->action(function (Event $record, array $data): void {
                $actor = auth()->user();
                app(EventModerationWorkflow::class)->requestChanges($record, $actor instanceof Model ? $actor : null, [
                    'source' => 'filament',
                    'reason_key' => $data['reason_key'] ?? null,
                    'note' => $data['note'] ?? null,
                ]);
            });
    }
}

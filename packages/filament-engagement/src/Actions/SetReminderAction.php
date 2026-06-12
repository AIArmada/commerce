<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Actions;

use AIArmada\Engagement\Contracts\ReminderManager;
use Filament\Forms;
use Filament\Actions\Action;

final class SetReminderAction
{
    public static function make(): Action
    {
        return Action::make('setReminder')
            ->label('Set Reminder')
            ->icon('heroicon-o-clock')
            ->form([
                Forms\Components\Select::make('reminder_type')
                    ->options(['before_start' => 'Before Start', 'when_live_starts' => 'When Live', 'custom' => 'Custom'])
                    ->required(),
                Forms\Components\DateTimePicker::make('remind_at')->label('Remind At'),
            ])
            ->action(function (array $data, $record) {
                app(ReminderManager::class)->setReminder(auth()->user(), $record, $data['reminder_type'], $data);
            });
    }
}

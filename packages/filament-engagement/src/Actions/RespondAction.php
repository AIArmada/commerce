<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Actions;

use AIArmada\Engagement\Contracts\EngagementManager;
use Filament\Forms;
use Filament\Actions\Action;

final class RespondAction
{
    public static function make(): Action
    {
        return Action::make('respond')
            ->label('Respond')
            ->icon('heroicon-o-hand-thumb-up')
            ->form([
                Forms\Components\Select::make('response_type')
                    ->options(['interested' => 'Interested', 'going' => 'Going', 'maybe' => 'Maybe', 'not_going' => 'Not Going'])
                    ->required(),
            ])
            ->action(function (array $data, $record) {
                app(EngagementManager::class)->respond(auth()->user(), $record, $data['response_type']);
            });
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Actions;

use AIArmada\Engagement\Contracts\EngagementManager;
use Filament\Forms;
use Filament\Actions\Action;

final class ReactAction
{
    public static function make(): Action
    {
        return Action::make('react')
            ->label('React')
            ->icon('heroicon-o-star')
            ->form([
                Forms\Components\Select::make('reaction_type')
                    ->options(['like' => 'Like', 'love' => 'Love', 'useful' => 'Useful', 'support' => 'Support', 'insightful' => 'Insightful'])
                    ->required(),
            ])
            ->action(function (array $data, $record) {
                app(EngagementManager::class)->react(auth()->user(), $record, $data['reaction_type']);
            });
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Actions;

use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\FilamentEngagement\Support\ActionRecordResolver;
use Filament\Actions\Action;

final class FollowAction
{
    public static function make(): Action
    {
        return Action::make('follow')
            ->label('Follow')
            ->icon('heroicon-o-heart')
            ->action(function ($livewire, $record): void {
                $record = ActionRecordResolver::resolveOrFail($record);
                app(EngagementManager::class)->follow(auth()->user(), $record);
            });
    }
}

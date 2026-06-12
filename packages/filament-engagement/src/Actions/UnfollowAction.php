<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Actions;

use AIArmada\Engagement\Contracts\EngagementManager;
use Filament\Actions\Action;

final class UnfollowAction
{
    public static function make(): Action
    {
        return Action::make('unfollow')
            ->label('Unfollow')
            ->icon('heroicon-o-heart-slash')
            ->color('danger')
            ->requiresConfirmation()
            ->action(function ($livewire, $record) {
                app(EngagementManager::class)->unfollow(auth()->user(), $record);
            });
    }
}

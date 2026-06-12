<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Actions;

use AIArmada\Engagement\Contracts\EngagementManager;
use Filament\Actions\Action;

final class BookmarkAction
{
    public static function make(): Action
    {
        return Action::make('bookmark')
            ->label('Bookmark')
            ->icon('heroicon-o-bookmark')
            ->action(function ($livewire, $record) {
                app(EngagementManager::class)->bookmark(auth()->user(), $record);
            });
    }
}

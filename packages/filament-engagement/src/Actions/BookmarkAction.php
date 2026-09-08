<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Actions;

use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\FilamentEngagement\Support\ActionRecordResolver;
use Filament\Actions\Action;

final class BookmarkAction
{
    public static function make(): Action
    {
        return Action::make('bookmark')
            ->label('Bookmark')
            ->icon('heroicon-o-bookmark')
            ->action(function ($livewire, $record): void {
                $record = ActionRecordResolver::resolveOrFail($record);
                app(EngagementManager::class)->bookmark(auth()->user(), $record);
            });
    }
}

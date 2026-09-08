<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Actions;

use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\FilamentEngagement\Support\ActionRecordResolver;
use Filament\Actions\Action;

final class RemoveBookmarkAction
{
    public static function make(): Action
    {
        return Action::make('removeBookmark')
            ->label('Remove Bookmark')
            ->icon('heroicon-o-bookmark-slash')
            ->color('danger')
            ->requiresConfirmation()
            ->action(function ($livewire, $record): void {
                $record = ActionRecordResolver::resolveOrFail($record);
                app(EngagementManager::class)->removeBookmark(auth()->user(), $record);
            });
    }
}

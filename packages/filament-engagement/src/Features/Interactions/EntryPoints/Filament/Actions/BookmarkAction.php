<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Features\Interactions\EntryPoints\Filament\Actions;

use AIArmada\Engagement\Features\Interactions\Actions\ManageInteractionsAction;
use AIArmada\FilamentEngagement\Support\ActionRecordResolver;
use AIArmada\FilamentEngagement\Support\AuthenticatedUser;
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
                app(ManageInteractionsAction::class)->bookmark(AuthenticatedUser::resolve(), $record);
            });
    }
}

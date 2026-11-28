<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Features\Subscriptions\EntryPoints\Filament\Actions;

use AIArmada\Engagement\Features\Subscriptions\Actions\ManageSubscriptionsAction;
use AIArmada\FilamentEngagement\Support\ActionRecordResolver;
use AIArmada\FilamentEngagement\Support\AuthenticatedUser;
use Filament\Actions\Action;

final class SubscribeAction
{
    public static function make(): Action
    {
        return Action::make('subscribe')
            ->label('Subscribe')
            ->icon('heroicon-o-bell')
            ->action(function ($livewire, $record): void {
                $record = ActionRecordResolver::resolveOrFail($record);
                app(ManageSubscriptionsAction::class)->subscribe(AuthenticatedUser::resolve(), $record);
            });
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Actions;

use AIArmada\Engagement\Contracts\SubscriptionManager;
use AIArmada\FilamentEngagement\Support\ActionRecordResolver;
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
                app(SubscriptionManager::class)->subscribe(auth()->user(), $record);
            });
    }
}

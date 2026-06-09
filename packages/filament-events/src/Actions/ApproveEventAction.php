<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Actions;

use AIArmada\Events\Contracts\EventModerationWorkflow;
use AIArmada\Events\Models\Event;
use AIArmada\FilamentEvents\Resources\EventResource;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;

final class ApproveEventAction
{
    public static function make(): Action
    {
        return Action::make('approveEvent')
            ->label('Approve')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Event $record): bool => in_array('approve', EventResource::reviewSchema($record)->actions, true))
            ->requiresConfirmation()
            ->action(function (Event $record): void {
                $actor = auth()->user();
                app(EventModerationWorkflow::class)->approve($record, $actor instanceof Model ? $actor : null, [
                    'source' => 'filament',
                ]);
            });
    }
}

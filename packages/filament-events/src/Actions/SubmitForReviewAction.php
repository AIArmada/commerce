<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Actions;

use AIArmada\Events\Contracts\EventModerationWorkflow;
use AIArmada\Events\Models\Event;
use AIArmada\FilamentEvents\Resources\EventResource;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;

final class SubmitForReviewAction
{
    public static function make(): Action
    {
        return Action::make('submitForReview')
            ->label('Submit for Review')
            ->icon('heroicon-o-paper-airplane')
            ->color('primary')
            ->visible(fn (Event $record): bool => in_array('submit', EventResource::reviewSchema($record)->actions, true))
            ->requiresConfirmation()
            ->action(function (Event $record): void {
                $actor = auth()->user();
                app(EventModerationWorkflow::class)->submit($record, $actor instanceof Model ? $actor : null, [
                    'source' => 'filament',
                ]);
            });
    }
}

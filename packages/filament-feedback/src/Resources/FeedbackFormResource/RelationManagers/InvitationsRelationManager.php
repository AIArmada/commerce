<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Resources\FeedbackFormResource\RelationManagers;

use AIArmada\Feedback\Enums\FeedbackInvitationStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

final class InvitationsRelationManager extends RelationManager
{
    protected static string $relationship = 'invitations';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'sent' => 'info',
                        'opened' => 'warning',
                        'started' => 'warning',
                        'submitted' => 'success',
                        'expired' => 'danger',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('sent_at')->dateTime(),
                Tables\Columns\TextColumn::make('opened_at')->dateTime(),
                Tables\Columns\TextColumn::make('submitted_at')->dateTime(),
                Tables\Columns\TextColumn::make('expires_at')->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(FeedbackInvitationStatus::options()),
            ]);
    }
}

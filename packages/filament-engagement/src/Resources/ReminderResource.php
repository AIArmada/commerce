<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\JsonDisplay;
use AIArmada\Engagement\Contracts\ReminderManager;
use AIArmada\Engagement\Models\Reminder;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class ReminderResource extends Resource
{
    protected static ?string $model = Reminder::class;

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-engagement.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-engagement.resources.navigation_sort.reminder');
    }

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-clock';

    public static function getEloquentQuery(): Builder
    {
        return OwnerUiScope::apply(parent::getEloquentQuery(), includeGlobal: false);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('recipient_type')->badge(),
                Tables\Columns\TextColumn::make('recipient_id'),
                Tables\Columns\TextColumn::make('remindable_type')->badge(),
                Tables\Columns\TextColumn::make('remindable_id'),
                Tables\Columns\TextColumn::make('reminder_type')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('channel')->badge(),
                Tables\Columns\TextColumn::make('remind_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('sent_at')->dateTime(),
                Tables\Columns\TextColumn::make('failed_at')->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('reminder_type')
                    ->options([
                        'before_start' => 'Before Start',
                        'when_live_starts' => 'When Live Starts',
                        'when_recording_available' => 'When Recording Available',
                        'custom' => 'Custom',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'scheduled' => 'Scheduled',
                        'sent' => 'Sent',
                        'cancelled' => 'Cancelled',
                        'failed' => 'Failed',
                        'expired' => 'Expired',
                    ]),
                Tables\Filters\SelectFilter::make('channel')
                    ->options([
                        'mail' => 'Mail',
                        'database' => 'Database',
                        'notification' => 'Notification',
                    ]),
            ])
            ->actions([
                Action::make('cancel')
                    ->visible(fn (Reminder $record): bool => in_array(
                        $record->status,
                        [Reminder::STATUS_PENDING, Reminder::STATUS_SCHEDULED],
                        true,
                    ))
                    ->action(fn (Reminder $record) => app(ReminderManager::class)
                        ->cancelReminder(
                            $record->recipient,
                            $record->remindable,
                            $record->reminder_type,
                        ))
                    ->requiresConfirmation(),
                Action::make('mark sent')
                    ->label('Mark Sent')
                    ->action(fn (Reminder $record) => app(ReminderManager::class)->markSent($record))
                    ->requiresConfirmation(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make()->columns(2)->schema([
                    Infolists\Components\TextEntry::make('recipient_type'),
                    Infolists\Components\TextEntry::make('recipient_id'),
                    Infolists\Components\TextEntry::make('remindable_type'),
                    Infolists\Components\TextEntry::make('remindable_id'),
                    Infolists\Components\TextEntry::make('reminder_type'),
                    Infolists\Components\TextEntry::make('status'),
                    Infolists\Components\TextEntry::make('channel'),
                    Infolists\Components\TextEntry::make('remind_at')->dateTime(),
                    Infolists\Components\TextEntry::make('offset_minutes'),
                    Infolists\Components\TextEntry::make('anchor_type'),
                    Infolists\Components\TextEntry::make('anchor_code'),
                    Infolists\Components\TextEntry::make('notification_class'),
                    Infolists\Components\TextEntry::make('sent_at')->dateTime(),
                    Infolists\Components\TextEntry::make('cancelled_at')->dateTime(),
                    Infolists\Components\TextEntry::make('failed_at')->dateTime(),
                    Infolists\Components\TextEntry::make('expires_at')->dateTime(),
                    Infolists\Components\TextEntry::make('failure_reason'),
                    Infolists\Components\TextEntry::make('metadata')
                        ->formatStateUsing(fn (mixed $state): string => JsonDisplay::format($state))
                        ->html(),
                ]),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('recipient_type'),
                Forms\Components\TextInput::make('recipient_id'),
                Forms\Components\TextInput::make('remindable_type'),
                Forms\Components\TextInput::make('remindable_id'),
                Forms\Components\TextInput::make('reminder_type'),
                Forms\Components\TextInput::make('status'),
                Forms\Components\TextInput::make('channel'),
                Forms\Components\DateTimePicker::make('remind_at'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ReminderResource\Pages\ListReminders::route('/'),
            'view' => ReminderResource\Pages\ViewReminder::route('/{record}'),
        ];
    }
}

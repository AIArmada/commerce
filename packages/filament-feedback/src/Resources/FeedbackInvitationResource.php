<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Feedback\Enums\FeedbackInvitationStatus;
use AIArmada\Feedback\Models\FeedbackInvitation;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class FeedbackInvitationResource extends Resource
{
    protected static ?string $model = FeedbackInvitation::class;

    public static function getNavigationGroup(): ?string
    {
        return config('filament-feedback.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-feedback.resources.navigation_sort.feedback_invitation');

        return is_numeric($sort) ? (int) $sort : null;
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-envelope';
    }

    public static function getEloquentQuery(): Builder
    {
        return OwnerUiScope::apply(parent::getEloquentQuery(), includeGlobal: false)
            ->with(['form']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('form.name')
                    ->label('Form')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('effective_status')
                    ->badge()
                    ->color(fn (FeedbackInvitationStatus $state): string => match ($state) {
                        FeedbackInvitationStatus::Pending => 'gray',
                        FeedbackInvitationStatus::Sent => 'info',
                        FeedbackInvitationStatus::Opened => 'warning',
                        FeedbackInvitationStatus::Started => 'warning',
                        FeedbackInvitationStatus::Submitted => 'success',
                        FeedbackInvitationStatus::Expired => 'danger',
                        FeedbackInvitationStatus::Cancelled => 'danger',
                    }),
                Tables\Columns\TextColumn::make('sent_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('opened_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('submitted_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(FeedbackInvitationStatus::options())
                    ->query(function (Builder $query, array $data): Builder {
                        $status = $data['value'] ?? null;

                        if ($status === FeedbackInvitationStatus::Expired->value) {
                            return $query->where(function (Builder $query): void {
                                $query
                                    ->where('status', FeedbackInvitationStatus::Expired->value)
                                    ->orWhere(function (Builder $query): void {
                                        $query
                                            ->whereNotIn('status', self::TERMINAL_STATUSES)
                                            ->whereNotNull('expires_at')
                                            ->where('expires_at', '<=', now());
                                    });
                            });
                        }

                        return $status === null ? $query : $query->where('status', $status);
                    }),
            ]);
    }

    /** @var list<string> */
    private const TERMINAL_STATUSES = [
        FeedbackInvitationStatus::Submitted->value,
        FeedbackInvitationStatus::Cancelled->value,
        FeedbackInvitationStatus::Expired->value,
    ];

    public static function getPages(): array
    {
        return [
            'index' => FeedbackInvitationResource\Pages\ListFeedbackInvitations::route('/'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources;

use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Services\OfferManagementService;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferApplicationResource\Pages\ListAffiliateOfferApplications;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferApplicationResource\Pages\ViewAffiliateOfferApplication;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class AffiliateOfferApplicationResource extends Resource
{
    protected static ?string $model = AffiliateOfferApplication::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static ?string $navigationLabel = 'Applications';

    protected static ?string $modelLabel = 'Application';

    protected static ?string $pluralModelLabel = 'Applications';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliate-network.navigation.group', 'Affiliate Network');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-affiliate-network.navigation.sort', 50) + 3;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Application Details')
                    ->schema([
                        Select::make('offer_id')
                            ->label('Offer')
                            ->relationship('offer', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->withoutGlobalScope('owner_via_site'))
                            ->required()
                            ->disabled(),

                        Select::make('affiliate_id')
                            ->label('Affiliate')
                            ->relationship('affiliate', 'code')
                            ->required()
                            ->disabled(),

                        Select::make('status')
                            ->options([
                                AffiliateOfferApplication::STATUS_PENDING => 'Pending',
                                AffiliateOfferApplication::STATUS_APPROVED => 'Approved',
                                AffiliateOfferApplication::STATUS_REJECTED => 'Rejected',
                                AffiliateOfferApplication::STATUS_REVOKED => 'Revoked',
                            ])
                            ->required(),

                        Textarea::make('reason')
                            ->label('Application Reason')
                            ->disabled()
                            ->columnSpanFull(),

                        Textarea::make('rejection_reason')
                            ->label('Rejection/Revocation Reason')
                            ->columnSpanFull(),

                        TextInput::make('reviewed_by')
                            ->disabled(),

                        DateTimePicker::make('reviewed_at')
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('offer.name')
                    ->label('Offer')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('affiliate.code')
                    ->label('Affiliate')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('affiliate.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        AffiliateOfferApplication::STATUS_APPROVED => 'success',
                        AffiliateOfferApplication::STATUS_PENDING => 'warning',
                        default => 'danger',
                    }),

                Tables\Columns\TextColumn::make('reviewed_by')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('reviewed_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        AffiliateOfferApplication::STATUS_PENDING => 'Pending',
                        AffiliateOfferApplication::STATUS_APPROVED => 'Approved',
                        AffiliateOfferApplication::STATUS_REJECTED => 'Rejected',
                        AffiliateOfferApplication::STATUS_REVOKED => 'Revoked',
                    ]),

                Tables\Filters\SelectFilter::make('offer_id')
                    ->label('Offer')
                    ->relationship('offer', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->withoutGlobalScope('owner_via_site')),
            ])
            ->actions([
                Actions\Action::make('approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (AffiliateOfferApplication $record): bool => $record->isPending())
                    ->action(function (AffiliateOfferApplication $record): void {
                        app(OfferManagementService::class)->approveApplication(
                            $record,
                            static::getReviewerName()
                        );

                        Notification::make()
                            ->title('Application approved')
                            ->success()
                            ->send();
                    }),

                Actions\Action::make('reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->form([
                        Textarea::make('reason')
                            ->label('Rejection Reason')
                            ->required(),
                    ])
                    ->visible(fn (AffiliateOfferApplication $record): bool => $record->isPending())
                    ->action(function (AffiliateOfferApplication $record, array $data): void {
                        app(OfferManagementService::class)->rejectApplication(
                            $record,
                            $data['reason'],
                            static::getReviewerName()
                        );

                        Notification::make()
                            ->title('Application rejected')
                            ->warning()
                            ->send();
                    }),

                Actions\Action::make('revoke')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->form([
                        Textarea::make('reason')
                            ->label('Revocation Reason')
                            ->required(),
                    ])
                    ->visible(fn (AffiliateOfferApplication $record): bool => $record->isApproved())
                    ->action(function (AffiliateOfferApplication $record, array $data): void {
                        app(OfferManagementService::class)->revokeApplication(
                            $record,
                            $data['reason'],
                            static::getReviewerName()
                        );

                        Notification::make()
                            ->title('Application revoked')
                            ->warning()
                            ->send();
                    }),

                Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('approve_selected')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            $service = app(OfferManagementService::class);
                            $reviewer = static::getReviewerName();

                            foreach ($records as $record) {
                                if ($record->isPending()) {
                                    $service->approveApplication($record, $reviewer);
                                }
                            }

                            Notification::make()
                                ->title('Applications approved')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    /**
     * @return Builder<AffiliateOfferApplication>
     */
    public static function getEloquentQuery(): Builder
    {
        // Admin resource: bypass per-affiliate owner scope to show all applications network-wide.
        /** @var Builder<AffiliateOfferApplication> $query */
        $query = parent::getEloquentQuery()
            ->with([
                'offer' => fn ($builder) => $builder->withoutGlobalScope('owner_via_site'),
                'affiliate' => fn ($builder) => $builder->withoutOwnerScope(),
            ]);

        return $query->withoutGlobalScope('owner_via_affiliate');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliateOfferApplications::route('/'),
            'view' => ViewAffiliateOfferApplication::route('/{record}'),
        ];
    }

    private static function getReviewerName(): ?string
    {
        /** @var Authenticatable|null $user */
        $user = auth()->user();

        if ($user === null) {
            return null;
        }

        return method_exists($user, 'getName')
            ? $user->getName()
            : ($user->name ?? $user->getAuthIdentifier());
    }
}

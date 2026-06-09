<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferApplicationResource\Tables;

use AIArmada\AffiliateNetwork\Services\OfferManagementService;
use Filament\Actions;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;

final class AffiliateOfferApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('offer.name')
                    ->label('Offer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('affiliate.code')
                    ->label('Affiliate')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('affiliate.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        default => 'danger',
                    }),

                TextColumn::make('reviewed_by')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('reviewed_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'revoked' => 'Revoked',
                    ]),

                // Admin filter: cross-tenant — show all offers regardless of owner scope.
                SelectFilter::make('offer_id')
                    ->label('Offer')
                    ->relationship('offer', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->withoutGlobalScope('owner_via_site')),
            ])
            ->actions([
                Actions\Action::make('approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => $record->isPending())
                    ->action(function ($record): void {
                        app(OfferManagementService::class)->approveApplication(
                            $record,
                            self::getReviewerName()
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
                    ->visible(fn ($record): bool => $record->isPending())
                    ->action(function ($record, array $data): void {
                        app(OfferManagementService::class)->rejectApplication(
                            $record,
                            $data['reason'],
                            self::getReviewerName()
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
                    ->visible(fn ($record): bool => $record->isApproved())
                    ->action(function ($record, array $data): void {
                        app(OfferManagementService::class)->revokeApplication(
                            $record,
                            $data['reason'],
                            self::getReviewerName()
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
                            $reviewer = self::getReviewerName();

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

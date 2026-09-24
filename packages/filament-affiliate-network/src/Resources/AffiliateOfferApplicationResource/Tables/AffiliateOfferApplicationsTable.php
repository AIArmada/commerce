<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferApplicationResource\Tables;

use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\AffiliateNetwork\Enums\ApplicationStatus;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Models\Concerns\ScopesByBelongsToOwner;
use AIArmada\AffiliateNetwork\Services\OfferManagementService;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Filament\Actions;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

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

                // affiliate.email is a virtual accessor over contact_methods (no email
                // column exists), so it must stay display-only: searchable/sortable
                // would generate SQL against a nonexistent column.
                TextColumn::make('affiliate.email')
                    ->label('Email')
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (ApplicationStatus | string $state): string => match ($state instanceof ApplicationStatus ? $state->value : $state) {
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
                    ->relationship('offer', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->withoutGlobalScope(ScopesByBelongsToOwner::class)),
            ])
            ->actions([
                Actions\Action::make('approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (AffiliateOfferApplication $record): bool => $record->isPending())
                    ->disabled(fn (AffiliateOfferApplication $record): bool => ! self::isOfferSiteVerified($record))
                    ->tooltip(fn (AffiliateOfferApplication $record): ?string => self::isOfferSiteVerified($record)
                        ? null
                        : 'Verify the site before approving its applications.')
                    ->action(function (AffiliateOfferApplication $record): void {
                        self::withApplicationOwnerContext($record, fn (): AffiliateOfferApplication => app(OfferManagementService::class)->approveApplication(
                            $record,
                            self::getReviewerName()
                        ));

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
                            ->required()
                            ->maxLength(2000),
                    ])
                    ->visible(fn (AffiliateOfferApplication $record): bool => $record->isPending())
                    ->action(function (AffiliateOfferApplication $record, array $data): void {
                        self::withApplicationOwnerContext($record, fn (): AffiliateOfferApplication => app(OfferManagementService::class)->rejectApplication(
                            $record,
                            (string) $data['reason'],
                            self::getReviewerName()
                        ));

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
                            ->required()
                            ->maxLength(2000),
                    ])
                    ->visible(fn (AffiliateOfferApplication $record): bool => $record->isApproved())
                    ->action(function (AffiliateOfferApplication $record, array $data): void {
                        self::withApplicationOwnerContext($record, fn (): AffiliateOfferApplication => app(OfferManagementService::class)->revokeApplication(
                            $record,
                            (string) $data['reason'],
                            self::getReviewerName()
                        ));

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
                        ->action(function (Collection $records): void {
                            $service = app(OfferManagementService::class);
                            $reviewer = self::getReviewerName();

                            /** @var AffiliateOfferApplication $record */
                            foreach ($records as $record) {
                                if ($record->isPending()) {
                                    $application = $record;

                                    self::withApplicationOwnerContext($application, fn (): AffiliateOfferApplication => $service->approveApplication($application, $reviewer));
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

    /**
     * Run an application mutation inside the owning affiliate's owner context.
     *
     * The admin table lists applications cross-tenant (scope bypassed), but the
     * domain service re-queries under ScopesByBelongsToOwner. Entering the
     * affiliate's own context makes those re-queries match truthfully when
     * owner scoping is on, instead of 404ing on cross-owner rows. Without an
     * identity resolver the mutations run unscoped.
     *
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    private static function withApplicationOwnerContext(AffiliateOfferApplication $record, callable $callback): mixed
    {
        $affiliate = app(AffiliateIdentityResolver::class)->find((string) $record->affiliate_id);

        return OwnerContext::withOwner($affiliate?->owner(), $callback);
    }

    private static function isOfferSiteVerified(AffiliateOfferApplication $record): bool
    {
        // Verification is a network fact: resolve keys unscoped so ambient
        // admin scope can never disable approval on a verified site.
        $siteId = AffiliateOffer::query()
            ->withoutGlobalScopes()
            ->whereKey($record->offer_id)
            ->value('site_id');

        return $siteId !== null && AffiliateSite::isVerifiedKey($siteId);
    }

    private static function getReviewerName(): ?string
    {
        /** @var Authenticatable|null $user */
        $user = auth()->user();

        if ($user === null) {
            return null;
        }

        $name = method_exists($user, 'getName')
            ? $user->getName()
            : ($user->name ?? $user->getAuthIdentifier());

        return $name === null ? null : (string) $name;
    }
}

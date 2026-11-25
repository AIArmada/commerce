<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Relations;

use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Features\Programs\Api\MembershipAdministration;
use AIArmada\Affiliates\Features\Programs\Api\MembershipReviewApi;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use AIArmada\CommerceSupport\Support\FilamentPermission;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Unexposed;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

use function Filament\authorize;

final class ProgramsRelationManager extends RelationManager
{
    use VerifiesRelationManagerOwnerContext;

    protected static string $relationship = 'programMemberships';

    protected static ?string $title = 'Programs';

    #[Unexposed]
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! FilamentPermission::hasAbility('affiliate.view')) {
            return false;
        }

        try {
            return authorize('viewAny', AffiliateProgramMembership::class, static::shouldCheckPolicyExistence())->allowed();
        } catch (AuthorizationException $exception) {
            return $exception->toResponse()->allowed();
        }
    }

    public function getRelationship(): Relation
    {
        return $this->getOwnerRecord()->hasMany(AffiliateProgramMembership::class, 'affiliate_id')->whereIn('program_id', AffiliateProgram::query()->select('id'));
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('program_id')
                ->relationship('program', 'name')
                ->live()
                ->disabledOn('edit')
                ->searchable()
                ->required(),

            Select::make('tier_id')
                ->relationship('tier', 'name', modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query->where('program_id', $get('program_id')))
                ->searchable()
                ->placeholder('No tiers configured'),

            Select::make('status')
                ->options(MembershipStatus::class)
                ->required()
                ->default(MembershipStatus::Pending),

            DateTimePicker::make('applied_at')
                ->required()
                ->default(CarbonImmutable::now()),

            DateTimePicker::make('approved_at'),

            DateTimePicker::make('expires_at'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('program_id')
            ->columns([
                TextColumn::make('program.name')
                    ->label('Program')
                    ->searchable(),

                TextColumn::make('tier.name')
                    ->label('Tier')
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->badge(),

                TextColumn::make('applied_at')
                    ->label('Applied')
                    ->dateTime(),

                TextColumn::make('approved_at')
                    ->label('Approved')
                    ->dateTime()
                    ->placeholder('—'),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with(['program', 'tier']))
            ->headerActions([
                CreateAction::make()
                    ->authorize(fn (): bool => FilamentPermission::hasAbility('affiliate.update'))
                    ->using(function (array $data): AffiliateProgramMembership {
                        $membership = app(MembershipAdministration::class)->create((string) $this->getOwnerRecord()->getKey(), (string) $data['program_id'], $data);

                        return AffiliateProgramMembership::query()->findOrFail($membership->id);
                    }),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Approve')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->authorize(fn (): bool => FilamentPermission::hasAbility('affiliate.update'))
                    ->visible(fn (AffiliateProgramMembership $record): bool => $record->status === MembershipStatus::Pending)
                    ->action(function (AffiliateProgramMembership $record): void {
                        $membershipId = $record->getKey();

                        if ($membershipId === null || ! app(MembershipReviewApi::class)->approve((string) $membershipId)) {
                            return;
                        }

                        Notification::make()
                            ->title('Membership approved')
                            ->success()
                            ->send();
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->authorize(fn (): bool => FilamentPermission::hasAbility('affiliate.update'))
                    ->visible(fn (AffiliateProgramMembership $record): bool => $record->status === MembershipStatus::Pending)
                    ->action(function (AffiliateProgramMembership $record): void {
                        $membershipId = $record->getKey();

                        if ($membershipId === null || ! app(MembershipReviewApi::class)->reject((string) $membershipId)) {
                            return;
                        }

                        Notification::make()
                            ->title('Membership rejected')
                            ->danger()
                            ->send();
                    }),

                EditAction::make()
                    ->authorize(fn (): bool => FilamentPermission::hasAbility('affiliate.update'))
                    ->using(function (AffiliateProgramMembership $record, array $data): AffiliateProgramMembership {
                        app(MembershipAdministration::class)->update((string) $record->getKey(), $data);

                        return $record->refresh();
                    }),
                DeleteAction::make()
                    ->authorize(fn (): bool => FilamentPermission::hasAbility('affiliate.update'))
                    ->action(fn (AffiliateProgramMembership $record) => app(MembershipAdministration::class)->delete((string) $record->getKey())),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorize(fn (): bool => FilamentPermission::hasAbility('affiliate.update'))
                        ->action(function ($records): void {
                            foreach ($records as $record) {
                                app(MembershipAdministration::class)->delete((string) $record->getKey());
                            }
                        }),
                ]),
            ]);
    }
}

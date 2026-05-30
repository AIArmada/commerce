<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources;

use AIArmada\Affiliates\Models\AffiliateSupportTicket;
use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\FilamentAffiliates\Resources\AffiliateSupportTicketResource\Pages\CreateAffiliateSupportTicket;
use AIArmada\FilamentAffiliates\Resources\AffiliateSupportTicketResource\Pages\EditAffiliateSupportTicket;
use AIArmada\FilamentAffiliates\Resources\AffiliateSupportTicketResource\Pages\ListAffiliateSupportTickets;
use AIArmada\FilamentAffiliates\Resources\AffiliateSupportTicketResource\Pages\ViewAffiliateSupportTicket;
use AIArmada\FilamentAffiliates\Resources\AffiliateSupportTicketResource\RelationManagers\MessagesRelationManager;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class AffiliateSupportTicketResource extends Resource
{
    protected static ?string $model = AffiliateSupportTicket::class;

    protected static ?string $tenantOwnershipRelationshipName = 'owner';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Support Tickets';

    protected static ?string $modelLabel = 'Support Ticket';

    protected static ?string $pluralModelLabel = 'Support Tickets';

    public static function canViewAny(): bool
    {
        return FilamentPermission::hasAbility('affiliate.viewAny');
    }

    public static function canView(Model $record): bool
    {
        return FilamentPermission::hasAbility('affiliate.view');
    }

    public static function canCreate(): bool
    {
        return FilamentPermission::hasAbility('affiliate.create');
    }

    public static function canEdit(Model $record): bool
    {
        return FilamentPermission::hasAbility('affiliate.update');
    }

    public static function canDelete(Model $record): bool
    {
        return FilamentPermission::hasAbility('affiliate.delete');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<AffiliateSupportTicket> $query */
        $query = parent::getEloquentQuery();

        if (! (bool) config('affiliates.owner.enabled', false)) {
            /** @var Builder<Model> $unscopedQuery */
            $unscopedQuery = $query;

            return $unscopedQuery;
        }

        /** @var Builder<Model> $modelQuery */
        $modelQuery = $query;

        return $modelQuery;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Ticket')
                ->schema([
                    Select::make('affiliate_id')
                        ->label('Affiliate')
                        ->relationship('affiliate', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),

                    TextInput::make('subject')
                        ->required()
                        ->maxLength(255),

                    Select::make('category')
                        ->options([
                            'general' => 'General',
                            'billing' => 'Billing',
                            'technical' => 'Technical',
                            'tax' => 'Tax',
                            'account' => 'Account',
                        ])
                        ->required(),

                    Select::make('priority')
                        ->options([
                            'low' => 'Low',
                            'medium' => 'Medium',
                            'high' => 'High',
                            'urgent' => 'Urgent',
                        ])
                        ->required(),

                    Select::make('status')
                        ->options([
                            'open' => 'Open',
                            'pending' => 'Pending',
                            'resolved' => 'Resolved',
                            'closed' => 'Closed',
                        ])
                        ->required()
                        ->default('open'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('affiliate.name')
                    ->label('Affiliate')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('subject')
                    ->searchable()
                    ->limit(40),

                TextColumn::make('category')
                    ->badge(),

                TextColumn::make('priority')
                    ->badge(),

                TextColumn::make('status')
                    ->badge(),

                TextColumn::make('messages_count')
                    ->counts('messages')
                    ->label('Replies')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'pending' => 'Pending',
                        'resolved' => 'Resolved',
                        'closed' => 'Closed',
                    ]),

                SelectFilter::make('category')
                    ->options([
                        'general' => 'General',
                        'billing' => 'Billing',
                        'technical' => 'Technical',
                        'tax' => 'Tax',
                        'account' => 'Account',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Ticket')
                ->schema([
                    TextEntry::make('affiliate.name')->label('Affiliate'),
                    TextEntry::make('subject'),
                    TextEntry::make('category')->badge(),
                    TextEntry::make('priority')->badge(),
                    TextEntry::make('status')->badge(),
                ])
                ->columns(2),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            MessagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliateSupportTickets::route('/'),
            'create' => CreateAffiliateSupportTicket::route('/create'),
            'view' => ViewAffiliateSupportTicket::route('/{record}'),
            'edit' => EditAffiliateSupportTicket::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliates.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-affiliates.resources.navigation_sort.affiliate_support_tickets', 71);
    }
}

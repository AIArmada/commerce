<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerCache;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Data\EventDetailData;
use AIArmada\Events\Data\EventReviewSchemaData;
use AIArmada\Events\Enums\EventModerationStatus;
use AIArmada\Events\Enums\EventStatus;
use AIArmada\Events\Enums\EventVisibility;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Services\EventQueryService;
use AIArmada\Events\Support\Policy\EventModerationPolicy;
use AIArmada\FilamentEvents\Actions\ApproveEventAction;
use AIArmada\FilamentEvents\Actions\RejectEventAction;
use AIArmada\FilamentEvents\Actions\RequestChangesAction;
use AIArmada\FilamentEvents\Actions\SubmitForReviewAction;
use AIArmada\FilamentEvents\Resources\EventResource\Pages;
use AIArmada\FilamentEvents\Resources\EventResource\RelationManagers;
use AIArmada\FilamentEvents\Resources\EventResource\Schemas\EventForm;
use AIArmada\FilamentEvents\Resources\EventResource\Schemas\EventInfolist;
use AIArmada\FilamentEvents\Resources\EventResource\Tables\EventTable;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return (string) config('filament-events.navigation.group', 'Events');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-events.navigation.resources.events', 2);
    }

    /**
     * @return Builder<Event>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Event> $query */
        $query = parent::getEloquentQuery();

        return OwnerUiScope::apply($query, includeGlobal: false)
            ->with(['series', 'product'])
            ->withCount('occurrences');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = OwnerCache::remember(
            OwnerContext::resolve(),
            'filament-events.nav-badge.active',
            CarbonImmutable::now()->addSeconds(30),
            fn (): int => static::getEloquentQuery()->where('status', EventStatus::Active)->count(),
        );

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return EventForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EventTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EventInfolist::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OccurrencesRelationManager::class,
            RelationManagers\EventPeopleRelationManager::class,
            RelationManagers\EventOrganizersRelationManager::class,
            RelationManagers\EventSpeakersRelationManager::class,
            RelationManagers\EventSponsorsRelationManager::class,
            RelationManagers\EventSubmissionsRelationManager::class,
            RelationManagers\EventReviewsRelationManager::class,
            RelationManagers\EventChangesRelationManager::class,
            RelationManagers\EventAssetsRelationManager::class,
            RelationManagers\EventClassificationsRelationManager::class,
            RelationManagers\EventEngagementsRelationManager::class,
            RelationManagers\EventAttendanceRelationManager::class,
            RelationManagers\EventAgendasRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEvents::route('/'),
            'create' => Pages\CreateEvent::route('/create'),
            'view' => Pages\ViewEvent::route('/{record}'),
            'edit' => Pages\EditEvent::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug', 'summary', 'description'];
    }

    public static function reviewSchema(Event $event): EventReviewSchemaData
    {
        return app(EventQueryService::class)->reviewSchema($event);
    }

    public static function submitForReviewAction(): Action
    {
        return SubmitForReviewAction::make();
    }

    public static function approveAction(): Action
    {
        return ApproveEventAction::make();
    }

    public static function requestChangesAction(): Action
    {
        return RequestChangesAction::make();
    }

    public static function rejectEventAction(): Action
    {
        return RejectEventAction::make();
    }

    /**
     * @return array<string, string>
     */
    public static function moderationStatusOptions(): array
    {
        return collect(EventModerationStatus::cases())
            ->mapWithKeys(fn (EventModerationStatus $status): array => [$status->value => $status->label()])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function visibilityOptions(): array
    {
        return collect(EventVisibility::cases())
            ->mapWithKeys(fn (EventVisibility $visibility): array => [$visibility->value => $visibility->label()])
            ->all();
    }

    public static function snapshot(Event $event): EventDetailData
    {
        return app(EventQueryService::class)->detail($event);
    }

    /**
     * @return array<string, string>
     */
    public static function reasonCodeOptions(): array
    {
        $codes = EventModerationPolicy::reasonCodes();

        $options = [];
        foreach ($codes as $key => $payload) {
            $label = is_array($payload) ? ($payload['label'] ?? $key) : $payload;
            $options[(string) $key] = (string) $label;
        }

        return $options;
    }
}

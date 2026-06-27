<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Pages;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Customers\Models\Segment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use UnitEnum;

class SegmentRebuildPage extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected string $view = 'filament-customers::pages.segment-rebuild';

    protected static ?string $slug = 'segment-rebuild';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-customers.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-customers.pages.navigation_sort.segment_rebuild');

        return is_numeric($sort) ? (int) $sort : null;
    }

    public static function getNavigationLabel(): string
    {
        return 'Rebuild Segments';
    }

    public function getTitle(): string
    {
        return 'Segment Rebuild';
    }

    /**
     * @return array<int, array{id: string, name: string, type: string, customer_count: int}>
     */
    public function getSegments(): array
    {
        $query = Segment::query();

        if ((bool) config('customers.features.owner.enabled', false)) {
            $query = OwnerUiScope::apply($query, includeGlobal: false);
        }

        return $query->get()
            ->map(fn (Segment $segment) => [
                'id' => $segment->id,
                'name' => $segment->name,
                'type' => $segment->is_automatic ? 'Automatic' : 'Manual',
                'customer_count' => $segment->customers()->count(),
            ])
            ->all();
    }

    public function rebuildSegment(string $segmentId): void
    {
        $segment = (bool) config('customers.features.owner.enabled', false)
            ? OwnerWriteGuard::findOrFailForOwner(Segment::class, $segmentId, includeGlobal: false)
            : Segment::find($segmentId);

        if ($segment === null) {
            Notification::make()
                ->title('Segment not found')
                ->danger()
                ->send();

            return;
        }

        if (! $segment->is_automatic) {
            Notification::make()
                ->title('Manual segments cannot be rebuilt')
                ->warning()
                ->send();

            return;
        }

        Artisan::call('customers:rebuild-segment', ['segment' => $segmentId]);

        Notification::make()
            ->title("Segment '{$segment->name}' rebuild initiated")
            ->success()
            ->send();
    }

    public function rebuildAllSegments(): void
    {
        Artisan::call('customers:rebuild-segments');

        Notification::make()
            ->title('All automatic segment rebuilds initiated')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('rebuild_all')
                ->label('Rebuild All Automatic')
                ->icon('heroicon-o-arrow-path')
                ->action('rebuildAllSegments')
                ->requiresConfirmation(),
        ];
    }
}

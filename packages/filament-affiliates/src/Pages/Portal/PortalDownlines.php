<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Pages\Portal;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\FilamentAffiliates\Concerns\PortalPage;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class PortalDownlines extends PortalPage implements HasTable
{
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUsers;

    /** @var view-string */
    protected string $view = 'filament-affiliates::pages.portal.downlines';

    public static function getNavigationLabel(): string
    {
        return __('Network');
    }

    public function getTitle(): string | Htmlable
    {
        return __('Your Network');
    }

    public function table(Table $table): Table
    {
        $affiliate = $this->getAffiliate();

        if (! $affiliate) {
            return $table
                ->query(Affiliate::query()->whereNull('id'))
                ->columns([])
                ->emptyStateHeading(__('No affiliate account'))
                ->emptyStateDescription(__('You need an affiliate account to view your network.'));
        }

        return $table
            ->query(
                Affiliate::query()
                    ->where('parent_affiliate_id', $affiliate->getKey())
            )
            ->columns([
                TextColumn::make('code')
                    ->label(__('Code'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('rank.name')
                    ->label(__('Rank'))
                    ->placeholder('—'),

                TextColumn::make('conversions_count')
                    ->label(__('Conversions'))
                    ->numeric()
                    ->sortable(),

                TextColumn::make('direct_downline_count')
                    ->label(__('Network'))
                    ->numeric()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('Joined'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->modifyQueryUsing(fn ($query) => $query->withCount('conversions'))
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $affiliate = $this->getAffiliate();

        return [
            'hasAffiliate' => $this->hasAffiliate(),
            'totalDownlines' => $affiliate?->total_downline_count ?? 0,
            'directDownlines' => $affiliate?->direct_downline_count ?? 0,
        ];
    }
}

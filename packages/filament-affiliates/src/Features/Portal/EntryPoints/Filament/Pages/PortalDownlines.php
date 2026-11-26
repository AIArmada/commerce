<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Portal\EntryPoints\Filament\Pages;

use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\FilamentAffiliates\Features\Portal\EntryPoints\Filament\Concerns\PortalPage;
use AIArmada\FilamentAffiliates\Support\ConversionCountProjection;
use AIArmada\FilamentAffiliates\Support\RankLabelProjection;
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
                    ->addSelect(['rank_name' => RankLabelProjection::query()])
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

                TextColumn::make('rank_name')
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
            ->modifyQueryUsing(fn ($query) => $query->addSelect(['conversions_count' => ConversionCountProjection::query()]))
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

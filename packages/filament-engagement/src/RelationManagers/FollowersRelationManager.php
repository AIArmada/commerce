<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\RelationManagers;

use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FollowersRelationManager extends RelationManager
{
    use VerifiesRelationManagerOwnerContext;

    protected static string $relationship = 'follows';

    protected static ?string $title = 'Followers';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $this->modifyFollowersQuery($query))
            ->columns($this->getFollowersColumns())
            ->filters($this->getFollowersFilters())
            ->headerActions($this->getFollowersHeaderActions())
            ->actions($this->getFollowersRecordActions());
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function modifyFollowersQuery(Builder $query): Builder
    {
        return $query;
    }

    /**
     * @return array<int, Tables\Columns\Column>
     */
    protected function getFollowersColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('follower_type')->badge(),
            Tables\Columns\TextColumn::make('follower_id'),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('followed_at')->dateTime()->sortable(),
        ];
    }

    /**
     * @return array<int, Tables\Filters\BaseFilter>
     */
    protected function getFollowersFilters(): array
    {
        return [
            Tables\Filters\SelectFilter::make('status')->options([
                'active' => 'Active',
                'muted' => 'Muted',
                'unfollowed' => 'Unfollowed',
            ]),
        ];
    }

    /**
     * @return array<int, Action>
     */
    protected function getFollowersHeaderActions(): array
    {
        return [];
    }

    /**
     * @return array<int, Action>
     */
    protected function getFollowersRecordActions(): array
    {
        return [];
    }
}

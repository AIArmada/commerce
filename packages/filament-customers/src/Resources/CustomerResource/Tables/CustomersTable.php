<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Resources\CustomerResource\Tables;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\Segment;
use Carbon\CarbonImmutable;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('Customer')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable()
                    ->description(fn ($record) => $record->email),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => $state->color()),

                TextColumn::make('company')
                    ->label('Company')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),

                IconColumn::make('accepts_marketing')
                    ->label('Marketing')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('segments.name')
                    ->label('Segments')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(
                        collect(CustomerStatus::cases())
                            ->mapWithKeys(fn ($status) => [$status->value => $status->label()])
                    ),

                TernaryFilter::make('accepts_marketing')
                    ->label('Accepts Marketing'),

                SelectFilter::make('segments')
                    ->options(static fn (): array => OwnerUiScope::apply(Segment::query(), includeGlobal: false)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->query(static function (Builder $query, array $data): Builder {
                        $values = array_values(array_filter($data['values'] ?? []));

                        if ($values === []) {
                            return $query;
                        }

                        return $query->whereHas('segments', static function (Builder $segmentQuery) use ($values): void {
                            OwnerUiScope::apply($segmentQuery, includeGlobal: false)
                                ->whereKey($values);
                        });
                    })
                    ->multiple()
                    ->preload(),

                Filter::make('recent')
                    ->label('New (Last 30 days)')
                    ->query(fn ($query) => $query->where('created_at', '>=', CarbonImmutable::now()->subDays(30))),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('opt_in_marketing')
                        ->label('Opt-In Marketing')
                        ->icon('heroicon-o-bell')
                        ->action(function (Collection $records): void {
                            $user = Filament::auth()->user();
                            abort_unless($user !== null, 403);

                            $records->each(function (Customer $record) use ($user): void {
                                Gate::forUser($user)->authorize('update', $record);
                                $record->optInMarketing();
                            });
                        }),
                    BulkAction::make('opt_out_marketing')
                        ->label('Opt-Out Marketing')
                        ->icon('heroicon-o-bell-slash')
                        ->action(function (Collection $records): void {
                            $user = Filament::auth()->user();
                            abort_unless($user !== null, 403);

                            $records->each(function (Customer $record) use ($user): void {
                                Gate::forUser($user)->authorize('update', $record);
                                $record->optOutMarketing();
                            });
                        }),
                ]),
            ]);
    }
}

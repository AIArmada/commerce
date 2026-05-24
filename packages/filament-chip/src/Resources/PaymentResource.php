<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources;

use AIArmada\Chip\Models\Payment;
use AIArmada\FilamentChip\Resources\PaymentResource\Pages\ListPayments;
use AIArmada\FilamentChip\Resources\PaymentResource\Pages\ViewPayment;
use AIArmada\FilamentChip\Resources\PaymentResource\Schemas\PaymentInfolist;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Layout\Component;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Override;

final class PaymentResource extends BaseChipResource
{
    protected static ?string $model = Payment::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $modelLabel = 'Payment';

    protected static ?string $pluralModelLabel = 'Payments';

    protected static ?string $recordTitleAttribute = 'id';

    #[Override]
    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                self::glowingSplit([
                    self::cardedStack([
                        TextColumn::make('id')
                            ->label('Payment #')
                            ->copyable()
                            ->searchable()
                            ->icon('heroicon-o-hashtag'),
                        TextColumn::make('purchase_id')
                            ->label('Purchase')
                            ->searchable()
                            ->icon('heroicon-o-receipt-refund'),
                        TextColumn::make('payment_type')
                            ->label('Type')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'refund' => 'warning',
                                'payout' => 'info',
                                default => 'primary',
                            }),
                    ]),
                    self::softShadowPanel([
                        self::cardedStack([
                            TextColumn::make('formatted_amount')
                                ->label('Amount')
                                ->badge()
                                ->color('primary')
                                ->weight(FontWeight::SemiBold),
                            TextColumn::make('formatted_net_amount')
                                ->label('Net Amount')
                                ->icon('heroicon-o-banknotes')
                                ->placeholder('—'),
                            TextColumn::make('formatted_fee_amount')
                                ->label('Fees')
                                ->icon('heroicon-o-receipt-percent')
                                ->color('warning')
                                ->placeholder('—'),
                        ]),
                    ]),
                    self::cardedStack([
                        TextColumn::make('currency')
                            ->label('Currency')
                            ->badge(),
                        TextColumn::make('paid_on')
                            ->label('Paid On')
                            ->dateTime(config('filament-chip.tables.created_on_format', 'Y-m-d H:i:s'))
                            ->placeholder('—'),
                        TextColumn::make('created_on')
                            ->label('Created')
                            ->dateTime(config('filament-chip.tables.created_on_format', 'Y-m-d H:i:s'))
                            ->since()
                            ->icon('heroicon-o-clock'),
                        IconColumn::make('is_outgoing')
                            ->label('Outgoing')
                            ->boolean()
                            ->trueColor('info')
                            ->falseColor('success'),
                    ]),
                ]),
            ])
            ->filters([
                SelectFilter::make('payment_type')
                    ->label('Type')
                    ->options([
                        'purchase' => 'Purchase',
                        'refund' => 'Refund',
                        'payout' => 'Payout',
                    ]),
                SelectFilter::make('currency')
                    ->label('Currency')
                    ->options(function (): array {
                        $query = Payment::query();

                        if (method_exists($query->getModel(), 'scopeForOwner')) {
                            $query->forOwner();
                        }

                        return $query
                            ->select('currency')
                            ->distinct()
                            ->orderBy('currency')
                            ->pluck('currency', 'currency')
                            ->filter()
                            ->all();
                    }),
                Filter::make('is_outgoing')
                    ->label('Outgoing Only')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('is_outgoing', true)),
                Filter::make('paid')
                    ->label('Paid Transactions')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('paid_on')),
            ], layout: FiltersLayout::AboveContent)
            ->actions([
                ViewAction::make()
                    ->icon('heroicon-o-eye'),
            ])
            ->bulkActions([])
            ->defaultSort('created_on', 'desc')
            ->paginated([25, 50, 100])
            ->poll(self::pollingInterval());
    }

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return PaymentInfolist::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }

    protected static function navigationSortKey(): string
    {
        return 'payments';
    }

    /**
     * @param  array<int, Column|ColumnGroup|Component>  $components
     */
    private static function glowingSplit(array $components): Split
    {
        return Split::make($components)->extraAttributes([
            'class' => 'after:absolute after:inset-0 after:-z-10 after:rounded-2xl after:bg-gradient-to-r after:from-primary-500/20 after:to-transparent',
        ]);
    }

    /**
     * @param  array<int, Column|ColumnGroup|Component>  $components
     */
    private static function softShadowPanel(array $components): Panel
    {
        return Panel::make($components)->extraAttributes([
            'class' => 'shadow-lg shadow-gray-200/40 ring-1 ring-black/5',
        ]);
    }

    /**
     * @param  array<int, Column|ColumnGroup|Component>  $components
     */
    private static function cardedStack(array $components): Stack
    {
        return Stack::make($components)->extraAttributes([
            'class' => 'rounded-2xl border border-white/60 bg-white/80 p-6 shadow-sm backdrop-blur dark:border-white/10 dark:bg-white/5',
        ]);
    }
}

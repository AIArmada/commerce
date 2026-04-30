<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocResource\RelationManagers;

use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocPayment;
use AIArmada\FilamentDocs\Support\DocsOwnerScope;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

final class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $recordTitleAttribute = 'reference';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->prefix(fn () => config('docs.defaults.currency', 'MYR')),

                Select::make('payment_method')
                    ->options(config('docs.payment_methods', [
                        'bank_transfer' => 'Bank Transfer',
                        'cash' => 'Cash',
                        'credit_card' => 'Credit Card',
                    ]))
                    ->required(),

                TextInput::make('reference')
                    ->maxLength(255),

                TextInput::make('transaction_id')
                    ->label('Transaction ID')
                    ->maxLength(255),

                DateTimePicker::make('paid_at')
                    ->label('Payment Date')
                    ->default(CarbonImmutable::now())
                    ->required(),

                Textarea::make('notes')
                    ->maxLength(500)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference')
            ->columns([
                TextColumn::make('amount')
                    ->money(fn ($record) => $record->currency)
                    ->sortable(),

                TextColumn::make('payment_method')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state))),

                TextColumn::make('reference')
                    ->searchable(),

                TextColumn::make('transaction_id')
                    ->label('Transaction ID')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('paid_at')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('notes')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        return $this->mutatePaymentData($data);
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateFormDataUsing(function (array $data, DocPayment $record): array {
                        $doc = $record->doc;

                        if ($doc !== null) {
                            DocsOwnerScope::assertCanMutateDoc($doc);
                        }

                        return $this->mutatePaymentData($data, $record);
                    }),
                Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (DocPayment $record): void {
                        $doc = $record->doc;

                        if ($doc !== null) {
                            DocsOwnerScope::assertCanMutateDoc($doc);
                        }

                        $record->delete();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('delete_selected')
                        ->label('Delete Selected')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            /** @var Collection<int|string, DocPayment> $records */
                            $records->each(function (DocPayment $record): void {
                                $doc = $record->doc;

                                if ($doc !== null) {
                                    DocsOwnerScope::assertCanMutateDoc($doc);
                                }

                                $record->delete();
                            });
                        }),
                ]),
            ])
            ->defaultSort('paid_at', 'desc');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mutatePaymentData(array $data, ?DocPayment $existing = null): array
    {
        $doc = $this->getOwnerDoc();

        if (! array_key_exists('amount', $data)) {
            throw ValidationException::withMessages([
                'amount' => __('Payment amount is required.'),
            ]);
        }

        $data['currency'] = $doc->currency;
        if ($existing !== null && (string) $existing->doc_id !== (string) $doc->getKey()) {
            throw ValidationException::withMessages([
                'doc_id' => __('Invalid payment record.'),
            ]);
        }

        return $data;
    }

    private function getOwnerDoc(): Doc
    {
        $ownerRecord = $this->getOwnerRecord();

        if (! $ownerRecord instanceof Doc) {
            throw ValidationException::withMessages([
                'doc' => __('Invalid document context.'),
            ]);
        }

        return $ownerRecord;
    }
}

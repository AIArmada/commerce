<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocResource\RelationManagers;

use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocPayment;
use AIArmada\Docs\Services\DocPaymentRecorder;
use AIArmada\FilamentDocs\Support\DocsOwnerScope;
use Carbon\CarbonImmutable;
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
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $recordTitleAttribute = 'reference';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('amount_minor')
                    ->label('Amount (minor units)')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->step(1)
                    ->dehydrateStateUsing(static fn (mixed $state): int => (int) $state),

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
                TextColumn::make('amount_minor')
                    ->formatStateUsing(fn (int | string $state, DocPayment $record): string => MoneyFormatter::formatMinor((int) $state, $record->currency))
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
                    ->using(fn (array $data): DocPayment => static::recordPaymentForDoc($this->getOwnerDoc(), $data)),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(fn (DocPayment $record, array $data): DocPayment => static::updatePaymentForDoc($this->getOwnerDoc(), $record, $data)),
            ])
            ->defaultSort('paid_at', 'desc');
    }

    /**
     * Record a payment through the domain recorder so row locks, currency
     * matching, remaining-balance caps, and status transitions all apply.
     *
     * Payments have no domain reversal: deletes are intentionally unavailable
     * and recorded amounts are immutable once persisted.
     *
     * @param  array<string, mixed>  $data
     */
    public static function recordPaymentForDoc(Doc $doc, array $data): DocPayment
    {
        DocsOwnerScope::assertCanMutateRecord($doc, 'Document not found.');

        try {
            return app(DocPaymentRecorder::class)->record($doc, array_merge($data, [
                'currency' => $doc->currency,
            ]));
        } catch (InvalidArgumentException | ModelNotFoundException $exception) {
            throw ValidationException::withMessages([
                'amount_minor' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function updatePaymentForDoc(Doc $doc, DocPayment $payment, array $data): DocPayment
    {
        DocsOwnerScope::assertCanMutateRecord($doc, 'Document not found.');
        DocsOwnerScope::assertCanMutateRecord($payment, 'Payment not found.');

        if ((string) $payment->doc_id !== (string) $doc->getKey()) {
            throw ValidationException::withMessages([
                'doc_id' => __('Invalid payment record.'),
            ]);
        }

        foreach (['amount_minor', 'currency', 'payment_method'] as $immutable) {
            if (array_key_exists($immutable, $data)
                && (string) $data[$immutable] !== (string) $payment->getAttribute($immutable)) {
                throw ValidationException::withMessages([
                    $immutable => __('This field cannot be changed after recording.'),
                ]);
            }
        }

        $payment->update(array_intersect_key($data, array_flip([
            'reference',
            'transaction_id',
            'notes',
            'paid_at',
        ])));

        return $payment;
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

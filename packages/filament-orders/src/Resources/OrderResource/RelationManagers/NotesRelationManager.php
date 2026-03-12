<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders\Resources\OrderResource\RelationManagers;

use AIArmada\Orders\Models\Order;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

final class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'orderNotes';

    protected static ?string $title = 'Notes';

    private function getOrderRecordOrNull(): ?Order
    {
        if (! isset($this->ownerRecord)) {
            return null;
        }

        $record = $this->getOwnerRecord();

        return $record instanceof Order ? $record : null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Textarea::make('content')
                    ->label('Note')
                    ->required()
                    ->rows(3)
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('is_customer_visible')
                    ->label('Visible to Customer')
                    ->default(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('content')
                    ->label('Note')
                    ->wrap()
                    ->limit(100),

                Tables\Columns\IconColumn::make('is_customer_visible')
                    ->label('Customer Visible')
                    ->boolean()
                    ->trueIcon('heroicon-o-eye')
                    ->falseIcon('heroicon-o-eye-slash'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_customer_visible')
                    ->label('Visibility')
                    ->trueLabel('Customer Visible')
                    ->falseLabel('Internal Only'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->authorize(function (): bool {
                        $user = Filament::auth()->user();

                        $order = $this->getOrderRecordOrNull();

                        if ($user === null || $order === null) {
                            return false;
                        }

                        return Gate::forUser($user)->allows('addNote', $order);
                    })
                    ->mutateFormDataUsing(function (array $data): array {
                        $userId = Filament::auth()->id();

                        $data['user_id'] = $userId ? (string) $userId : null;

                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->authorize(function (): bool {
                        $user = Filament::auth()->user();

                        $order = $this->getOrderRecordOrNull();

                        if ($user === null || $order === null) {
                            return false;
                        }

                        return Gate::forUser($user)->allows('update', $order);
                    }),
                DeleteAction::make()
                    ->authorize(function (): bool {
                        $user = Filament::auth()->user();

                        $order = $this->getOrderRecordOrNull();

                        if ($user === null || $order === null) {
                            return false;
                        }

                        return Gate::forUser($user)->allows('update', $order);
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorize(function (): bool {
                            $user = Filament::auth()->user();

                            $order = $this->getOrderRecordOrNull();

                            if ($user === null || $order === null) {
                                return false;
                            }

                            return Gate::forUser($user)->allows('update', $order);
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}

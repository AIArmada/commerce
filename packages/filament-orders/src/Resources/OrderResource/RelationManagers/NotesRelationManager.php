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

                Forms\Components\Select::make('visibility')
                    ->label('Visibility')
                    ->options([
                        'internal' => 'Internal Only',
                        'customer' => 'Customer Visible',
                    ])
                    ->default('internal')
                    ->required(),
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

                Tables\Columns\TextColumn::make('visibility')
                    ->label('Visibility')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'customer' => 'success',
                        'internal' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'customer' => 'Customer Visible',
                        'internal' => 'Internal Only',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('visibility')
                    ->label('Visibility')
                    ->options([
                        'internal' => 'Internal Only',
                        'customer' => 'Customer Visible',
                    ]),
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

<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders\Resources\OrderResource\RelationManagers;

use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use AIArmada\Orders\Contracts\OrderNotes;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderNote;
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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

final class NotesRelationManager extends RelationManager
{
    use VerifiesRelationManagerOwnerContext;

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
                    ->using(function (array $data): OrderNote {
                        $order = $this->getOrderRecordOrNull();
                        abort_unless($order instanceof Order, 404);
                        $actor = Filament::auth()->id();
                        $id = app(OrderNotes::class)->add((string) $order->getKey(), $data['content'], $data['visibility'], $actor === null ? null : (string) $actor);

                        return $order->orderNotes()->findOrFail($id);
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
                    })
                    ->using(function (OrderNote $record, array $data): OrderNote {
                        $order = $this->getOrderRecordOrNull();
                        abort_unless($order instanceof Order, 404);
                        app(OrderNotes::class)->edit((string) $order->getKey(), (string) $record->getKey(), $data['content'], $data['visibility']);

                        return $record->refresh();
                    }),
                DeleteAction::make()
                    ->authorize(function (): bool {
                        $user = Filament::auth()->user();

                        $order = $this->getOrderRecordOrNull();

                        if ($user === null || $order === null) {
                            return false;
                        }

                        return Gate::forUser($user)->allows('update', $order);
                    })
                    ->using(function (OrderNote $record): bool {
                        $order = $this->getOrderRecordOrNull();
                        abort_unless($order instanceof Order, 404);
                        app(OrderNotes::class)->remove((string) $order->getKey(), [(string) $record->getKey()]);

                        return true;
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
                        })
                        ->action(function (Collection $records): void {
                            $order = $this->getOrderRecordOrNull();
                            abort_unless($order instanceof Order, 404);
                            app(OrderNotes::class)->remove((string) $order->getKey(), array_map('strval', $records->modelKeys()));
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}

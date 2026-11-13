<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource\Pages;

use AIArmada\FilamentShipping\Actions\ApproveReturnAction;
use AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource;
use AIArmada\Shipping\Integrations\PackageReturns;
use AIArmada\Shipping\Models\ReturnAuthorization;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;

class ViewReturnAuthorization extends ViewRecord
{
    protected static string $resource = ReturnAuthorizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn (ReturnAuthorization $record): bool => ! $record->isPackageReturn()),
            ApproveReturnAction::make(),
            Action::make('receive_package')
                ->label('Receive and Inspect Package')
                ->authorize(fn (ReturnAuthorization $record): bool => auth()->user()?->can('update', $record) ?? false)
                ->visible(fn (ReturnAuthorization $record): bool => $record->isPackageReturn() && $record->isApproved())
                ->schema(fn (ReturnAuthorization $record): array => [
                    ...$record->items()->get()->map(fn ($item): Section => Section::make($item->name)
                        ->schema([
                            TextInput::make("inspection.{$item->id}.quantity")
                                ->label('Quantity received')
                                ->integer()
                                ->minValue(0)
                                ->maxValue($item->quantity_approved)
                                ->required(),
                            Select::make("inspection.{$item->id}.condition")
                                ->options([
                                    'unopened' => 'Unopened',
                                    'opened' => 'Opened / used',
                                    'damaged' => 'Damaged',
                                    'defective' => 'Defective',
                                    'mixed' => 'Mixed sellable and unsellable',
                                ])
                                ->required(),
                            TextInput::make("inspection.{$item->id}.restock_quantity")
                                ->label('Sellable quantity')
                                ->integer()
                                ->minValue(0)
                                ->maxValue($item->quantity_approved)
                                ->helperText('Leave empty to restock all unopened components and zero for other conditions.'),
                        ]))->all(),
                    TextInput::make('location_id')->label('Receiving inventory location ID')->uuid(),
                ])
                ->action(function (ReturnAuthorization $record, array $data): void {
                    $inspection = array_map(function (array $result): array {
                        $result['quantity'] = (int) $result['quantity'];
                        if (isset($result['restock_quantity'])) {
                            $result['restock_quantity'] = (int) $result['restock_quantity'];
                        }

                        return $result;
                    }, $data['inspection']);
                    $this->record = app(PackageReturns::class)->receive($record, $inspection, $data['location_id'] ?? null);
                }),
            Action::make('complete_package_return')
                ->label('Complete Package Return')
                ->authorize(fn (ReturnAuthorization $record): bool => auth()->user()?->can('update', $record) ?? false)
                ->visible(fn (ReturnAuthorization $record): bool => $record->isPackageReturn() && $record->isReceived())
                ->schema([
                    TextInput::make('transaction_id')
                        ->label('Settled refund transaction ID')
                        ->helperText('Leave empty if the refund is already recorded or the unit was free.')
                        ->maxLength(255),
                ])
                ->action(function (ReturnAuthorization $record, array $data): void {
                    $this->record = app(PackageReturns::class)->complete($record, ($data['transaction_id'] ?? '') ?: null);
                }),
        ];
    }
}

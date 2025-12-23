<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Resources\CustomerResource\Pages;

use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Policies\CustomerPolicy;
use AIArmada\FilamentCustomers\Resources\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('add_credit')
                ->label('Add Credit')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->modalHeading('Add Store Credit')
                ->form([
                    \Filament\Forms\Components\TextInput::make('amount')
                        ->label('Amount (RM)')
                        ->numeric()
                        ->required()
                        ->minValue(0.01)
                        ->prefix('RM'),

                    \Filament\Forms\Components\Textarea::make('reason')
                        ->label('Reason')
                        ->rows(2),
                ])
                ->action(function (Customer $record, array $data): void {
                    $user = Auth::user();
                    abort_unless($user !== null, 403);

                    $policy = new CustomerPolicy;
                    abort_unless($policy->update($user, $record), 403);

                    $amountInCents = (int) ($data['amount'] * 100);
                    $record->addCredit($amountInCents, $data['reason'] ?? null);

                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Credit Added')
                        ->body("RM {$data['amount']} added to wallet.")
                        ->send();
                }),
            Actions\Action::make('deduct_credit')
                ->label('Deduct Credit')
                ->icon('heroicon-o-minus-circle')
                ->color('danger')
                ->modalHeading('Deduct Store Credit')
                ->form([
                    \Filament\Forms\Components\TextInput::make('amount')
                        ->label('Amount (RM)')
                        ->numeric()
                        ->required()
                        ->minValue(0.01)
                        ->prefix('RM'),

                    \Filament\Forms\Components\Textarea::make('reason')
                        ->label('Reason')
                        ->rows(2),
                ])
                ->action(function (Customer $record, array $data): void {
                    $user = Auth::user();
                    abort_unless($user !== null, 403);

                    $policy = new CustomerPolicy;
                    abort_unless($policy->update($user, $record), 403);

                    $amountInCents = (int) ($data['amount'] * 100);

                    if (! $record->deductCredit($amountInCents, $data['reason'] ?? null)) {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('Insufficient Balance')
                            ->body('Cannot deduct more than available balance.')
                            ->send();

                        return;
                    }

                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Credit Deducted')
                        ->body("RM {$data['amount']} deducted from wallet.")
                        ->send();
                }),
        ];
    }
}

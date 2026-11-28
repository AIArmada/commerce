<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Resources\UnifiedSubscriptionResource\Pages;

use AIArmada\Cashier\Actions\CreateCustomerSubscription;
use AIArmada\FilamentCashier\Resources\UnifiedSubscriptionResource;
use AIArmada\FilamentCashier\Resources\UnifiedSubscriptionResource\Schemas\SubscriptionForm;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

final class CreateSubscription extends CreateRecord
{
    protected static string $resource = UnifiedSubscriptionResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema(SubscriptionForm::schema())
            ->columns(1);
    }

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateCustomerSubscription::class)->handle($data);
    }

    protected function getCreatedNotification(): ?Notification
    {
        $gateway = $this->data['gateway'] ?? 'unknown';

        return Notification::make()
            ->title(__('filament-cashier::subscriptions.create.success', [
                'gateway' => ucfirst($gateway),
            ]))
            ->success();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

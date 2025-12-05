<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Actions;

use AIArmada\Vouchers\GiftCards\Enums\GiftCardType;
use AIArmada\Vouchers\GiftCards\Services\GiftCardService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

final class BulkIssueGiftCardsAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'bulk_issue';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Bulk Issue');
        $this->icon(Heroicon::OutlinedSquare2Stack);
        $this->color('primary');
        $this->modalHeading('Bulk Issue Gift Cards');
        $this->modalDescription('Create multiple gift cards at once with the same configuration.');

        $currencyOptions = [
            'MYR' => 'MYR',
            'USD' => 'USD',
            'SGD' => 'SGD',
            'IDR' => 'IDR',
        ];

        $this->form([
            TextInput::make('count')
                ->label('Number of Gift Cards')
                ->numeric()
                ->minValue(1)
                ->maxValue(100)
                ->required()
                ->default(10),

            TextInput::make('amount')
                ->label('Amount per Card')
                ->numeric()
                ->minValue(0.01)
                ->required()
                ->suffix(fn (callable $get): string => $get('currency') ?? 'MYR'),

            Select::make('currency')
                ->label('Currency')
                ->options($currencyOptions)
                ->default('MYR')
                ->required(),

            Select::make('type')
                ->label('Type')
                ->options(static fn (): array => collect(GiftCardType::cases())
                    ->mapWithKeys(static fn (GiftCardType $type): array => [$type->value => $type->label()])
                    ->toArray())
                ->default(GiftCardType::Standard->value)
                ->required(),
        ]);

        $this->action(function (array $data): void {
            /** @var GiftCardService $service */
            $service = app(GiftCardService::class);

            $count = (int) $data['count'];
            $amountCents = (int) round((float) $data['amount'] * 100);

            $giftCards = $service->createBulk($count, $amountCents, [
                'type' => GiftCardType::from($data['type']),
                'currency' => $data['currency'],
            ]);

            Notification::make()
                ->title('Gift cards created')
                ->body("Successfully created {$giftCards->count()} gift cards.")
                ->success()
                ->send();
        });
    }
}

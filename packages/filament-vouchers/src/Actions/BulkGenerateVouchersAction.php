<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Actions;

use AIArmada\FilamentVouchers\Support\MoneyHelper;
use AIArmada\Vouchers\Runtime\VoucherBatchIssuer;
use AIArmada\Vouchers\Data\VoucherType;
use AIArmada\CommerceSupport\Support\FilamentPermission;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

final class BulkGenerateVouchersAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->authorize(static fn (): bool => FilamentPermission::hasAbility('voucher.create'));

        $this->label('Bulk Generate');
        $this->icon(Heroicon::OutlinedSquare2Stack);
        $this->color('primary');
        $this->modalHeading('Bulk Generate Vouchers');
        $this->modalDescription('Generate multiple vouchers at once with the same configuration.');

        $currencyOptions = [
            'MYR' => 'MYR',
            'USD' => 'USD',
            'SGD' => 'SGD',
            'IDR' => 'IDR',
        ];

        $this->form([
            TextInput::make('count')
                ->label('Number of Vouchers')
                ->numeric()
                ->minValue(1)
                ->maxValue(100)
                ->required()
                ->default(10),

            TextInput::make('prefix')
                ->label('Code Prefix')
                ->maxLength(10)
                ->alphaDash()
                ->default('BULK')
                ->helperText('Codes will be generated as PREFIX-XXXXXX'),

            TextInput::make('name')
                ->label('Voucher Name')
                ->required()
                ->maxLength(120),

            Select::make('type')
                ->label('Type')
                ->options(static fn (): array => collect(VoucherType::cases())
                    ->mapWithKeys(static fn (VoucherType $type): array => [$type->value => $type->label()])
                    ->toArray())
                ->default(VoucherType::Percentage->value)
                ->required(),

            TextInput::make('value')
                ->label('Value')
                ->numeric()
                ->required()
                ->helperText('Percentage (e.g., 10 for 10%) or fixed amount'),

            Select::make('currency')
                ->label('Currency')
                ->options($currencyOptions)
                ->default('MYR')
                ->required(),

            TextInput::make('usage_limit')
                ->label('Usage Limit per Voucher')
                ->numeric()
                ->minValue(1)
                ->default(1),
        ]);

        $this->action(function (array $data): void {
            $count = (int) $data['count'];
            $type = VoucherType::from($data['type']);

            $value = $type === VoucherType::Percentage
                ? MoneyHelper::displayToBasisPoints((string) $data['value'])
                : MoneyHelper::displayToCents((string) $data['value']);

            if ($value === null) {
                throw ValidationException::withMessages([
                    'value' => 'Enter a valid numeric value.',
                ]);
            }

            $created = app(VoucherBatchIssuer::class)->handle(
                count: $count,
                prefix: (string) ($data['prefix'] ?? ''),
                name: (string) $data['name'],
                type: $type,
                value: $value,
                currency: (string) $data['currency'],
                usageLimit: isset($data['usage_limit']) ? (int) $data['usage_limit'] : null,
            );

            Notification::make()
                ->title('Vouchers generated')
                ->body("Successfully created {$created} vouchers.")
                ->success()
                ->send();
        });
    }

    public static function getDefaultName(): ?string
    {
        return 'bulk_generate';
    }
}

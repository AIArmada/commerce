<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Services\VoucherService;
use AIArmada\Vouchers\States\Active;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class BulkGenerateVouchersAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

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
            /** @var VoucherService $service */
            $service = app(VoucherService::class);

            $count = (int) $data['count'];
            $created = 0;

            $ownerDefaults = $this->enforceOwnerOnCreate([]);

            for ($i = 0; $i < $count; $i++) {
                $code = mb_strtoupper($data['prefix']) . '-' . mb_strtoupper(Str::random(6));

                $service->create(array_merge($ownerDefaults, [
                    'code' => $code,
                    'name' => $data['name'] . ' #' . ($i + 1),
                    'type' => VoucherType::from($data['type']),
                    'value' => (int) round((float) $data['value'] * 100),
                    'currency' => $data['currency'],
                    'status' => Active::class,
                    'usage_limit' => $data['usage_limit'] ? (int) $data['usage_limit'] : null,
                ]));

                $created++;
            }

            Notification::make()
                ->title('Vouchers generated')
                ->body("Successfully created {$created} vouchers.")
                ->success()
                ->send();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function enforceOwnerOnCreate(array $data): array
    {
        if (! config('vouchers.owner.enabled', false)) {
            return $data;
        }

        $owner = OwnerContext::resolve();

        if (! $owner instanceof Model) {
            $data['owner_type'] = null;
            $data['owner_id'] = null;

            return $data;
        }

        $data['owner_type'] = $owner->getMorphClass();
        $data['owner_id'] = (string) $owner->getKey();

        return $data;
    }

    public static function getDefaultName(): ?string
    {
        return 'bulk_generate';
    }
}

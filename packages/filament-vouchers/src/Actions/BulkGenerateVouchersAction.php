<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Actions;

use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentVouchers\Support\MoneyHelper;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Services\VoucherService;
use AIArmada\Vouchers\States\Active;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

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
            /** @var VoucherService $service */
            $service = app(VoucherService::class);

            // Clamp server-side: the form maxValue is client-enforced only.
            $count = min(100, max(1, (int) $data['count']));
            $type = VoucherType::from($data['type']);

            $value = $type === VoucherType::Percentage
                ? MoneyHelper::displayToBasisPoints((string) $data['value'])
                : MoneyHelper::displayToCents((string) $data['value']);

            if ($value === null) {
                throw ValidationException::withMessages([
                    'value' => 'Enter a valid numeric value.',
                ]);
            }

            $ownerDefaults = $this->enforceOwnerOnCreate([]);
            $prefix = mb_strtoupper((string) ($data['prefix'] ?? ''));

            $created = DB::transaction(function () use ($service, $count, $type, $value, $data, $ownerDefaults, $prefix): int {
                $created = 0;

                for ($i = 0; $i < $count; $i++) {
                    $attempts = 0;

                    while (true) {
                        $attempts++;

                        try {
                            $service->create(array_merge($ownerDefaults, [
                                'code' => $this->generateUniqueCode($prefix),
                                'name' => $data['name'] . ' #' . ($i + 1),
                                'type' => $type,
                                'value' => $value,
                                'currency' => $data['currency'],
                                'status' => Active::class,
                                'usage_limit' => $data['usage_limit'] ? (int) $data['usage_limit'] : null,
                            ]));

                            break;
                        } catch (QueryException $exception) {
                            // Random suffix collision: regenerate instead of
                            // aborting the batch with partial rows.
                            if ($attempts >= 5 || $exception->getCode() !== '23000') {
                                throw $exception;
                            }
                        }
                    }

                    $created++;
                }

                return $created;
            });

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
            if (! OwnerContext::isExplicitGlobal()) {
                throw new NoCurrentOwnerException(
                    'Bulk voucher generation requires an owner context or explicit global context.'
                );
            }

            $data['owner_type'] = null;
            $data['owner_id'] = null;

            return $data;
        }

        $data['owner_type'] = $owner->getMorphClass();
        $data['owner_id'] = (string) $owner->getKey();

        return $data;
    }

    private function generateUniqueCode(string $prefix): string
    {
        $attempts = 0;

        do {
            $attempts++;
            $code = ($prefix !== '' ? $prefix . '-' : '') . mb_strtoupper(Str::random(6));

            try {
                $exists = DB::table((new Voucher)->getTable())
                    ->where('code', $code)
                    ->exists();
            } catch (QueryException) {
                $exists = false;
            }

            if (! $exists) {
                return $code;
            }
        } while ($attempts < 10);

        throw new RuntimeException('Could not generate a unique voucher code after 10 attempts.');
    }

    public static function getDefaultName(): ?string
    {
        return 'bulk_generate';
    }
}

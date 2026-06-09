<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\Promotions\Models\Promotion;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Schemas\VoucherInfolist;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Tables\VouchersTable;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\States\Active;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Livewire\Component as LivewireComponent;

uses(TestCase::class);

if (! function_exists('filamentVouchers_makeSchemaLivewire')) {
    function filamentVouchers_makeSchemaLivewire(): LivewireComponent & HasSchemas
    {
        return new class extends LivewireComponent implements HasSchemas
        {
            public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
            {
                return null;
            }

            public function getOldSchemaState(string $statePath): mixed
            {
                return null;
            }

            public function getSchemaComponent(string $key, bool $withHidden = false, array $skipComponentsChildContainersWhileSearching = []): Component | Action | ActionGroup | null
            {
                return null;
            }

            public function getSchema(string $name): ?Schema
            {
                return null;
            }

            public function currentlyValidatingSchema(?Schema $schema): void {}

            public function getDefaultTestingSchemaName(): ?string
            {
                return null;
            }
        };
    }
}

it('derives promotion source attributes from the related promotion when available', function (): void {
    $promotion = Promotion::factory()->create([
        'name' => 'Launch Campaign',
        'code' => 'LAUNCH',
    ]);

    $voucher = Voucher::query()->create([
        'code' => 'WELCOME-1',
        'name' => 'Welcome Voucher',
        'type' => VoucherType::Fixed,
        'value' => 1500,
        'currency' => 'MYR',
        'status' => Active::class,
        'promotion_id' => $promotion->id,
    ])->fresh();

    expect($voucher->promotion_source_id)->toBe((string) $promotion->id)
        ->and($voucher->promotion_source_name)->toBe('Launch Campaign')
        ->and($voucher->promotion_source_code)->toBe('LAUNCH')
        ->and($voucher->promotion_source_label)->toBe('Launch Campaign (LAUNCH)');
});

it('falls back to voucher metadata for promotion source attributes when relation data is unavailable', function (): void {
    $voucher = Voucher::query()->create([
        'code' => 'WELCOME-2',
        'name' => 'Fallback Voucher',
        'type' => VoucherType::Fixed,
        'value' => 1500,
        'currency' => 'MYR',
        'status' => Active::class,
        'promotion_id' => (string) Str::uuid(),
        'metadata' => [
            'source_promotion_id' => 'promo-fallback-id',
            'source_promotion_name' => 'Recovery Campaign',
            'source_promotion_code' => 'RECOVER',
        ],
    ])->fresh();

    expect($voucher->promotion_source_id)->toBe('promo-fallback-id')
        ->and($voucher->promotion_source_name)->toBe('Recovery Campaign')
        ->and($voucher->promotion_source_code)->toBe('RECOVER')
        ->and($voucher->promotion_source_label)->toBe('Recovery Campaign (RECOVER)');
});

it('exposes promotion source fields in voucher infolist and table definitions', function (): void {
    $schema = VoucherInfolist::configure(Schema::make(filamentVouchers_makeSchemaLivewire()));

    $flatten = function (array $components) use (&$flatten): array {
        $all = [];

        foreach ($components as $component) {
            if (! is_object($component)) {
                continue;
            }

            $all[] = $component;

            if (method_exists($component, 'getChildComponents')) {
                $all = [...$all, ...$flatten($component->getChildComponents())];
            }
        }

        return $all;
    };

    $entryNames = collect($flatten($schema->getComponents()))
        ->filter(fn (object $component): bool => $component instanceof TextEntry)
        ->map(fn (TextEntry $entry): string => $entry->getName())
        ->values()
        ->all();

    $table = VouchersTable::configure(Table::make(Mockery::mock(HasTable::class)));
    $columnNames = collect($table->getColumns())
        ->map(fn (object $column): string => $column->getName())
        ->values()
        ->all();

    expect($entryNames)
        ->toContain('promotion_source_name')
        ->toContain('promotion_source_code')
        ->toContain('promotion_source_id');

    expect($columnNames)->toContain('promotion_source_label');
});

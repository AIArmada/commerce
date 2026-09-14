<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentVouchers\Actions\BulkGenerateVouchersAction;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Schemas\VoucherForm;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Validator;
use Livewire\Component as LivewireComponent;

uses(TestCase::class);

class VoucherFormSchemaHost extends LivewireComponent implements HasSchemas
{
    use InteractsWithSchemas;

    public function render(): string
    {
        return '';
    }
}

function voucherCodeField(): TextInput
{
    $schema = VoucherForm::configure(Schema::make(new VoucherFormSchemaHost));

    $field = findVoucherFormField($schema->getComponents(), 'code');

    expect($field)->toBeInstanceOf(TextInput::class);

    return $field;
}

function bulkPrefixField(): TextInput
{
    $schema = BulkGenerateVouchersAction::make()->getSchema(Schema::make(new VoucherFormSchemaHost));

    expect($schema)->toBeInstanceOf(Schema::class);

    $field = findVoucherFormField($schema->getComponents(), 'prefix');

    expect($field)->toBeInstanceOf(TextInput::class);

    return $field;
}

/**
 * @param  array<int, Component>  $components
 */
function findVoucherFormField(array $components, string $name): ?Component
{
    foreach ($components as $component) {
        if ($component instanceof TextInput && $component->getName() === $name) {
            return $component;
        }

        $found = findVoucherFormField($component->getChildComponents(), $name);

        if ($found !== null) {
            return $found;
        }
    }

    return null;
}

it('rejects voucher codes outside the dash-separated alphabet', function (): void {
    $rules = voucherCodeField()->getValidationRules();

    expect(Validator::make(['code' => 'BULK-ABC123'], ['code' => $rules])->passes())->toBeTrue()
        ->and(Validator::make(['code' => "BULK'-ABC"], ['code' => $rules])->fails())->toBeTrue()
        ->and(Validator::make(['code' => 'BULK ABC'], ['code' => $rules])->fails())->toBeTrue();
});

it('rejects bulk prefixes outside the dash-separated alphabet', function (): void {
    $rules = bulkPrefixField()->getValidationRules();

    expect(Validator::make(['prefix' => 'BULK'], ['prefix' => $rules])->passes())->toBeTrue()
        ->and(Validator::make(['prefix' => "BULK'-X"], ['prefix' => $rules])->fails())->toBeTrue();
});

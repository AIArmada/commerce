<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\ReversedConversion;
use AIArmada\Authz\Models\Permission;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource\Tables\AffiliateConversionsTable;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Livewire\Component;

class ConversionAttributionSurfaceHostComponent extends Component implements HasSchemas, HasTable
{
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function render()
    {
        return view('livewire.placeholder');
    }
}

beforeEach(function (): void {
    AffiliateConversion::query()->delete();
    Affiliate::query()->delete();
    User::query()->delete();
    Permission::query()->delete();
});

function attributionSurfaceConversion(array $overrides = []): AffiliateConversion
{
    $affiliate = Affiliate::create([
        'code' => 'AFF-' . Str::uuid(),
        'name' => 'Attribution Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    return AffiliateConversion::create(array_merge([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'external_reference' => 'ORDER-' . Str::uuid(),
        'subject_key' => 'checkout:' . Str::uuid(),
        'status' => ApprovedConversion::class,
        'occurred_at' => now(),
        'subtotal_minor' => 10000,
        'value_minor' => 10000,
        'commission_minor' => 1000,
        'commission_currency' => 'USD',
        'origin' => 'network',
        'source_ref' => 'leg-001',
    ], $overrides));
}

it('exposes origin and source ref columns', function (): void {
    $table = AffiliateConversionsTable::configure(Table::make(new ConversionAttributionSurfaceHostComponent));

    expect($table->getColumn('origin'))->not->toBeNull()
        ->and($table->getColumn('source_ref'))->not->toBeNull()
        ->and($table->getAction('reverse'))->not->toBeNull();
});

it('reverses a conversion through the table reverse helper', function (): void {
    Permission::create(['name' => 'affiliate_conversion.update', 'guard_name' => 'web']);

    $user = User::create([
        'name' => 'Attribution Reverser',
        'email' => 'attribution-reverser@example.com',
        'password' => bcrypt('password'),
    ]);
    $user->givePermissionTo('affiliate_conversion.update');

    $this->actingAs($user);

    $conversion = attributionSurfaceConversion();

    AffiliateConversionsTable::reverse($conversion, 'chargeback');

    $reversal = AffiliateConversion::query()->where('conversion_type', 'reversal')->first();

    expect($conversion->fresh()->status->equals(ReversedConversion::class))->toBeTrue()
        ->and($reversal)->not->toBeNull()
        ->and((int) $reversal->commission_minor)->toBe(-1000)
        ->and($reversal->origin)->toBe('network');
});

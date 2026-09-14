<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentProducts\Fixtures\TestOwner;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentProducts\Resources\ProductResource\Tables\ProductsTable;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

uses(TestCase::class);

beforeEach(function (): void {
    Schema::dropIfExists('test_owners');

    Schema::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    config()->set('products.features.owner.enabled', true);
    config()->set('products.features.owner.include_global', false);
    config()->set('products.features.owner.auto_assign_on_create', true);

    Storage::fake('local');
});

function runProductImport(array $data): void
{
    $method = new ReflectionMethod(ProductsTable::class, 'importProducts');

    $method->invoke(null, $data);
}

function validateImportRow(array $record, bool $isUpdate): array
{
    $method = new ReflectionMethod(ProductsTable::class, 'validateImportRow');

    return $method->invoke(null, $record, $isUpdate);
}

function runProductExport(array $data): string
{
    $method = new ReflectionMethod(ProductsTable::class, 'exportProducts');

    /** @var StreamedResponse $response */
    $response = $method->invoke(null, $data);
    $callback = $response->getCallback();

    ob_start();

    try {
        $callback();
    } finally {
        $output = (string) ob_get_clean();
    }

    return $output;
}

it('rejects corrupt import rows instead of coercing defaults', function (): void {
    expect(fn () => validateImportRow(['name' => 'Bad Enum', 'price' => '9.99', 'status' => 'nonsense'], false))
        ->toThrow(Exception::class, 'status value is invalid')
        ->and(fn () => validateImportRow(['name' => 'Bad Type', 'price' => '9.99', 'type' => 'nonsense'], false))
        ->toThrow(Exception::class, 'type value is invalid')
        ->and(fn () => validateImportRow(['name' => 'Missing Price'], false))
        ->toThrow(Exception::class, 'price field is required')
        ->and(fn () => validateImportRow(['name' => 'Bad Price', 'price' => 'abc'], false))
        ->toThrow(Exception::class, 'price must be a positive number')
        ->and(fn () => validateImportRow(['name' => 'Bad Currency', 'price' => '9.99', 'currency' => 'XX'], false))
        ->toThrow(Exception::class, 'three-letter code')
        ->and(fn () => validateImportRow(['price' => '9.99'], false))
        ->toThrow(Exception::class, 'name field is required');
});

it('validates import rows and leaves updates untouched when cells are blank', function (): void {
    $created = validateImportRow(['name' => 'Widget', 'price' => '9.99'], false);

    expect($created['name'])->toBe('Widget')
        ->and($created['price'])->toBe(999)
        ->and($created['slug'])->toBe('widget')
        ->and($created['status'])->toBe(ProductStatus::Draft);

    $updated = validateImportRow(['sku' => 'KEEP-1', 'name' => 'Renamed'], true);

    expect($updated)->toBe(['name' => 'Renamed', 'sku' => 'KEEP-1']);
});

it('imports valid rows while skipping corrupt ones', function (): void {
    $owner = TestOwner::query()->create(['name' => 'Owner']);

    app()->instance(OwnerResolverInterface::class, new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    Storage::disk('local')->put('imports/mixed.csv', implode("\n", [
        'name,sku,price,status',
        'Good Widget,GOOD-1,9.99,draft',
        'Corrupt Widget,,not-a-price,bogus',
    ]));

    OwnerContext::withOwner($owner, static function (): void {
        runProductImport(['csv_file' => 'imports/mixed.csv', 'update_existing' => false, 'skip_errors' => true]);
    });

    expect(Product::query()->withoutGlobalScopes()->where('sku', 'GOOD-1')->exists())->toBeTrue()
        ->and(Product::query()->withoutGlobalScopes()->where('name', 'Corrupt Widget')->exists())->toBeFalse();
});

it('rolls back the whole import when errors are not skipped', function (): void {
    $owner = TestOwner::query()->create(['name' => 'Owner']);

    app()->instance(OwnerResolverInterface::class, new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    Storage::disk('local')->put('imports/atomic.csv', implode("\n", [
        'name,sku,price,status',
        'First Widget,FIRST-1,9.99,draft',
        'Corrupt Widget,,not-a-price,bogus',
    ]));

    OwnerContext::withOwner($owner, static function (): void {
        runProductImport(['csv_file' => 'imports/atomic.csv', 'update_existing' => false, 'skip_errors' => false]);
    });

    expect(Product::query()->withoutGlobalScopes()->where('sku', 'FIRST-1')->exists())->toBeFalse();
});

it('refuses imports that exceed the configured row limit', function (): void {
    config()->set('filament-products.import.max_rows', 1);

    Storage::disk('local')->put('imports/oversize.csv', implode("\n", [
        'name,sku,price,status',
        'One,ONE-1,1.00,draft',
        'Two,TWO-2,2.00,draft',
    ]));

    runProductImport(['csv_file' => 'imports/oversize.csv', 'update_existing' => false, 'skip_errors' => true]);

    expect(Product::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(Storage::disk('local')->exists('imports/oversize.csv'))->toBeTrue();
});

it('streams product exports row by row', function (): void {
    $owner = TestOwner::query()->create(['name' => 'Owner']);

    app()->instance(OwnerResolverInterface::class, new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    OwnerContext::withOwner($owner, static function (): void {
        Product::query()->create([
            'name' => 'Exported Widget',
            'slug' => 'exported-widget',
            'sku' => 'EXP-1',
            'price' => 1299,
            'status' => ProductStatus::Active,
        ]);
    });

    $csv = OwnerContext::withOwner($owner, static fn (): string => runProductExport([
        'fields' => ['name', 'sku', 'price', 'status'],
        'status_filter' => 'all',
    ]));

    expect($csv)->toContain('Exported Widget')
        ->and($csv)->toContain('EXP-1')
        ->and($csv)->toContain('12.99')
        ->and($csv)->toContain('active');
});

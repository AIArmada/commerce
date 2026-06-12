<?php

declare(strict_types=1);

use AIArmada\Addressing\Casts\AddressDataCast;
use AIArmada\Addressing\Data\AddressData;
use Illuminate\Database\Eloquent\Model;

beforeEach(function (): void {
    $this->cast = new AddressDataCast;

    $this->model = new class extends Model
    {
        protected $table = 'test_casts';

        protected function casts(): array
        {
            return [
                'address' => AddressDataCast::class,
            ];
        }
    };
});

it('casts JSON to AddressData', function (): void {
    $result = $this->cast->get(
        $this->model,
        'address',
        json_encode(['line1' => '123 Main St', 'city' => 'KL']),
        [],
    );

    expect($result)->toBeInstanceOf(AddressData::class);
    expect($result->line1)->toBe('123 Main St');
    expect($result->city)->toBe('KL');
});

it('serializes AddressData to JSON string', function (): void {
    $data = AddressData::from(['line1' => '123 Main St', 'city' => 'KL']);

    $result = $this->cast->set($this->model, 'address', $data, []);

    expect($result)->toBeJson();
    expect(json_decode($result, true))->toMatchArray([
        'line1' => '123 Main St',
        'city' => 'KL',
    ]);
});

it('returns null for null value', function (): void {
    $result = $this->cast->get($this->model, 'address', null, []);

    expect($result)->toBeNull();
});

it('serializes array to JSON', function (): void {
    $result = $this->cast->set(
        $this->model,
        'address',
        ['line1' => '123 Main St'],
        [],
    );

    expect($result)->toBeJson();
});

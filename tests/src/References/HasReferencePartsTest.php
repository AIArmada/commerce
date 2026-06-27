<?php

declare(strict_types=1);

use AIArmada\References\Enums\ReferencePartType;
use AIArmada\References\Traits\HasReferenceParts;
use Illuminate\Database\Eloquent\Model;

final class ReferencePartModel extends Model
{
    use HasReferenceParts;

    protected $fillable = ['reference_parts'];

    protected function casts(): array
    {
        return [
            'reference_parts' => 'array',
        ];
    }
}

beforeEach(function (): void {
    $this->model = new ReferencePartModel;
});

test('can set and get reference parts', function (): void {
    $this->model->setPart(ReferencePartType::Jilid, '1');

    $part = $this->model->getPart(ReferencePartType::Jilid);
    expect($part)->toBeArray();
    expect($part['type'])->toBe('jilid');
    expect($part['value'])->toBe('1');

    expect($this->model->getPart(ReferencePartType::Jilid->value))->toBe($part);
});

test('can remove reference parts', function (): void {
    $this->model->setPart(ReferencePartType::Jilid, '1');

    $this->model->removePart(ReferencePartType::Jilid);

    expect($this->model->reference_parts)->toBe([]);
});

test('can overwrite existing reference part', function (): void {
    $this->model->setPart(ReferencePartType::Jilid, '1');
    $this->model->setPart(ReferencePartType::Jilid, '2');

    $part = $this->model->getPart(ReferencePartType::Jilid->value);
    expect($part['value'])->toBe('2');
});

test('hasPart returns correct boolean', function (): void {
    expect($this->model->hasPart(ReferencePartType::Jilid))->toBeFalse();

    $this->model->setPart(ReferencePartType::Jilid, '1');

    expect($this->model->hasPart(ReferencePartType::Jilid))->toBeTrue();
});

test('can handle multiple part types', function (): void {
    $this->model->setPart(ReferencePartType::Jilid, '1');
    $this->model->setPart(ReferencePartType::Juz, '5');

    expect($this->model->hasPart(ReferencePartType::Jilid))->toBeTrue();
    expect($this->model->hasPart(ReferencePartType::Juz))->toBeTrue();
    expect($this->model->hasPart(ReferencePartType::Surah))->toBeFalse();

    $this->model->removePart(ReferencePartType::Jilid);

    expect($this->model->hasPart(ReferencePartType::Jilid))->toBeFalse();
    expect($this->model->hasPart(ReferencePartType::Juz))->toBeTrue();
});

test('getPartsGrouped returns parts with labels', function (): void {
    $this->model->setPart(ReferencePartType::Jilid, '1');
    $this->model->setPart(ReferencePartType::Chapter, '3');

    $grouped = $this->model->getPartsGrouped();

    expect($grouped)->toHaveCount(2);
    expect($grouped[0]['type'])->toBe('jilid');
    expect($grouped[0]['label'])->toBe('Jilid');
    expect($grouped[0]['value'])->toBe('1');
    expect($grouped[1]['type'])->toBe('chapter');
    expect($grouped[1]['label'])->toBe('Bab');
    expect($grouped[1]['value'])->toBe('3');
});

<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\PostalCode;
use AIArmada\Addressing\ReferenceSync\Canonical;
use AIArmada\Addressing\ReferenceSync\DiffEngine;
use AIArmada\Addressing\ReferenceSync\ReferenceReview;
use AIArmada\Addressing\ReferenceSync\ReferenceWriter;
use AIArmada\Addressing\ReferenceSync\SnapshotRepository;
use Illuminate\Support\Facades\DB;

function initializeReferenceFixture(): void
{
    $repo = new SnapshotRepository;
    $repo->captureLocal();
    $records = DB::table(SnapshotRepository::TABLE)->where('scope', 'local')->get()->map(
        static fn ($r): array => [
            'entity_type' => $r->entity_type,
            'natural_key' => Canonical::decode($r->natural_key),
            'payload' => Canonical::decode($r->payload),
        ],
    )->all();
    $repo->receiveRemote($records, 'package-v1');
    $repo->initializeBaseline('package-v1');
}

it('ignores timestamp touches but identifies local canonical field edits', function (): void {
    $country = $this->seedCountry('MY');
    initializeReferenceFixture();
    $country->touch();
    (new SnapshotRepository)->captureLocal();
    expect((new DiffEngine)->rebuild()['contribution'])->toBe(0);

    $country->name = 'Malaysia corrected';
    $country->saveOrFail();
    (new SnapshotRepository)->captureLocal();
    expect((new DiffEngine)->rebuild()['contribution'])->toBe(1)
        ->and(DB::table(DiffEngine::TABLE)->where('direction', 'contribution')->value('operation'))->toBe('update');
});

it('detects created and deleted postcodes even though deleted rows lack updated_at', function (): void {
    $this->seedCountry('MY');
    $postcode = PostalCode::query()->create(['country_code' => 'MY', 'code' => '12345']);
    initializeReferenceFixture();
    $postcode->delete();
    PostalCode::query()->create(['country_code' => 'MY', 'code' => '54321']);
    (new SnapshotRepository)->captureLocal();
    (new DiffEngine)->rebuild();
    $changes = DB::table(DiffEngine::TABLE)->where('direction', 'contribution')
        ->where('entity_type', 'postal_codes')->pluck('operation')->all();
    expect($changes)->toContain('delete')->toContain('create');
});

it('recognizes upstream acceptance without exporting the already merged correction again', function (): void {
    $country = $this->seedCountry('MY');
    initializeReferenceFixture();
    $country->name = 'Locally corrected';
    $country->saveOrFail();
    $repo = new SnapshotRepository;
    $repo->captureLocal();
    $records = DB::table(SnapshotRepository::TABLE)->where('scope', 'local')->get()->map(
        static fn ($r): array => [
            'entity_type' => $r->entity_type,
            'natural_key' => Canonical::decode($r->natural_key),
            'payload' => Canonical::decode($r->payload),
        ],
    )->all();
    $repo->receiveRemote($records, 'package-v2');
    expect((new DiffEngine)->rebuild()['contribution'])->toBe(0)
        ->and(DB::table(DiffEngine::TABLE)->where('direction', 'delivery')->value('operation'))->toBe('reconciled');
});

it('invalidates previous approvals if the underlying correction changes again', function (): void {
    $country = $this->seedCountry('MY');
    initializeReferenceFixture();
    $country->name = 'Correction one';
    $country->saveOrFail();
    (new SnapshotRepository)->captureLocal();
    (new DiffEngine)->rebuild();
    $id = (int) DB::table(DiffEngine::TABLE)->where('direction', 'contribution')->value('id');
    (new ReferenceReview)->decide($id, 'approved', 'tester');
    (new DiffEngine)->rebuild();
    expect(DB::table(DiffEngine::TABLE)->where('id', $id)->value('decision'))->toBe('approved');

    $country->name = 'Correction two';
    $country->saveOrFail();
    (new SnapshotRepository)->captureLocal();
    (new DiffEngine)->rebuild();
    expect(DB::table(DiffEngine::TABLE)->where('id', $id)->value('decision'))->toBe('pending');
});

it('refuses to bootstrap a baseline from an already-diverged application', function (): void {
    $country = $this->seedCountry('MY');
    $repo = new SnapshotRepository;
    $repo->captureLocal();
    $records = DB::table(SnapshotRepository::TABLE)->where('scope', 'local')->get()->map(
        static fn ($r): array => [
            'entity_type' => $r->entity_type,
            'natural_key' => Canonical::decode($r->natural_key),
            'payload' => Canonical::decode($r->payload),
        ],
    )->all();
    $repo->receiveRemote($records, 'v1');
    $country->name = 'Already changed before bootstrap';
    $country->saveOrFail();
    expect(fn () => $repo->initializeBaseline('v1'))->toThrow(LogicException::class);
});

it('flags incompatible simultaneous local and upstream corrections as conflicts', function (): void {
    $country = $this->seedCountry('MY');
    initializeReferenceFixture();
    $repo = new SnapshotRepository;
    $country->name = 'Local geographic spelling';
    $country->saveOrFail();
    $repo->captureLocal();

    $records = DB::table(SnapshotRepository::TABLE)->where('scope', 'remote')->get()->map(
        static function ($r): array {
            $payload = Canonical::decode($r->payload);
            if ($r->entity_type === 'countries') {
                $payload['name'] = 'Different upstream spelling';
            }

            return ['entity_type' => $r->entity_type,
                'natural_key' => Canonical::decode($r->natural_key), 'payload' => $payload];
        },
    )->all();
    $repo->receiveRemote($records, 'package-v2');
    expect((new DiffEngine)->rebuild()['conflict'])->toBe(1);
    $id = (int) DB::table(DiffEngine::TABLE)->where('direction', 'delivery')->value('id');
    expect(fn () => (new ReferenceReview)->decide($id, 'approved', 'tester'))->toThrow(RuntimeException::class);
});

it('applies a reviewed nonconflicting upstream scalar update and advances only its baseline', function (): void {
    $country = $this->seedCountry('MY');
    initializeReferenceFixture();
    $repo = new SnapshotRepository;
    $records = DB::table(SnapshotRepository::TABLE)->where('scope', 'remote')->get()->map(
        static function ($r): array {
            $payload = Canonical::decode($r->payload);
            if ($r->entity_type === 'countries') {
                $payload['name'] = 'Reviewed canonical name';
            }

            return ['entity_type' => $r->entity_type,
                'natural_key' => Canonical::decode($r->natural_key), 'payload' => $payload];
        },
    )->all();
    $repo->receiveRemote($records, 'package-v2');
    (new DiffEngine)->rebuild();
    $id = (int) DB::table(DiffEngine::TABLE)->where('direction', 'delivery')->value('id');
    (new ReferenceReview)->decide($id, 'approved', 'tester');
    $result = (new ReferenceWriter)->applyApproved(1);
    expect($result['applied'])->toBe(1)
        ->and($country->fresh()->name)->toBe('Reviewed canonical name');
});

<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Myanmar\MyanmarGeographyProvider;

it('ships 126 post-2022 districts and zones under regions with parent links', function (): void {
    $areas = app(MyanmarGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(126)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('parentSourceId', 'mm:state:shan'))->toHaveCount(25)
        ->and($l2->where('parentSourceId', 'mm:region:bago'))->toHaveCount(6)
        ->and($l2->where('parentSourceId', 'mm:region:sagaing'))->toHaveCount(14)
        ->and($byId->get('mm:district:kengtung')->code)->toBe('MMR016001')
        ->and($byId->get('mm:district:oke-ta-ra')->parentSourceId)->toBe('mm:union_territory:naypyidaw');
});

it('pins the post-2022 per-parent district counts', function (): void {
    $areas = app(MyanmarGeographyProvider::class)->addressAreaSource()->areas()->collect();

    $kids = [
        'mm:region:ayeyarwady' => 8,
        'mm:region:bago' => 6,
        'mm:state:chin' => 6,
        'mm:state:kachin' => 6,
        'mm:state:kayah' => 4,
        'mm:state:kayin' => 6,
        'mm:region:magway' => 7,
        'mm:region:mandalay' => 11,
        'mm:state:mon' => 4,
        'mm:union_territory:naypyidaw' => 4,
        'mm:state:rakhine' => 7,
        'mm:region:sagaing' => 14,
        'mm:state:shan' => 25,
        'mm:region:tanintharyi' => 4,
        'mm:region:yangon' => 14,
    ];

    foreach ($kids as $parent => $n) {
        expect($areas->where('parentSourceId', $parent))->toHaveCount($n, $parent);
    }
});

it('drops the 5 suppressed pre-2022 districts and retires their codes', function (): void {
    $areas = app(MyanmarGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    foreach (['mm:district:mandalay', 'mm:district:yangon-north', 'mm:district:yangon-east', 'mm:district:yangon-south', 'mm:district:yangon-west'] as $gone) {
        expect($byId->has($gone))->toBeFalse($gone);
    }

    $codes = $areas->where('level', 2)->pluck('code');
    foreach (['MMR010001', 'MMR013001', 'MMR013002', 'MMR013003', 'MMR013004'] as $retired) {
        expect($codes)->not->toContain($retired);
    }
});

it('renames the continuing Naypyidaw districts in place', function (): void {
    $areas = app(MyanmarGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($byId->get('mm:district:oke-ta-ra')->name)->toBe('Ottara')
        ->and($byId->get('mm:district:oke-ta-ra')->code)->toBe('MMR018001')
        ->and($byId->get('mm:district:det-khi-na')->name)->toBe('Dekkhina')
        ->and($byId->get('mm:district:det-khi-na')->code)->toBe('MMR018002');
});

it('pins the 2022 expansion districts by name, code and parent', function (): void {
    $areas = app(MyanmarGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    $adds = [
        ['chipwi', 'Chipwi', 'MMR001005', 'mm:state:kachin'],
        ['tanai', 'Tanai', 'MMR001006', 'mm:state:kachin'],
        ['demoso', 'Demoso', 'MMR002003', 'mm:state:kayah'],
        ['mese', 'Mese', 'MMR002004', 'mm:state:kayah'],
        ['kyain-seikgyi', 'Kyain Seikgyi', 'MMR003005', 'mm:state:kayin'],
        ['thandaunggyi', 'Thandaunggyi', 'MMR003006', 'mm:state:kayin'],
        ['paletwa', 'Paletwa', 'MMR004005', 'mm:state:chin'],
        ['tedim', 'Tedim', 'MMR004006', 'mm:state:chin'],
        ['homalin', 'Homalin', 'MMR005012', 'mm:region:sagaing'],
        ['ye-u', 'Ye-U', 'MMR005013', 'mm:region:sagaing'],
        ['bokpyin', 'Bokpyin', 'MMR006004', 'mm:region:tanintharyi'],
        ['nyaunglebin', 'Nyaunglebin', 'MMR007003', 'mm:region:bago'],
        ['nattalin', 'Nattalin', 'MMR008003', 'mm:region:bago'],
        ['aunglan', 'Aunglan', 'MMR009006', 'mm:region:magway'],
        ['chauk', 'Chauk', 'MMR009007', 'mm:region:magway'],
        ['amarapura', 'Amarapura', 'MMR010008', 'mm:region:mandalay'],
        ['aungmyethazan', 'Aungmyethazan', 'MMR010009', 'mm:region:mandalay'],
        ['maha-aungmye', 'Maha Aungmye', 'MMR010010', 'mm:region:mandalay'],
        ['tada-u', 'Tada-U', 'MMR010011', 'mm:region:mandalay'],
        ['thabeikkyin', 'Thabeikkyin', 'MMR010012', 'mm:region:mandalay'],
        ['kyaikto', 'Kyaikto', 'MMR011003', 'mm:state:mon'],
        ['ye', 'Ye', 'MMR011004', 'mm:state:mon'],
        ['ann', 'Ann', 'MMR012006', 'mm:state:rakhine'],
        ['taungup', 'Taungup', 'MMR012007', 'mm:state:rakhine'],
        ['taikkyi', 'Taikkyi', 'MMR013005', 'mm:region:yangon'],
        ['hlegu', 'Hlegu', 'MMR013006', 'mm:region:yangon'],
        ['hmawbi', 'Hmawbi', 'MMR013007', 'mm:region:yangon'],
        ['mingaladon', 'Mingaladon', 'MMR013008', 'mm:region:yangon'],
        ['insein', 'Insein', 'MMR013009', 'mm:region:yangon'],
        ['kyauktada', 'Kyauktada', 'MMR013010', 'mm:region:yangon'],
        ['ahlon', 'Ahlon', 'MMR013011', 'mm:region:yangon'],
        ['kamayut', 'Kamayut', 'MMR013012', 'mm:region:yangon'],
        ['mayangon', 'Mayangon', 'MMR013013', 'mm:region:yangon'],
        ['thingangyun', 'Thingangyun', 'MMR013014', 'mm:region:yangon'],
        ['botahtaung', 'Botahtaung', 'MMR013015', 'mm:region:yangon'],
        ['dagon-myothit', 'Dagon Myothit', 'MMR013016', 'mm:region:yangon'],
        ['twantay', 'Twantay', 'MMR013017', 'mm:region:yangon'],
        ['thanlyin', 'Thanlyin', 'MMR013018', 'mm:region:yangon'],
        ['kalaw', 'Kalaw', 'MMR014004', 'mm:state:shan'],
        ['kutkai', 'Kutkai', 'MMR015009', 'mm:state:shan'],
        ['monghsu', 'Monghsu', 'MMR014005', 'mm:state:shan'],
        ['mongla', 'Mongla', 'MMR016005', 'mm:state:shan'],
        ['mongton', 'Mongton', 'MMR016006', 'mm:state:shan'],
        ['mongyang', 'Mong Yang', 'MMR016007', 'mm:state:shan'],
        ['mongyawng', 'Mongyawng', 'MMR016008', 'mm:state:shan'],
        ['nansang', 'Nansang', 'MMR014006', 'mm:state:shan'],
        ['tangyan', 'Tangyan', 'MMR015010', 'mm:state:shan'],
        ['kyonpyaw', 'Kyonpyaw', 'MMR017007', 'mm:region:ayeyarwady'],
        ['myanaung', 'Myanaung', 'MMR017008', 'mm:region:ayeyarwady'],
        ['zeyathiri', 'Zeyathiri', 'MMR018003', 'mm:union_territory:naypyidaw'],
        ['pyinmana', 'Pyinmana', 'MMR018004', 'mm:union_territory:naypyidaw'],
    ];

    expect($adds)->toHaveCount(51);

    foreach ($adds as [$slug, $name, $code, $parent]) {
        $row = $byId->get("mm:district:{$slug}");
        expect($row)->not->toBeNull($slug);
        expect($row->name)->toBe($name, $slug)
            ->and($row->code)->toBe($code, $slug)
            ->and($row->parentSourceId)->toBe($parent, $slug);
    }
});

it('keeps self-administered zones as district-typed rows with stable parents', function (): void {
    $areas = app(MyanmarGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    $saz = [
        ['naga-self-administered-zone', 'Naga Self-Administered Zone', 'MMR005S001', 'mm:region:sagaing'],
        ['danu-self-administered-zone', 'Danu Self-Administered Zone', 'MMR014S001', 'mm:state:shan'],
        ['pa-o-self-administered-zone', 'Pa-O Self-Administered Zone', 'MMR014S002', 'mm:state:shan'],
        ['pa-laung-self-administered-zone', 'Pa Laung Self-Administered Zone', 'MMR015S001', 'mm:state:shan'],
        ['kokang-self-administered-zone', 'Kokang Self-Administered Zone', 'MMR015S002', 'mm:state:shan'],
    ];

    foreach ($saz as [$slug, $name, $code, $parent]) {
        $row = $byId->get("mm:district:{$slug}");
        expect($row->name)->toBe($name, $slug)
            ->and($row->code)->toBe($code, $slug)
            ->and($row->parentSourceId)->toBe($parent, $slug)
            ->and($row->type)->toBe('district', $slug);
    }

    expect($byId->get('mm:district:hopang')->parentSourceId)->toBe('mm:state:shan')
        ->and($byId->get('mm:district:matman')->parentSourceId)->toBe('mm:state:shan');
});

it('keeps bundled spellings where the wiki variants lack a second signal', function (): void {
    $areas = app(MyanmarGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    $stable = [
        'puta-o' => 'Puta-O',
        'bawlake' => 'Bawlake',
        'thayarwady' => 'Thayarwady',
        'maubin' => 'Maubin',
        'yinmarbin' => 'Yinmarbin',
        'langkho' => 'Langkho',
        'muse' => 'Muse',
        'monghsat' => 'Monghsat',
        'kawthoung' => 'Kawthoung',
        'pyinoolwin' => 'Pyinoolwin',
        'kyaukpyu' => 'Kyaukpyu',
    ];

    foreach ($stable as $slug => $name) {
        expect($byId->get("mm:district:{$slug}")->name)->toBe($name, $slug);
    }
});

<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Angola\AngolaGeographyProvider;

it('ships the 21 operational provinces with Cuando Cubango retired', function (): void {
    $areas = app(AngolaGeographyProvider::class)->addressAreaSource()->areas();

    expect($areas->where('level', 1))->toHaveCount(21);

    $byId = $areas->keyBy('sourceId');

    expect($byId->has('ao:province:cuando-cubango'))->toBeFalse()
        ->and($byId->get('ao:province:cuando')->code)->toBe('CUA')
        ->and($byId->get('ao:province:cubango')->code)->toBe('CUB')
        ->and($byId->get('ao:province:icolo-e-bengo')->name)->toBe('Icolo e Bengo')
        ->and($byId->get('ao:province:moxico-leste')->code)->toBe('MLE')
        ->and($byId->get('ao:province:cuanza-sul')->name)->toBe('Cuanza Sul')
        ->and($byId->has('ao:province:cuanza'))->toBeFalse();
});

it('ships 326 municipalities under provinces with parent links', function (): void {
    $areas = app(AngolaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(326)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('parentSourceId', 'ao:province:malanje'))->toHaveCount(27)
        ->and($l2->where('parentSourceId', 'ao:province:icolo-e-bengo'))->toHaveCount(7)
        ->and($byId->get('ao:municipality:viana')->parentSourceId)->toBe('ao:province:luanda')
        ->and($byId->get('ao:municipality:quelo')->parentSourceId)->toBe('ao:province:zaire');
});

it('spells Alto Chicapa per census oracles and keeps dual-name municipalities single', function (): void {
    $areas = app(AngolaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($byId->has('ao:municipality:alto-chipaca'))->toBeFalse()
        ->and($byId->get('ao:municipality:alto-chicapa')->name)->toBe('Alto Chicapa')
        ->and($byId->get('ao:municipality:alto-chicapa')->parentSourceId)->toBe('ao:province:lunda-sul')
        ->and($byId->get('ao:municipality:tombwa')->name)->toBe('Tômbwa')
        ->and($byId->get('ao:municipality:waku-kungo')->parentSourceId)->toBe('ao:province:cuanza-sul')
        ->and($byId->get('ao:municipality:cangola')->parentSourceId)->toBe('ao:province:uige');
});

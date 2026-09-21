<?php

declare(strict_types=1);

use AIArmada\FilamentDocs\Resources\DocTemplateResource;

test('doc template resource has correct pages', function (): void {
    $pages = DocTemplateResource::getPages();

    expect($pages)->toHaveKey('index');
    expect($pages)->toHaveKey('create');
    expect($pages)->toHaveKey('view');
    expect($pages)->toHaveKey('edit');
});

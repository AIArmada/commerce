<?php

use AIArmada\Docs\Facades\Doc;
use AIArmada\Docs\Services\DocService;

test('doc facade resolves to doc service binding', function () {
    expect(Doc::getFacadeRoot())->toBeInstanceOf(DocService::class);
});

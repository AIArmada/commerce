<?php

declare(strict_types=1);

use AIArmada\Docs\Enums\ShareLinkAction;
use AIArmada\FilamentDocs\Resources\DocResource\Pages\ViewDoc;

it('prefers the PDF share route when online viewing is not allowed', function (): void {
    $method = new ReflectionMethod(ViewDoc::class, 'shareLinkRouteName');

    expect($method->invoke(null, [ShareLinkAction::Pdf->value]))
        ->toBe('docs.share.pdf');
});

it('prefers the online share route when view access is allowed', function (): void {
    $method = new ReflectionMethod(ViewDoc::class, 'shareLinkRouteName');

    expect($method->invoke(null, [ShareLinkAction::Pdf->value, ShareLinkAction::View->value]))
        ->toBe('docs.share.show');
});

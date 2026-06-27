<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\JsonDisplay;

it('formats arrays as pretty json html', function (): void {
    expect(JsonDisplay::format(['name' => 'Alice']))
        ->toBe("<pre>{\n    &quot;name&quot;: &quot;Alice&quot;\n}</pre>");
});

it('decodes json strings before formatting', function (): void {
    expect(JsonDisplay::format('{"name":"Alice"}'))
        ->toBe("<pre>{\n    &quot;name&quot;: &quot;Alice&quot;\n}</pre>");
});

it('escapes html in the rendered json output', function (): void {
    expect(JsonDisplay::format(['html' => '<b>safe</b>']))
        ->toBe("<pre>{\n    &quot;html&quot;: &quot;&lt;b&gt;safe&lt;/b&gt;&quot;\n}</pre>");
});

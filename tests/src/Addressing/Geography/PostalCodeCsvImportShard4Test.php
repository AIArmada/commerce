<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Addressing\Geography\Postal\PostalCsvSamples;

it('ships structurally valid postcode datasets (shard 4 of 4)', function (string $countryCode, string $codesPath, string $linksPath): void {
    PostalCsvSamples::assertStructurallyValid($countryCode, $codesPath, $linksPath);
})->with(PostalCsvSamples::datasetsForShard(3));

it('imports sampled postcodes without failures (shard 4 of 4)', function (string $countryCode, string $codesPath, string $linksPath): void {
    $country = $this->seedCountry($countryCode);

    PostalCsvSamples::importSample($country, $countryCode, $codesPath, $linksPath);
})->with(PostalCsvSamples::datasetsForShard(3));

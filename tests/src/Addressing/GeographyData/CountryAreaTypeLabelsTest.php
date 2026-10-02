<?php

declare(strict_types=1);

use AIArmada\Addressing\Contracts\CountryAreaTypeLabelProvider;
use AIArmada\Addressing\Contracts\CountryGeographyProvider;
use AIArmada\Addressing\Geography\Afghanistan\AfghanistanGeographyProvider;
use AIArmada\Addressing\Geography\Aland\AlandGeographyProvider;
use AIArmada\Addressing\Geography\Albania\AlbaniaGeographyProvider;
use AIArmada\Addressing\Geography\Algeria\AlgeriaGeographyProvider;
use AIArmada\Addressing\Geography\AmericanSamoa\AmericanSamoaGeographyProvider;
use AIArmada\Addressing\Geography\Andorra\AndorraGeographyProvider;
use AIArmada\Addressing\Geography\Angola\AngolaGeographyProvider;
use AIArmada\Addressing\Geography\Anguilla\AnguillaGeographyProvider;
use AIArmada\Addressing\Geography\AntiguaAndBarbuda\AntiguaAndBarbudaGeographyProvider;
use AIArmada\Addressing\Geography\Argentina\ArgentinaGeographyProvider;
use AIArmada\Addressing\Geography\Armenia\ArmeniaGeographyProvider;
use AIArmada\Addressing\Geography\Aruba\ArubaGeographyProvider;
use AIArmada\Addressing\Geography\Australia\AustraliaGeographyProvider;
use AIArmada\Addressing\Geography\Austria\AustriaGeographyProvider;
use AIArmada\Addressing\Geography\Azerbaijan\AzerbaijanGeographyProvider;
use AIArmada\Addressing\Geography\Bahamas\BahamasGeographyProvider;
use AIArmada\Addressing\Geography\Bahrain\BahrainGeographyProvider;
use AIArmada\Addressing\Geography\Bangladesh\BangladeshGeographyProvider;
use AIArmada\Addressing\Geography\Barbados\BarbadosGeographyProvider;
use AIArmada\Addressing\Geography\Belarus\BelarusGeographyProvider;
use AIArmada\Addressing\Geography\Belgium\BelgiumGeographyProvider;
use AIArmada\Addressing\Geography\Belize\BelizeGeographyProvider;
use AIArmada\Addressing\Geography\Benin\BeninGeographyProvider;
use AIArmada\Addressing\Geography\Bermuda\BermudaGeographyProvider;
use AIArmada\Addressing\Geography\Bhutan\BhutanGeographyProvider;
use AIArmada\Addressing\Geography\Bolivia\BoliviaGeographyProvider;
use AIArmada\Addressing\Geography\BosniaAndHerzegovina\BosniaAndHerzegovinaGeographyProvider;
use AIArmada\Addressing\Geography\Botswana\BotswanaGeographyProvider;
use AIArmada\Addressing\Geography\Brazil\BrazilGeographyProvider;
use AIArmada\Addressing\Geography\Brunei\BruneiGeographyProvider;
use AIArmada\Addressing\Geography\Bulgaria\BulgariaGeographyProvider;
use AIArmada\Addressing\Geography\BurkinaFaso\BurkinaFasoGeographyProvider;
use AIArmada\Addressing\Geography\Burundi\BurundiGeographyProvider;
use AIArmada\Addressing\Geography\Cambodia\CambodiaGeographyProvider;
use AIArmada\Addressing\Geography\Cameroon\CameroonGeographyProvider;
use AIArmada\Addressing\Geography\Canada\CanadaGeographyProvider;
use AIArmada\Addressing\Geography\CapeVerde\CapeVerdeGeographyProvider;
use AIArmada\Addressing\Geography\CaribbeanNetherlands\CaribbeanNetherlandsGeographyProvider;
use AIArmada\Addressing\Geography\CaymanIslands\CaymanIslandsGeographyProvider;
use AIArmada\Addressing\Geography\CentralAfricanRepublic\CentralAfricanRepublicGeographyProvider;
use AIArmada\Addressing\Geography\Chad\ChadGeographyProvider;
use AIArmada\Addressing\Geography\Chile\ChileGeographyProvider;
use AIArmada\Addressing\Geography\China\ChinaGeographyProvider;
use AIArmada\Addressing\Geography\Colombia\ColombiaGeographyProvider;
use AIArmada\Addressing\Geography\Comoros\ComorosGeographyProvider;
use AIArmada\Addressing\Geography\Congo\CongoGeographyProvider;
use AIArmada\Addressing\Geography\CostaRica\CostaRicaGeographyProvider;
use AIArmada\Addressing\Geography\Croatia\CroatiaGeographyProvider;
use AIArmada\Addressing\Geography\Cuba\CubaGeographyProvider;
use AIArmada\Addressing\Geography\Cyprus\CyprusGeographyProvider;
use AIArmada\Addressing\Geography\CzechRepublic\CzechRepublicGeographyProvider;
use AIArmada\Addressing\Geography\DemocraticRepublicOfCongo\DemocraticRepublicOfCongoGeographyProvider;
use AIArmada\Addressing\Geography\Denmark\DenmarkGeographyProvider;
use AIArmada\Addressing\Geography\Djibouti\DjiboutiGeographyProvider;
use AIArmada\Addressing\Geography\Dominica\DominicaGeographyProvider;
use AIArmada\Addressing\Geography\DominicanRepublic\DominicanRepublicGeographyProvider;
use AIArmada\Addressing\Geography\Ecuador\EcuadorGeographyProvider;
use AIArmada\Addressing\Geography\Egypt\EgyptGeographyProvider;
use AIArmada\Addressing\Geography\ElSalvador\ElSalvadorGeographyProvider;
use AIArmada\Addressing\Geography\EquatorialGuinea\EquatorialGuineaGeographyProvider;
use AIArmada\Addressing\Geography\Eritrea\EritreaGeographyProvider;
use AIArmada\Addressing\Geography\Estonia\EstoniaGeographyProvider;
use AIArmada\Addressing\Geography\Eswatini\EswatiniGeographyProvider;
use AIArmada\Addressing\Geography\Ethiopia\EthiopiaGeographyProvider;
use AIArmada\Addressing\Geography\FaroeIslands\FaroeIslandsGeographyProvider;
use AIArmada\Addressing\Geography\Fiji\FijiGeographyProvider;
use AIArmada\Addressing\Geography\Finland\FinlandGeographyProvider;
use AIArmada\Addressing\Geography\France\FranceGeographyProvider;
use AIArmada\Addressing\Geography\FrenchGuiana\FrenchGuianaGeographyProvider;
use AIArmada\Addressing\Geography\FrenchPolynesia\FrenchPolynesiaGeographyProvider;
use AIArmada\Addressing\Geography\FrenchSouthernTerritories\FrenchSouthernTerritoriesGeographyProvider;
use AIArmada\Addressing\Geography\Gabon\GabonGeographyProvider;
use AIArmada\Addressing\Geography\Gambia\GambiaGeographyProvider;
use AIArmada\Addressing\Geography\Georgia\GeorgiaGeographyProvider;
use AIArmada\Addressing\Geography\Germany\GermanyGeographyProvider;
use AIArmada\Addressing\Geography\Ghana\GhanaGeographyProvider;
use AIArmada\Addressing\Geography\Greece\GreeceGeographyProvider;
use AIArmada\Addressing\Geography\Greenland\GreenlandGeographyProvider;
use AIArmada\Addressing\Geography\Grenada\GrenadaGeographyProvider;
use AIArmada\Addressing\Geography\Guadeloupe\GuadeloupeGeographyProvider;
use AIArmada\Addressing\Geography\Guam\GuamGeographyProvider;
use AIArmada\Addressing\Geography\Guatemala\GuatemalaGeographyProvider;
use AIArmada\Addressing\Geography\Guernsey\GuernseyGeographyProvider;
use AIArmada\Addressing\Geography\Guinea\GuineaGeographyProvider;
use AIArmada\Addressing\Geography\GuineaBissau\GuineaBissauGeographyProvider;
use AIArmada\Addressing\Geography\Guyana\GuyanaGeographyProvider;
use AIArmada\Addressing\Geography\Haiti\HaitiGeographyProvider;
use AIArmada\Addressing\Geography\Honduras\HondurasGeographyProvider;
use AIArmada\Addressing\Geography\HongKong\HongKongGeographyProvider;
use AIArmada\Addressing\Geography\Hungary\HungaryGeographyProvider;
use AIArmada\Addressing\Geography\Iceland\IcelandGeographyProvider;
use AIArmada\Addressing\Geography\India\IndiaGeographyProvider;
use AIArmada\Addressing\Geography\Indonesia\IndonesiaGeographyProvider;
use AIArmada\Addressing\Geography\Iran\IranGeographyProvider;
use AIArmada\Addressing\Geography\Iraq\IraqGeographyProvider;
use AIArmada\Addressing\Geography\Ireland\IrelandGeographyProvider;
use AIArmada\Addressing\Geography\IsleOfMan\IsleOfManGeographyProvider;
use AIArmada\Addressing\Geography\Italy\ItalyGeographyProvider;
use AIArmada\Addressing\Geography\IvoryCoast\IvoryCoastGeographyProvider;
use AIArmada\Addressing\Geography\Jamaica\JamaicaGeographyProvider;
use AIArmada\Addressing\Geography\Japan\JapanGeographyProvider;
use AIArmada\Addressing\Geography\Jersey\JerseyGeographyProvider;
use AIArmada\Addressing\Geography\Jordan\JordanGeographyProvider;
use AIArmada\Addressing\Geography\Kazakhstan\KazakhstanGeographyProvider;
use AIArmada\Addressing\Geography\Kenya\KenyaGeographyProvider;
use AIArmada\Addressing\Geography\Kiribati\KiribatiGeographyProvider;
use AIArmada\Addressing\Geography\Kosovo\KosovoGeographyProvider;
use AIArmada\Addressing\Geography\Kuwait\KuwaitGeographyProvider;
use AIArmada\Addressing\Geography\Kyrgyzstan\KyrgyzstanGeographyProvider;
use AIArmada\Addressing\Geography\Laos\LaosGeographyProvider;
use AIArmada\Addressing\Geography\Latvia\LatviaGeographyProvider;
use AIArmada\Addressing\Geography\Lebanon\LebanonGeographyProvider;
use AIArmada\Addressing\Geography\Lesotho\LesothoGeographyProvider;
use AIArmada\Addressing\Geography\Liberia\LiberiaGeographyProvider;
use AIArmada\Addressing\Geography\Libya\LibyaGeographyProvider;
use AIArmada\Addressing\Geography\Liechtenstein\LiechtensteinGeographyProvider;
use AIArmada\Addressing\Geography\Lithuania\LithuaniaGeographyProvider;
use AIArmada\Addressing\Geography\Luxembourg\LuxembourgGeographyProvider;
use AIArmada\Addressing\Geography\Madagascar\MadagascarGeographyProvider;
use AIArmada\Addressing\Geography\Malawi\MalawiGeographyProvider;
use AIArmada\Addressing\Geography\Malaysia\MalaysiaGeographyProvider;
use AIArmada\Addressing\Geography\Maldives\MaldivesGeographyProvider;
use AIArmada\Addressing\Geography\Mali\MaliGeographyProvider;
use AIArmada\Addressing\Geography\Malta\MaltaGeographyProvider;
use AIArmada\Addressing\Geography\MarshallIslands\MarshallIslandsGeographyProvider;
use AIArmada\Addressing\Geography\Martinique\MartiniqueGeographyProvider;
use AIArmada\Addressing\Geography\Mauritania\MauritaniaGeographyProvider;
use AIArmada\Addressing\Geography\Mauritius\MauritiusGeographyProvider;
use AIArmada\Addressing\Geography\Mayotte\MayotteGeographyProvider;
use AIArmada\Addressing\Geography\Mexico\MexicoGeographyProvider;
use AIArmada\Addressing\Geography\Micronesia\MicronesiaGeographyProvider;
use AIArmada\Addressing\Geography\Moldova\MoldovaGeographyProvider;
use AIArmada\Addressing\Geography\Monaco\MonacoGeographyProvider;
use AIArmada\Addressing\Geography\Mongolia\MongoliaGeographyProvider;
use AIArmada\Addressing\Geography\Montenegro\MontenegroGeographyProvider;
use AIArmada\Addressing\Geography\Montserrat\MontserratGeographyProvider;
use AIArmada\Addressing\Geography\Morocco\MoroccoGeographyProvider;
use AIArmada\Addressing\Geography\Mozambique\MozambiqueGeographyProvider;
use AIArmada\Addressing\Geography\Myanmar\MyanmarGeographyProvider;
use AIArmada\Addressing\Geography\Namibia\NamibiaGeographyProvider;
use AIArmada\Addressing\Geography\Nauru\NauruGeographyProvider;
use AIArmada\Addressing\Geography\Nepal\NepalGeographyProvider;
use AIArmada\Addressing\Geography\Netherlands\NetherlandsGeographyProvider;
use AIArmada\Addressing\Geography\NewCaledonia\NewCaledoniaGeographyProvider;
use AIArmada\Addressing\Geography\NewZealand\NewZealandGeographyProvider;
use AIArmada\Addressing\Geography\Nicaragua\NicaraguaGeographyProvider;
use AIArmada\Addressing\Geography\Niger\NigerGeographyProvider;
use AIArmada\Addressing\Geography\Nigeria\NigeriaGeographyProvider;
use AIArmada\Addressing\Geography\Niue\NiueGeographyProvider;
use AIArmada\Addressing\Geography\NorthKorea\NorthKoreaGeographyProvider;
use AIArmada\Addressing\Geography\NorthMacedonia\NorthMacedoniaGeographyProvider;
use AIArmada\Addressing\Geography\Norway\NorwayGeographyProvider;
use AIArmada\Addressing\Geography\Oman\OmanGeographyProvider;
use AIArmada\Addressing\Geography\Pakistan\PakistanGeographyProvider;
use AIArmada\Addressing\Geography\Palau\PalauGeographyProvider;
use AIArmada\Addressing\Geography\Palestine\PalestineGeographyProvider;
use AIArmada\Addressing\Geography\Panama\PanamaGeographyProvider;
use AIArmada\Addressing\Geography\PapuaNewGuinea\PapuaNewGuineaGeographyProvider;
use AIArmada\Addressing\Geography\Paraguay\ParaguayGeographyProvider;
use AIArmada\Addressing\Geography\Peru\PeruGeographyProvider;
use AIArmada\Addressing\Geography\Philippines\PhilippinesGeographyProvider;
use AIArmada\Addressing\Geography\Poland\PolandGeographyProvider;
use AIArmada\Addressing\Geography\Portugal\PortugalGeographyProvider;
use AIArmada\Addressing\Geography\PuertoRico\PuertoRicoGeographyProvider;
use AIArmada\Addressing\Geography\Qatar\QatarGeographyProvider;
use AIArmada\Addressing\Geography\Reunion\ReunionGeographyProvider;
use AIArmada\Addressing\Geography\Romania\RomaniaGeographyProvider;
use AIArmada\Addressing\Geography\Russia\RussiaGeographyProvider;
use AIArmada\Addressing\Geography\Rwanda\RwandaGeographyProvider;
use AIArmada\Addressing\Geography\SaintBarthelemy\SaintBarthelemyGeographyProvider;
use AIArmada\Addressing\Geography\SaintHelena\SaintHelenaGeographyProvider;
use AIArmada\Addressing\Geography\SaintKittsAndNevis\SaintKittsAndNevisGeographyProvider;
use AIArmada\Addressing\Geography\SaintLucia\SaintLuciaGeographyProvider;
use AIArmada\Addressing\Geography\SaintMartin\SaintMartinGeographyProvider;
use AIArmada\Addressing\Geography\SaintPierreAndMiquelon\SaintPierreAndMiquelonGeographyProvider;
use AIArmada\Addressing\Geography\SaintVincentAndTheGrenadines\SaintVincentAndTheGrenadinesGeographyProvider;
use AIArmada\Addressing\Geography\Samoa\SamoaGeographyProvider;
use AIArmada\Addressing\Geography\SanMarino\SanMarinoGeographyProvider;
use AIArmada\Addressing\Geography\SaoTomeAndPrincipe\SaoTomeAndPrincipeGeographyProvider;
use AIArmada\Addressing\Geography\SaudiArabia\SaudiArabiaGeographyProvider;
use AIArmada\Addressing\Geography\Senegal\SenegalGeographyProvider;
use AIArmada\Addressing\Geography\Serbia\SerbiaGeographyProvider;
use AIArmada\Addressing\Geography\Seychelles\SeychellesGeographyProvider;
use AIArmada\Addressing\Geography\SierraLeone\SierraLeoneGeographyProvider;
use AIArmada\Addressing\Geography\Singapore\SingaporeGeographyProvider;
use AIArmada\Addressing\Geography\Slovakia\SlovakiaGeographyProvider;
use AIArmada\Addressing\Geography\Slovenia\SloveniaGeographyProvider;
use AIArmada\Addressing\Geography\SolomonIslands\SolomonIslandsGeographyProvider;
use AIArmada\Addressing\Geography\Somalia\SomaliaGeographyProvider;
use AIArmada\Addressing\Geography\SouthAfrica\SouthAfricaGeographyProvider;
use AIArmada\Addressing\Geography\SouthKorea\SouthKoreaGeographyProvider;
use AIArmada\Addressing\Geography\SouthSudan\SouthSudanGeographyProvider;
use AIArmada\Addressing\Geography\Spain\SpainGeographyProvider;
use AIArmada\Addressing\Geography\SriLanka\SriLankaGeographyProvider;
use AIArmada\Addressing\Geography\Sudan\SudanGeographyProvider;
use AIArmada\Addressing\Geography\Suriname\SurinameGeographyProvider;
use AIArmada\Addressing\Geography\Sweden\SwedenGeographyProvider;
use AIArmada\Addressing\Geography\Switzerland\SwitzerlandGeographyProvider;
use AIArmada\Addressing\Geography\Syria\SyriaGeographyProvider;
use AIArmada\Addressing\Geography\Taiwan\TaiwanGeographyProvider;
use AIArmada\Addressing\Geography\Tajikistan\TajikistanGeographyProvider;
use AIArmada\Addressing\Geography\Tanzania\TanzaniaGeographyProvider;
use AIArmada\Addressing\Geography\Thailand\ThailandGeographyProvider;
use AIArmada\Addressing\Geography\TimorLeste\TimorLesteGeographyProvider;
use AIArmada\Addressing\Geography\Togo\TogoGeographyProvider;
use AIArmada\Addressing\Geography\Tonga\TongaGeographyProvider;
use AIArmada\Addressing\Geography\TrinidadAndTobago\TrinidadAndTobagoGeographyProvider;
use AIArmada\Addressing\Geography\Tunisia\TunisiaGeographyProvider;
use AIArmada\Addressing\Geography\Turkiye\TurkiyeGeographyProvider;
use AIArmada\Addressing\Geography\Turkmenistan\TurkmenistanGeographyProvider;
use AIArmada\Addressing\Geography\TurksAndCaicos\TurksAndCaicosGeographyProvider;
use AIArmada\Addressing\Geography\Tuvalu\TuvaluGeographyProvider;
use AIArmada\Addressing\Geography\Uganda\UgandaGeographyProvider;
use AIArmada\Addressing\Geography\Ukraine\UkraineGeographyProvider;
use AIArmada\Addressing\Geography\UnitedArabEmirates\UnitedArabEmiratesGeographyProvider;
use AIArmada\Addressing\Geography\UnitedKingdom\UnitedKingdomGeographyProvider;
use AIArmada\Addressing\Geography\UnitedStates\UnitedStatesGeographyProvider;
use AIArmada\Addressing\Geography\Uruguay\UruguayGeographyProvider;
use AIArmada\Addressing\Geography\USMinorOutlyingIslands\USMinorOutlyingIslandsGeographyProvider;
use AIArmada\Addressing\Geography\USVirginIslands\USVirginIslandsGeographyProvider;
use AIArmada\Addressing\Geography\Uzbekistan\UzbekistanGeographyProvider;
use AIArmada\Addressing\Geography\Vanuatu\VanuatuGeographyProvider;
use AIArmada\Addressing\Geography\Venezuela\VenezuelaGeographyProvider;
use AIArmada\Addressing\Geography\Vietnam\VietnamGeographyProvider;
use AIArmada\Addressing\Geography\WallisAndFutuna\WallisAndFutunaGeographyProvider;
use AIArmada\Addressing\Geography\Yemen\YemenGeographyProvider;
use AIArmada\Addressing\Geography\Zambia\ZambiaGeographyProvider;
use AIArmada\Addressing\Geography\Zimbabwe\ZimbabweGeographyProvider;

/**
 * Every country's administrative type labels, one dataset row each.
 *
 * The labels are hardcoded maps inside the providers, so the rows restate
 * them as data rather than one test per country. A failing row names the
 * country that needs updating.
 */
it('labels area types for every country', function (string $provider, array $area, array $state): void {
    $provider = app($provider);

    expect($provider)->toBeInstanceOf(CountryGeographyProvider::class)
        ->and($provider->areaTypeLabels())->toBe($area)
        ->and($provider->stateAreaTypeLabels())->toBe($state);
})->with([
    // Afghanistan
    [AfghanistanGeographyProvider::class, ['province' => 'Province', 'district' => 'District'], []],
    // Aland
    [AlandGeographyProvider::class, ['municipality' => 'Kommun'], []],
    // Albania
    [AlbaniaGeographyProvider::class, ['county' => 'Qark', 'municipality' => 'Bashki'], []],
    // Algeria
    [AlgeriaGeographyProvider::class, ['wilaya' => 'Wilaya', 'daira' => 'Daira'], []],
    // AmericanSamoa
    [AmericanSamoaGeographyProvider::class, ['district' => 'District', 'atoll' => 'Atoll', 'county' => 'County'], []],
    // Andorra
    [AndorraGeographyProvider::class, ['parish' => 'Parròquia'], []],
    // Angola
    [AngolaGeographyProvider::class, ['province' => 'Província', 'municipality' => 'Município'], []],
    // Anguilla
    [AnguillaGeographyProvider::class, ['district' => 'District'], []],
    // AntiguaAndBarbuda
    [AntiguaAndBarbudaGeographyProvider::class, ['parish' => 'Parish', 'dependency' => 'Dependency'], []],
    // Argentina
    [ArgentinaGeographyProvider::class, ['province' => 'Provincia', 'city' => 'Ciudad', 'commune' => 'Comuna', 'department' => 'Departamento', 'partido' => 'Partido'], []],
    // Armenia
    [ArmeniaGeographyProvider::class, ['region' => 'Marz', 'municipality' => 'Hamaynk', 'city' => 'City', 'district' => 'District'], []],
    // Aruba
    [ArubaGeographyProvider::class, ['region' => 'Region', 'capital_city' => 'Capital City'], []],
    // Australia
    [AustraliaGeographyProvider::class, ['state' => 'State', 'territory' => 'Territory', 'city' => 'City', 'shire' => 'Shire', 'town' => 'Town', 'region' => 'Region', 'borough' => 'Borough', 'municipality' => 'Municipality', 'rural_city' => 'Rural City', 'council' => 'Council'], []],
    // Austria
    [AustriaGeographyProvider::class, ['state' => 'Bundesland', 'district' => 'Bezirk', 'statutory_city' => 'Statutarstadt'], []],
    // Azerbaijan
    [AzerbaijanGeographyProvider::class, ['district' => 'Rayon', 'municipality' => 'Şəhər', 'autonomous_republic' => 'Muxtar Respublika', 'local_municipality' => 'Bələdiyyə'], []],
    // Bahamas
    [BahamasGeographyProvider::class, ['district' => 'District', 'island' => 'Island'], []],
    // Bahrain
    [BahrainGeographyProvider::class, ['governorate' => 'Governorate'], []],
    // Bangladesh
    [BangladeshGeographyProvider::class, ['division' => 'Division', 'district' => 'District'], []],
    // Barbados
    [BarbadosGeographyProvider::class, ['parish' => 'Parish'], []],
    // Belarus
    [BelarusGeographyProvider::class, ['oblast' => 'Oblast', 'city' => 'City', 'district' => 'District'], []],
    // Belgium
    [BelgiumGeographyProvider::class, ['region' => 'Region', 'province' => 'Province'], [
        ['state_code' => 'VLG', 'type_labels' => ['region' => 'Gewest', 'province' => 'Provincie']],
        ['state_code' => 'WAL', 'type_labels' => ['region' => 'Région', 'province' => 'Province']],
    ]],
    // Belize
    [BelizeGeographyProvider::class, ['district' => 'District'], []],
    // Benin
    [BeninGeographyProvider::class, ['department' => 'Département', 'commune' => 'Commune'], []],
    // Bermuda
    [BermudaGeographyProvider::class, ['parish' => 'Parish', 'municipality' => 'Municipality'], []],
    // Bhutan
    [BhutanGeographyProvider::class, ['district' => 'Dzongkhag', 'gewog' => 'Gewog'], []],
    // Bolivia
    [BoliviaGeographyProvider::class, ['department' => 'Departamento', 'province' => 'Provincia'], []],
    // BosniaAndHerzegovina
    [BosniaAndHerzegovinaGeographyProvider::class, ['entity' => 'Entitet', 'district' => 'Distrikt', 'municipality' => 'Općina'], [
        ['state_code' => 'SRP', 'type_labels' => ['municipality' => 'Opština']],
    ]],
    // Botswana
    [BotswanaGeographyProvider::class, ['district' => 'District', 'city' => 'City', 'town' => 'Town', 'subdistrict' => 'Subdistrict'], []],
    // Brazil
    [BrazilGeographyProvider::class, ['state' => 'Estado', 'federal_district' => 'Distrito Federal', 'municipality' => 'Município', 'district' => 'Distrito'], []],
    // Brunei
    [BruneiGeographyProvider::class, ['district' => 'Daerah', 'mukim' => 'Mukim'], []],
    // Bulgaria
    [BulgariaGeographyProvider::class, ['district' => 'Oblast', 'municipality' => 'Municipality'], []],
    // BurkinaFaso
    [BurkinaFasoGeographyProvider::class, ['region' => 'Région', 'province' => 'Province'], []],
    // Burundi
    [BurundiGeographyProvider::class, ['province' => 'Province', 'commune' => 'Commune'], []],
    // Cambodia
    [CambodiaGeographyProvider::class, ['province' => 'Khet', 'municipality' => 'Krong', 'district' => 'Srok', 'section' => 'Khan'], []],
    // Cameroon
    [CameroonGeographyProvider::class, ['region' => 'Région', 'department' => 'Département'], []],
    // Canada
    [CanadaGeographyProvider::class, ['province' => 'Province', 'territory' => 'Territory', 'municipality' => 'Municipality', 'indigenous_reserve' => 'Indigenous Reserve', 'unorganized' => 'Unorganized'], []],
    // CapeVerde
    [CapeVerdeGeographyProvider::class, ['municipality' => 'Concelho', 'geographical_region' => 'Região Geográfica', 'parish' => 'Freguesia'], []],
    // CaribbeanNetherlands
    [CaribbeanNetherlandsGeographyProvider::class, ['special_municipality' => 'Bijzondere Gemeente'], []],
    // CaymanIslands
    [CaymanIslandsGeographyProvider::class, ['island' => 'Island', 'district' => 'District'], []],
    // CentralAfricanRepublic
    [CentralAfricanRepublicGeographyProvider::class, ['prefecture' => 'Préfecture', 'economic_prefecture' => 'Préfecture Économique', 'subprefecture' => 'Sous-préfecture'], []],
    // Chad
    [ChadGeographyProvider::class, ['province' => 'Province', 'department' => 'Département'], []],
    // Chile
    [ChileGeographyProvider::class, ['region' => 'Región', 'province' => 'Provincia'], []],
    // China
    [ChinaGeographyProvider::class, ['province' => 'Province', 'autonomous_region' => 'Autonomous Region', 'municipality' => 'Municipality', 'special_administrative_region' => 'Special Administrative Region', 'prefecture_city' => 'Prefecture City', 'prefecture' => 'Prefecture', 'autonomous_prefecture' => 'Autonomous Prefecture', 'league' => 'League'], []],
    // Colombia
    [ColombiaGeographyProvider::class, ['department' => 'Departamento', 'capital_district' => 'Distrito Capital', 'municipality' => 'Municipio', 'locality' => 'Localidad', 'non_municipalized_area' => 'Área No Municipalizada'], []],
    // Comoros
    [ComorosGeographyProvider::class, ['island' => 'Île', 'prefecture' => 'Préfecture'], []],
    // Congo
    [CongoGeographyProvider::class, ['department' => 'Département', 'district' => 'District'], []],
    // CostaRica
    [CostaRicaGeographyProvider::class, ['province' => 'Provincia', 'canton' => 'Cantón'], []],
    // Croatia
    [CroatiaGeographyProvider::class, ['county' => 'Županija', 'municipality' => 'Općina', 'town' => 'Grad'], []],
    // Cuba
    [CubaGeographyProvider::class, ['province' => 'Provincia', 'special_municipality' => 'Municipio Especial', 'municipality' => 'Municipio'], []],
    // Cyprus
    [CyprusGeographyProvider::class, ['district' => 'District', 'locality' => 'Locality'], []],
    // CzechRepublic
    [CzechRepublicGeographyProvider::class, ['region' => 'Kraj', 'capital_city' => 'Hlavní Město', 'district' => 'Okres'], []],
    // DemocraticRepublicOfCongo
    [DemocraticRepublicOfCongoGeographyProvider::class, ['province' => 'Province', 'territory' => 'Territoire'], []],
    // Denmark
    [DenmarkGeographyProvider::class, ['region' => 'Region', 'municipality' => 'Kommune'], []],
    // Djibouti
    [DjiboutiGeographyProvider::class, ['region' => 'Région', 'city' => 'Ville', 'subprefecture' => 'Sous-préfecture'], []],
    // Dominica
    [DominicaGeographyProvider::class, ['parish' => 'Parish'], []],
    // DominicanRepublic
    [DominicanRepublicGeographyProvider::class, ['region' => 'Región', 'province' => 'Provincia', 'district' => 'Distrito', 'municipality' => 'Municipio'], []],
    // Ecuador
    [EcuadorGeographyProvider::class, ['province' => 'Provincia', 'canton' => 'Cantón'], []],
    // Egypt
    [EgyptGeographyProvider::class, ['governorate' => 'Governorate', 'district' => 'District'], []],
    // ElSalvador
    [ElSalvadorGeographyProvider::class, ['department' => 'Departamento', 'municipality' => 'Municipio'], []],
    // EquatorialGuinea
    [EquatorialGuineaGeographyProvider::class, ['region' => 'Región', 'province' => 'Provincia'], []],
    // Eritrea
    [EritreaGeographyProvider::class, ['region' => 'Zoba', 'subregion' => 'Subregion'], []],
    // Estonia
    [EstoniaGeographyProvider::class, ['county' => 'Maakond', 'rural_municipality' => 'Vald', 'urban_municipality' => 'Linn'], []],
    // Eswatini
    [EswatiniGeographyProvider::class, ['region' => 'Region', 'inkhundla' => 'Inkhundla'], []],
    // Ethiopia
    [EthiopiaGeographyProvider::class, ['region' => 'Kilil', 'city' => 'City', 'zone' => 'Zone', 'woreda' => 'Woreda'], []],
    // FaroeIslands
    [FaroeIslandsGeographyProvider::class, ['municipality' => 'Kommuna', 'region' => 'Region'], []],
    // Fiji
    [FijiGeographyProvider::class, ['division' => 'Division', 'dependency' => 'Dependency', 'province' => 'Province'], []],
    // Finland
    [FinlandGeographyProvider::class, ['region' => 'Maakunta', 'city' => 'Kaupunki', 'municipality' => 'Kunta'], []],
    // France
    [FranceGeographyProvider::class, ['region' => 'Région', 'department' => 'Département'], []],
    // FrenchGuiana
    [FrenchGuianaGeographyProvider::class, ['overseas_region' => 'Région', 'commune' => 'Commune'], []],
    // FrenchPolynesia
    [FrenchPolynesiaGeographyProvider::class, ['division' => 'Subdivision', 'commune' => 'Commune'], []],
    // FrenchSouthernTerritories
    [FrenchSouthernTerritoriesGeographyProvider::class, ['district' => 'District'], []],
    // Gabon
    [GabonGeographyProvider::class, ['province' => 'Province', 'department' => 'Département'], []],
    // Gambia
    [GambiaGeographyProvider::class, ['region' => 'Region', 'city' => 'City', 'district' => 'District'], []],
    // Georgia
    [GeorgiaGeographyProvider::class, ['region' => 'Mkhare', 'autonomous_republic' => 'Autonomous Republic', 'city' => 'City', 'municipality' => 'Municipality', 'district' => 'District'], []],
    // Germany
    [GermanyGeographyProvider::class, ['state' => 'Land', 'rural_district' => 'Landkreis', 'urban_district' => 'Kreisfreie Stadt'], []],
    // Ghana
    [GhanaGeographyProvider::class, ['region' => 'Region', 'metropolitan_city' => 'Metropolitan City', 'municipality' => 'Municipality', 'district' => 'District'], []],
    // Greece
    [GreeceGeographyProvider::class, ['administrative_region' => 'Periféreia', 'municipality' => 'Dímos'], []],
    // Greenland
    [GreenlandGeographyProvider::class, ['municipality' => 'Kommune'], []],
    // Grenada
    [GrenadaGeographyProvider::class, ['parish' => 'Parish', 'dependency' => 'Dependency'], []],
    // Guadeloupe
    [GuadeloupeGeographyProvider::class, ['district' => 'Arrondissement', 'commune' => 'Commune'], []],
    // Guam
    [GuamGeographyProvider::class, ['village' => 'Village'], []],
    // Guatemala
    [GuatemalaGeographyProvider::class, ['department' => 'Departamento', 'municipality' => 'Municipio'], []],
    // Guernsey
    [GuernseyGeographyProvider::class, ['parish' => 'Parish', 'dependency' => 'Dependency'], []],
    // Guinea
    [GuineaGeographyProvider::class, ['administrative_region' => 'Région', 'governorate' => 'Gouvernorat', 'prefecture' => 'Préfecture'], []],
    // GuineaBissau
    [GuineaBissauGeographyProvider::class, ['region' => 'Região', 'autonomous_sector' => 'Sector Autónomo', 'sector' => 'Sector'], []],
    // Guyana
    [GuyanaGeographyProvider::class, ['region' => 'Region', 'town' => 'Town', 'neighbourhood_democratic_council' => 'Neighbourhood Democratic Council'], []],
    // Haiti
    [HaitiGeographyProvider::class, ['department' => 'Département', 'arrondissement' => 'Arrondissement'], []],
    // Honduras
    [HondurasGeographyProvider::class, ['department' => 'Departamento', 'municipality' => 'Municipio'], []],
    // HongKong
    [HongKongGeographyProvider::class, ['district' => 'District'], []],
    // Hungary
    [HungaryGeographyProvider::class, ['county' => 'Vármegye', 'city_with_county_rights' => 'Megyei Jogú Város', 'capital_city' => 'Főváros', 'district' => 'Járás'], []],
    // Iceland
    [IcelandGeographyProvider::class, ['region' => 'Landsvæði', 'municipality' => 'Sveitarfélag'], []],
    // India
    [IndiaGeographyProvider::class, ['state' => 'State', 'union_territory' => 'Union Territory', 'district' => 'District'], []],
    // Indonesia
    [IndonesiaGeographyProvider::class, ['city' => 'Kota', 'district' => 'Kecamatan', 'village' => 'Desa', 'urban_village' => 'Kelurahan', 'province' => 'Province', 'regency' => 'Regency'], [
        ['state_code' => 'AC', 'type_labels' => ['village' => 'Gampong', 'urban_village' => 'Gampong']],
        ['state_code' => 'SB', 'type_labels' => ['village' => 'Nagari']],
        ['state_code' => 'YO', 'type_labels' => ['district' => 'Kapanewon / Kemantren', 'village' => 'Kalurahan']],
        ['state_code' => 'PA', 'type_labels' => ['district' => 'Distrik', 'village' => 'Kampung']],
        ['state_code' => 'PB', 'type_labels' => ['district' => 'Distrik', 'village' => 'Kampung']],
        ['state_code' => 'PS', 'type_labels' => ['district' => 'Distrik', 'village' => 'Kampung']],
        ['state_code' => 'PT', 'type_labels' => ['district' => 'Distrik', 'village' => 'Kampung']],
        ['state_code' => 'PE', 'type_labels' => ['district' => 'Distrik', 'village' => 'Kampung']],
        ['state_code' => 'PD', 'type_labels' => ['district' => 'Distrik', 'village' => 'Kampung']],
    ]],
    // Iran
    [IranGeographyProvider::class, ['province' => 'Ostan', 'county' => 'Shahrestan'], []],
    // Iraq
    [IraqGeographyProvider::class, ['governorate' => 'Muhafaza', 'district' => 'Qadaa'], []],
    // Ireland
    [IrelandGeographyProvider::class, ['province' => 'Province', 'county' => 'County'], []],
    // IsleOfMan
    [IsleOfManGeographyProvider::class, ['sheading' => 'Sheading', 'parish' => 'Parish', 'town' => 'Town', 'district' => 'District', 'village' => 'Village'], []],
    // Italy
    [ItalyGeographyProvider::class, ['region' => 'Regione', 'province' => 'Province', 'metropolitan_city' => 'Metropolitan City', 'free_municipal_consortium' => 'Free Municipal Consortium', 'decentralization_entity' => 'Decentralization Entity', 'autonomous_province' => 'Autonomous Province'], []],
    // IvoryCoast
    [IvoryCoastGeographyProvider::class, ['autonomous_district' => 'District Autonome', 'district' => 'District', 'region' => 'Région'], []],
    // Jamaica
    [JamaicaGeographyProvider::class, ['parish' => 'Parish'], []],
    // Japan
    [JapanGeographyProvider::class, ['prefecture' => 'Prefecture', 'city' => 'City', 'town' => 'Town', 'village' => 'Village', 'ward' => 'Ward'], []],
    // Jersey
    [JerseyGeographyProvider::class, ['parish' => 'Parish', 'vingtaine' => 'Vingtaine', 'canton' => 'Canton', 'cueillette' => 'Cueillette'], []],
    // Jordan
    [JordanGeographyProvider::class, ['governorate' => 'Muhafaza', 'liwa' => 'Liwa'], []],
    // Kazakhstan
    [KazakhstanGeographyProvider::class, ['region' => 'Oblys', 'city' => 'Qala', 'district' => 'Audan'], []],
    // Kenya
    [KenyaGeographyProvider::class, ['county' => 'County', 'constituency' => 'Constituency'], []],
    // Kiribati
    [KiribatiGeographyProvider::class, ['island' => 'Island', 'council' => 'Council'], []],
    // Kosovo
    [KosovoGeographyProvider::class, ['district' => 'Rajoni', 'municipality' => 'Komuna'], []],
    // Kuwait
    [KuwaitGeographyProvider::class, ['governorate' => 'Muhafaza', 'area' => 'Area'], []],
    // Kyrgyzstan
    [KyrgyzstanGeographyProvider::class, ['region' => 'Oblus', 'city' => 'Shaar', 'district' => 'Raion'], []],
    // Laos
    [LaosGeographyProvider::class, ['province' => 'Khoueng', 'district' => 'Muang', 'prefecture' => 'Prefecture'], []],
    // Latvia
    [LatviaGeographyProvider::class, ['municipality' => 'Novads', 'state_city' => 'Valstspilsēta', 'parish' => 'Pagasts', 'town' => 'Pilsēta', 'city' => 'Pilsēta'], []],
    // Lebanon
    [LebanonGeographyProvider::class, ['governorate' => 'Muhafaza', 'caza' => 'Caza'], []],
    // Lesotho
    [LesothoGeographyProvider::class, ['district' => 'District', 'constituency' => 'Constituency'], []],
    // Liberia
    [LiberiaGeographyProvider::class, ['county' => 'County', 'district' => 'District'], []],
    // Libya
    [LibyaGeographyProvider::class, ['popularate' => 'Popularate', 'baladiya' => 'Baladiya'], []],
    // Liechtenstein
    [LiechtensteinGeographyProvider::class, ['commune' => 'Gemeinde'], []],
    // Lithuania
    [LithuaniaGeographyProvider::class, ['county' => 'Apskritis', 'district_municipality' => 'Rajono Savivaldybė', 'city_municipality' => 'Miesto Savivaldybė', 'municipality' => 'Savivaldybė'], []],
    // Luxembourg
    [LuxembourgGeographyProvider::class, ['canton' => 'Canton', 'commune' => 'Commune'], []],
    // Madagascar
    [MadagascarGeographyProvider::class, ['province' => 'Faritany', 'region' => 'Faritra', 'district' => 'Distrika'], []],
    // Malawi
    [MalawiGeographyProvider::class, ['region' => 'Region', 'district' => 'District'], []],
    // Malaysia
    [MalaysiaGeographyProvider::class, [
        'state' => 'State',
        'wilayah_persekutuan' => 'Wilayah Persekutuan',
        'division' => 'Division',
        'district' => 'District',
        'minor_district' => 'Minor District',
        'city' => 'City',
        'municipality' => 'Municipality',
        'mukim' => 'Mukim',
        'subdistrict' => 'Subdistrict',
        'bandar' => 'Bandar',
        'pekan' => 'Pekan',
        'daerah_kecil' => 'Daerah Kecil',
        'locality' => 'Locality',
        'precinct' => 'Precinct',
    ], [
        ['state_code' => '03', 'type_labels' => ['district' => 'Jajahan', 'minor_district' => 'Jajahan Kecil']],
        ['state_code' => '06', 'type_labels' => ['minor_district' => 'Daerah Kecil']],
    ]],
    // Maldives
    [MaldivesGeographyProvider::class, ['atoll' => 'Atoll', 'city' => 'City', 'island' => 'Island'], []],
    // Mali
    [MaliGeographyProvider::class, ['district' => 'District', 'region' => 'Région', 'cercle' => 'Cercle'], []],
    // Malta
    [MaltaGeographyProvider::class, ['local_council' => 'Local Council'], []],
    // MarshallIslands
    [MarshallIslandsGeographyProvider::class, ['chain' => 'Chain', 'municipality' => 'Municipality'], []],
    // Martinique
    [MartiniqueGeographyProvider::class, ['district' => 'Arrondissement', 'commune' => 'Commune'], []],
    // Mauritania
    [MauritaniaGeographyProvider::class, ['region' => 'Wilaya', 'department' => 'Moughataa'], []],
    // Mauritius
    [MauritiusGeographyProvider::class, ['district' => 'District', 'dependency' => 'Dependency', 'city' => 'City', 'town' => 'Town', 'village' => 'Village'], []],
    // Mayotte
    [MayotteGeographyProvider::class, ['commune' => 'Commune'], []],
    // Mexico
    [MexicoGeographyProvider::class, ['state' => 'Estado', 'municipality' => 'Municipio', 'borough' => 'Alcaldía'], []],
    // Micronesia
    [MicronesiaGeographyProvider::class, ['state' => 'State', 'municipality' => 'Municipality', 'city' => 'City'], []],
    // Moldova
    [MoldovaGeographyProvider::class, ['district' => 'Raion', 'commune' => 'Comună', 'city' => 'Oraș', 'autonomous_territorial_unit' => 'Autonomous Territorial Unit', 'territorial_unit' => 'Territorial Unit'], []],
    // Monaco
    [MonacoGeographyProvider::class, ['quarter' => 'Quartier'], []],
    // Mongolia
    [MongoliaGeographyProvider::class, ['province' => 'Aimag', 'sum' => 'Sum', 'duureg' => 'Düüreg', 'capital_city' => 'Capital City'], []],
    // Montenegro
    [MontenegroGeographyProvider::class, ['municipality' => 'Opština'], []],
    // Montserrat
    [MontserratGeographyProvider::class, ['parish' => 'Parish'], []],
    // Morocco
    [MoroccoGeographyProvider::class, ['region' => 'Région', 'prefecture' => 'Préfecture', 'province' => 'Province'], []],
    // Mozambique
    [MozambiqueGeographyProvider::class, ['province' => 'Província', 'city' => 'Cidade', 'district' => 'Distrito'], []],
    // Myanmar
    [MyanmarGeographyProvider::class, ['region' => 'Region', 'state' => 'State', 'union_territory' => 'Union Territory', 'district' => 'District'], []],
    // Namibia
    [NamibiaGeographyProvider::class, ['region' => 'Region', 'constituency' => 'Constituency'], []],
    // Nauru
    [NauruGeographyProvider::class, ['district' => 'District'], []],
    // Nepal
    [NepalGeographyProvider::class, ['province' => 'Pradesh', 'district' => 'Jilla'], []],
    // Netherlands
    [NetherlandsGeographyProvider::class, ['province' => 'Provincie', 'municipality' => 'Gemeente'], []],
    // NewCaledonia
    [NewCaledoniaGeographyProvider::class, ['province' => 'Province', 'commune' => 'Commune'], []],
    // NewZealand
    [NewZealandGeographyProvider::class, ['region' => 'Region', 'special_island_authority' => 'Special Island Authority', 'district' => 'District', 'city' => 'City', 'council' => 'Council', 'locality' => 'Locality'], []],
    // Nicaragua
    [NicaraguaGeographyProvider::class, ['department' => 'Departamento', 'autonomous_region' => 'Región Autónoma', 'municipality' => 'Municipio'], []],
    // Niger
    [NigerGeographyProvider::class, ['region' => 'Région', 'urban_community' => 'Communauté Urbaine', 'department' => 'Département', 'commune' => 'Commune'], []],
    // Nigeria
    [NigeriaGeographyProvider::class, ['lga' => 'LGA', 'state' => 'State', 'area_council' => 'Area Council'], []],
    // Niue
    [NiueGeographyProvider::class, ['village' => 'Village'], []],
    // NorthKorea
    [NorthKoreaGeographyProvider::class, ['province' => 'Province', 'capital_city' => 'Capital City', 'special_city' => 'Special City', 'district' => 'District'], []],
    // NorthMacedonia
    [NorthMacedoniaGeographyProvider::class, ['municipality' => 'Opština'], []],
    // Norway
    [NorwayGeographyProvider::class, ['county' => 'Fylke', 'municipality' => 'Kommune', 'arctic_region' => 'Arctic Region'], []],
    // Oman
    [OmanGeographyProvider::class, ['governorate' => 'Muhafaza', 'wilayat' => 'Wilayat'], []],
    // Pakistan
    [PakistanGeographyProvider::class, ['province' => 'Province', 'territory' => 'Territory', 'district' => 'District'], []],
    // Palau
    [PalauGeographyProvider::class, ['state' => 'State'], []],
    // Palestine
    [PalestineGeographyProvider::class, ['governorate' => 'Muhafaza'], []],
    // Panama
    [PanamaGeographyProvider::class, ['province' => 'Provincia', 'indigenous_region' => 'Comarca Indígena', 'district' => 'Distrito'], []],
    // PapuaNewGuinea
    [PapuaNewGuineaGeographyProvider::class, ['province' => 'Province', 'autonomous_region' => 'Autonomous Region', 'district' => 'District'], []],
    // Paraguay
    [ParaguayGeographyProvider::class, ['department' => 'Departamento', 'capital_district' => 'Distrito Capital', 'district' => 'Distrito'], []],
    // Peru
    [PeruGeographyProvider::class, ['region' => 'Región', 'municipality' => 'Municipalidad', 'province' => 'Provincia'], []],
    // Philippines
    [PhilippinesGeographyProvider::class, ['province' => 'Province', 'region' => 'Region', 'city' => 'City', 'municipality' => 'Municipality', 'sub_municipality' => 'Sub Municipality', 'barangay' => 'Barangay'], []],
    // Poland
    [PolandGeographyProvider::class, ['voivodeship' => 'Województwo', 'land_county' => 'Powiat Ziemski', 'city_county' => 'Powiat Grodzki'], []],
    // Portugal
    [PortugalGeographyProvider::class, ['autonomous_region' => 'Região Autónoma', 'district' => 'Distrito', 'municipality' => 'Município'], []],
    // PuertoRico
    [PuertoRicoGeographyProvider::class, ['municipality' => 'Municipio', 'barrio_pueblo' => 'Barrio-Pueblo', 'barrio' => 'Barrio'], []],
    // Qatar
    [QatarGeographyProvider::class, ['municipality' => 'Municipality', 'zone' => 'Zone'], []],
    // Reunion
    [ReunionGeographyProvider::class, ['district' => 'Arrondissement', 'commune' => 'Commune'], []],
    // Romania
    [RomaniaGeographyProvider::class, ['department' => 'Județ', 'municipality' => 'Municipiu', 'commune' => 'Comună', 'town' => 'Oraș', 'sector' => 'Sector'], []],
    // Russia
    [RussiaGeographyProvider::class, ['oblast' => 'Oblast', 'republic' => 'Republic', 'krai' => 'Krai', 'okrug' => 'Okrug', 'federal_city' => 'Federal City', 'autonomous_oblast' => 'Autonomous Oblast'], []],
    // Rwanda
    [RwandaGeographyProvider::class, ['province' => 'Province', 'city' => 'City', 'district' => 'District'], []],
    // SaintBarthelemy
    [SaintBarthelemyGeographyProvider::class, ['overseas_collectivity' => 'Overseas Collectivity'], []],
    // SaintHelena
    [SaintHelenaGeographyProvider::class, ['district' => 'District', 'island' => 'Island'], []],
    // SaintKittsAndNevis
    [SaintKittsAndNevisGeographyProvider::class, ['state' => 'State', 'parish' => 'Parish', 'village' => 'Village'], []],
    // SaintLucia
    [SaintLuciaGeographyProvider::class, ['district' => 'District'], []],
    // SaintMartin
    [SaintMartinGeographyProvider::class, ['overseas_collectivity' => 'Overseas Collectivity'], []],
    // SaintPierreAndMiquelon
    [SaintPierreAndMiquelonGeographyProvider::class, ['overseas_collectivity' => 'Overseas Collectivity'], []],
    // SaintVincentAndTheGrenadines
    [SaintVincentAndTheGrenadinesGeographyProvider::class, ['parish' => 'Parish'], []],
    // Samoa
    [SamoaGeographyProvider::class, ['district' => 'District', 'village' => 'Village'], []],
    // SanMarino
    [SanMarinoGeographyProvider::class, ['municipality' => 'Castello'], []],
    // SaoTomeAndPrincipe
    [SaoTomeAndPrincipeGeographyProvider::class, ['district' => 'Distrito', 'autonomous_region' => 'Região Autónoma'], []],
    // SaudiArabia
    [SaudiArabiaGeographyProvider::class, ['region' => 'Region', 'governorate' => 'Muhafaza'], []],
    // Senegal
    [SenegalGeographyProvider::class, ['region' => 'Région', 'department' => 'Département'], []],
    // Serbia
    [SerbiaGeographyProvider::class, ['district' => 'Okrug', 'province' => 'Pokrajina', 'city' => 'Grad', 'municipality' => 'Opština', 'city_municipality' => 'Gradska opština'], []],
    // Seychelles
    [SeychellesGeographyProvider::class, ['district' => 'District'], []],
    // SierraLeone
    [SierraLeoneGeographyProvider::class, ['province' => 'Province', 'area' => 'Area', 'district' => 'District'], []],
    // Singapore
    [SingaporeGeographyProvider::class, ['postal_district' => 'Postal District', 'postal_sector' => 'Postal Sector', 'region' => 'Region', 'planning_area' => 'Planning Area'], []],
    // Slovakia
    [SlovakiaGeographyProvider::class, ['region' => 'Kraj', 'district' => 'Okres'], []],
    // Slovenia
    [SloveniaGeographyProvider::class, ['municipality' => 'Občina', 'urban_municipality' => 'Mestna občina'], []],
    // SolomonIslands
    [SolomonIslandsGeographyProvider::class, ['province' => 'Province', 'capital_territory' => 'Capital Territory', 'ward' => 'Ward'], []],
    // Somalia
    [SomaliaGeographyProvider::class, ['region' => 'Region', 'district' => 'District'], []],
    // SouthAfrica
    [SouthAfricaGeographyProvider::class, ['province' => 'Province', 'district_municipality' => 'District Municipality', 'city_municipality' => 'City Municipality'], []],
    // SouthKorea
    [SouthKoreaGeographyProvider::class, ['province' => 'Province', 'metropolitan_city' => 'Metropolitan City', 'special_city' => 'Special City', 'special_self_governing_province' => 'Special Self Governing Province', 'special_self_governing_city' => 'Special Self Governing City', 'city' => 'City', 'county' => 'County', 'district' => 'District'], []],
    // SouthSudan
    [SouthSudanGeographyProvider::class, ['state' => 'State', 'county' => 'County'], []],
    // Spain
    [SpainGeographyProvider::class, ['autonomous_community' => 'Comunidad Autónoma', 'autonomous_city' => 'Ciudad Autónoma', 'province' => 'Provincia'], []],
    // SriLanka
    [SriLankaGeographyProvider::class, ['province' => 'Province', 'district' => 'District'], []],
    // Sudan
    [SudanGeographyProvider::class, ['state' => 'State', 'district' => 'District'], []],
    // Suriname
    [SurinameGeographyProvider::class, ['district' => 'District', 'resort' => 'Ressort'], []],
    // Sweden
    [SwedenGeographyProvider::class, ['county' => 'Län', 'municipality' => 'Kommun'], []],
    // Switzerland
    [SwitzerlandGeographyProvider::class, ['canton' => 'Canton', 'district' => 'District'], []],
    // Syria
    [SyriaGeographyProvider::class, ['province' => 'Province', 'district' => 'District'], []],
    // Taiwan
    [TaiwanGeographyProvider::class, ['special_municipality' => 'Special Municipality', 'county' => 'County', 'city' => 'City', 'district' => 'District', 'mountain_indigenous_district' => 'Mountain Indigenous District', 'county_administered_city' => 'County Administered City', 'urban_township' => 'Urban Township', 'rural_township' => 'Rural Township', 'mountain_indigenous_township' => 'Mountain Indigenous Township'], []],
    // Tajikistan
    [TajikistanGeographyProvider::class, ['region' => 'Viloyat', 'district' => 'Nohiya', 'city' => 'Shahr', 'capital_territory' => 'Capital Territory', 'autonomous_region' => 'Autonomous Region', 'districts_under_republic_administration' => 'Districts Under Republic Administration'], []],
    // Tanzania
    [TanzaniaGeographyProvider::class, ['region' => 'Region', 'district' => 'District'], []],
    // Thailand
    [ThailandGeographyProvider::class, ['province' => 'Changwat', 'metropolitan_administration' => 'Metropolitan Administration', 'amphoe' => 'Amphoe', 'khet' => 'Khet'], []],
    // TimorLeste
    [TimorLesteGeographyProvider::class, ['municipality' => 'Municipality', 'special_administrative_region' => 'Special Administrative Region', 'administrative_post' => 'Administrative Post'], []],
    // Togo
    [TogoGeographyProvider::class, ['region' => 'Région', 'prefecture' => 'Préfecture'], []],
    // Tonga
    [TongaGeographyProvider::class, ['division' => 'Division', 'district' => 'District'], []],
    // TrinidadAndTobago
    [TrinidadAndTobagoGeographyProvider::class, ['region' => 'Region', 'borough' => 'Borough', 'city' => 'City', 'ward' => 'Ward'], []],
    // Tunisia
    [TunisiaGeographyProvider::class, ['governorate' => 'Gouvernorat', 'delegation' => 'Délégation'], []],
    // Turkiye
    [TurkiyeGeographyProvider::class, ['province' => 'İl', 'district' => 'İlçe'], []],
    // Turkmenistan
    [TurkmenistanGeographyProvider::class, ['region' => 'Welaýat', 'city' => 'Şäher', 'district' => 'Etrap'], []],
    // TurksAndCaicos
    [TurksAndCaicosGeographyProvider::class, ['district' => 'District'], []],
    // Tuvalu
    [TuvaluGeographyProvider::class, ['island_council' => 'Island Council', 'town_council' => 'Town Council'], []],
    // USMinorOutlyingIslands
    [USMinorOutlyingIslandsGeographyProvider::class, ['island' => 'Island'], []],
    // USVirginIslands
    [USVirginIslandsGeographyProvider::class, ['district' => 'District', 'subdistrict' => 'Subdistrict'], []],
    // Uganda
    [UgandaGeographyProvider::class, ['region' => 'Region', 'district' => 'District', 'city' => 'City'], []],
    // Ukraine
    [UkraineGeographyProvider::class, ['oblast' => 'Oblast', 'city' => 'City', 'republic' => 'Republic', 'raion' => 'Raion'], []],
    // UnitedArabEmirates
    [UnitedArabEmiratesGeographyProvider::class, ['emirate' => 'Emirate'], []],
    // UnitedKingdom
    [UnitedKingdomGeographyProvider::class, ['nation' => 'Nation', 'county' => 'County', 'council_area' => 'Council Area', 'county_borough' => 'County Borough', 'district' => 'District'], []],
    // UnitedStates
    [UnitedStatesGeographyProvider::class, ['state' => 'State', 'district' => 'District', 'territory' => 'Territory', 'county' => 'County', 'parish' => 'Parish', 'borough' => 'Borough', 'census_area' => 'Census Area', 'city' => 'City', 'municipality' => 'Municipality', 'planning_region' => 'Planning Region'], []],
    // Uruguay
    [UruguayGeographyProvider::class, ['department' => 'Departamento', 'municipality' => 'Municipio'], []],
    // Uzbekistan
    [UzbekistanGeographyProvider::class, ['city' => 'Shahar', 'region' => 'Region', 'republic' => 'Republic', 'tuman' => 'Tuman'], []],
    // Vanuatu
    [VanuatuGeographyProvider::class, ['province' => 'Province', 'area_council' => 'Area Council', 'municipality' => 'Municipality'], []],
    // Venezuela
    [VenezuelaGeographyProvider::class, ['state' => 'Estado', 'capital_district' => 'Distrito Capital', 'federal_dependency' => 'Dependencias Federales', 'municipality' => 'Municipio'], []],
    // Vietnam
    [VietnamGeographyProvider::class, ['province' => 'Tỉnh', 'municipality' => 'Thành phố', 'commune' => 'Xã', 'ward' => 'Phường', 'special_zone' => 'Đặc khu'], []],
    // WallisAndFutuna
    [WallisAndFutunaGeographyProvider::class, ['administrative_precinct' => 'Circonscription', 'district' => 'District'], []],
    // Yemen
    [YemenGeographyProvider::class, ['governorate' => 'Muhafaza', 'municipality' => 'Municipality', 'district' => 'District'], []],
    // Zambia
    [ZambiaGeographyProvider::class, ['province' => 'Province', 'district' => 'District'], []],
    // Zimbabwe
    [ZimbabweGeographyProvider::class, ['province' => 'Province', 'district' => 'District'], []],
]);
it('implements the area type label contract for every configured provider', function (): void {
    $providers = config('addressing.geography.providers');

    expect($providers)->toBeArray()->not->toBeEmpty();

    foreach ($providers as $providerClass) {
        $provider = app($providerClass);

        expect($provider)->toBeInstanceOf(CountryAreaTypeLabelProvider::class, "Provider {$providerClass} must implement CountryAreaTypeLabelProvider");
    }
});

it('declares a non-empty base label for every hierarchy type', function (): void {
    foreach (config('addressing.geography.providers') as $providerClass) {
        $provider = app($providerClass);

        $types = [];

        foreach ($provider->addressHierarchies() as $hierarchy) {
            foreach ($hierarchy->levels as $level) {
                $levelTypes = $level->areaTypes;

                if ($levelTypes === [] && $level->areaType !== null) {
                    $levelTypes = [$level->areaType];
                }

                foreach ($levelTypes as $type) {
                    if (! in_array($type, $types, true)) {
                        $types[] = $type;
                    }
                }
            }
        }

        $labels = $provider->areaTypeLabels();

        expect($labels)->toBeArray()->not->toBeEmpty("Provider {$providerClass} must declare base labels");

        foreach ($types as $type) {
            expect($labels[$type] ?? null)
                ->toBeString("Provider {$providerClass} is missing a base label for hierarchy type {$type}")
                ->not->toBeEmpty();
        }
    }
});

it('declares a base label for every state override type', function (): void {
    foreach (config('addressing.geography.providers') as $providerClass) {
        $provider = app($providerClass);
        $labels = $provider->areaTypeLabels();

        foreach ($provider->stateAreaTypeLabels() as $override) {
            foreach (($override['type_labels'] ?? []) as $type => $label) {
                expect($label)->toBeString()->not->toBeEmpty();
                expect($labels[$type] ?? null)
                    ->toBeString("Provider {$providerClass} override for {$type} is missing a base label")
                    ->not->toBeEmpty();
            }
        }
    }
});

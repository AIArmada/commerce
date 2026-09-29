<?php

declare(strict_types=1);

use AIArmada\Addressing\Contracts\CountryGeographyProvider;
use AIArmada\Addressing\Geography\Aland\AlandGeographyProvider;
use AIArmada\Addressing\Geography\Albania\AlbaniaGeographyProvider;
use AIArmada\Addressing\Geography\Andorra\AndorraGeographyProvider;
use AIArmada\Addressing\Geography\Angola\AngolaGeographyProvider;
use AIArmada\Addressing\Geography\Argentina\ArgentinaGeographyProvider;
use AIArmada\Addressing\Geography\Armenia\ArmeniaGeographyProvider;
use AIArmada\Addressing\Geography\Austria\AustriaGeographyProvider;
use AIArmada\Addressing\Geography\Azerbaijan\AzerbaijanGeographyProvider;
use AIArmada\Addressing\Geography\Belgium\BelgiumGeographyProvider;
use AIArmada\Addressing\Geography\Benin\BeninGeographyProvider;
use AIArmada\Addressing\Geography\Bhutan\BhutanGeographyProvider;
use AIArmada\Addressing\Geography\Bolivia\BoliviaGeographyProvider;
use AIArmada\Addressing\Geography\BosniaAndHerzegovina\BosniaAndHerzegovinaGeographyProvider;
use AIArmada\Addressing\Geography\Brazil\BrazilGeographyProvider;
use AIArmada\Addressing\Geography\Brunei\BruneiGeographyProvider;
use AIArmada\Addressing\Geography\Bulgaria\BulgariaGeographyProvider;
use AIArmada\Addressing\Geography\BurkinaFaso\BurkinaFasoGeographyProvider;
use AIArmada\Addressing\Geography\Burundi\BurundiGeographyProvider;
use AIArmada\Addressing\Geography\Cambodia\CambodiaGeographyProvider;
use AIArmada\Addressing\Geography\Cameroon\CameroonGeographyProvider;
use AIArmada\Addressing\Geography\CapeVerde\CapeVerdeGeographyProvider;
use AIArmada\Addressing\Geography\CaribbeanNetherlands\CaribbeanNetherlandsGeographyProvider;
use AIArmada\Addressing\Geography\CentralAfricanRepublic\CentralAfricanRepublicGeographyProvider;
use AIArmada\Addressing\Geography\Chad\ChadGeographyProvider;
use AIArmada\Addressing\Geography\Chile\ChileGeographyProvider;
use AIArmada\Addressing\Geography\Colombia\ColombiaGeographyProvider;
use AIArmada\Addressing\Geography\Comoros\ComorosGeographyProvider;
use AIArmada\Addressing\Geography\Congo\CongoGeographyProvider;
use AIArmada\Addressing\Geography\CostaRica\CostaRicaGeographyProvider;
use AIArmada\Addressing\Geography\Croatia\CroatiaGeographyProvider;
use AIArmada\Addressing\Geography\Cuba\CubaGeographyProvider;
use AIArmada\Addressing\Geography\CzechRepublic\CzechRepublicGeographyProvider;
use AIArmada\Addressing\Geography\DemocraticRepublicOfCongo\DemocraticRepublicOfCongoGeographyProvider;
use AIArmada\Addressing\Geography\Denmark\DenmarkGeographyProvider;
use AIArmada\Addressing\Geography\Djibouti\DjiboutiGeographyProvider;
use AIArmada\Addressing\Geography\DominicanRepublic\DominicanRepublicGeographyProvider;
use AIArmada\Addressing\Geography\Ecuador\EcuadorGeographyProvider;
use AIArmada\Addressing\Geography\ElSalvador\ElSalvadorGeographyProvider;
use AIArmada\Addressing\Geography\EquatorialGuinea\EquatorialGuineaGeographyProvider;
use AIArmada\Addressing\Geography\Eritrea\EritreaGeographyProvider;
use AIArmada\Addressing\Geography\Estonia\EstoniaGeographyProvider;
use AIArmada\Addressing\Geography\Ethiopia\EthiopiaGeographyProvider;
use AIArmada\Addressing\Geography\FaroeIslands\FaroeIslandsGeographyProvider;
use AIArmada\Addressing\Geography\Finland\FinlandGeographyProvider;
use AIArmada\Addressing\Geography\France\FranceGeographyProvider;
use AIArmada\Addressing\Geography\FrenchGuiana\FrenchGuianaGeographyProvider;
use AIArmada\Addressing\Geography\FrenchPolynesia\FrenchPolynesiaGeographyProvider;
use AIArmada\Addressing\Geography\Gabon\GabonGeographyProvider;
use AIArmada\Addressing\Geography\Georgia\GeorgiaGeographyProvider;
use AIArmada\Addressing\Geography\Germany\GermanyGeographyProvider;
use AIArmada\Addressing\Geography\Greece\GreeceGeographyProvider;
use AIArmada\Addressing\Geography\Greenland\GreenlandGeographyProvider;
use AIArmada\Addressing\Geography\Guadeloupe\GuadeloupeGeographyProvider;
use AIArmada\Addressing\Geography\Guatemala\GuatemalaGeographyProvider;
use AIArmada\Addressing\Geography\Guinea\GuineaGeographyProvider;
use AIArmada\Addressing\Geography\GuineaBissau\GuineaBissauGeographyProvider;
use AIArmada\Addressing\Geography\Haiti\HaitiGeographyProvider;
use AIArmada\Addressing\Geography\Honduras\HondurasGeographyProvider;
use AIArmada\Addressing\Geography\Hungary\HungaryGeographyProvider;
use AIArmada\Addressing\Geography\Iceland\IcelandGeographyProvider;
use AIArmada\Addressing\Geography\Iran\IranGeographyProvider;
use AIArmada\Addressing\Geography\Iraq\IraqGeographyProvider;
use AIArmada\Addressing\Geography\Italy\ItalyGeographyProvider;
use AIArmada\Addressing\Geography\IvoryCoast\IvoryCoastGeographyProvider;
use AIArmada\Addressing\Geography\Jordan\JordanGeographyProvider;
use AIArmada\Addressing\Geography\Kazakhstan\KazakhstanGeographyProvider;
use AIArmada\Addressing\Geography\Kosovo\KosovoGeographyProvider;
use AIArmada\Addressing\Geography\Kuwait\KuwaitGeographyProvider;
use AIArmada\Addressing\Geography\Kyrgyzstan\KyrgyzstanGeographyProvider;
use AIArmada\Addressing\Geography\Laos\LaosGeographyProvider;
use AIArmada\Addressing\Geography\Latvia\LatviaGeographyProvider;
use AIArmada\Addressing\Geography\Lebanon\LebanonGeographyProvider;
use AIArmada\Addressing\Geography\Liechtenstein\LiechtensteinGeographyProvider;
use AIArmada\Addressing\Geography\Lithuania\LithuaniaGeographyProvider;
use AIArmada\Addressing\Geography\Madagascar\MadagascarGeographyProvider;
use AIArmada\Addressing\Geography\Mali\MaliGeographyProvider;
use AIArmada\Addressing\Geography\Martinique\MartiniqueGeographyProvider;
use AIArmada\Addressing\Geography\Mauritania\MauritaniaGeographyProvider;
use AIArmada\Addressing\Geography\Mexico\MexicoGeographyProvider;
use AIArmada\Addressing\Geography\Moldova\MoldovaGeographyProvider;
use AIArmada\Addressing\Geography\Monaco\MonacoGeographyProvider;
use AIArmada\Addressing\Geography\Mongolia\MongoliaGeographyProvider;
use AIArmada\Addressing\Geography\Montenegro\MontenegroGeographyProvider;
use AIArmada\Addressing\Geography\Morocco\MoroccoGeographyProvider;
use AIArmada\Addressing\Geography\Mozambique\MozambiqueGeographyProvider;
use AIArmada\Addressing\Geography\Nepal\NepalGeographyProvider;
use AIArmada\Addressing\Geography\Netherlands\NetherlandsGeographyProvider;
use AIArmada\Addressing\Geography\NewCaledonia\NewCaledoniaGeographyProvider;
use AIArmada\Addressing\Geography\Nicaragua\NicaraguaGeographyProvider;
use AIArmada\Addressing\Geography\Niger\NigerGeographyProvider;
use AIArmada\Addressing\Geography\Nigeria\NigeriaGeographyProvider;
use AIArmada\Addressing\Geography\NorthMacedonia\NorthMacedoniaGeographyProvider;
use AIArmada\Addressing\Geography\Norway\NorwayGeographyProvider;
use AIArmada\Addressing\Geography\Oman\OmanGeographyProvider;
use AIArmada\Addressing\Geography\Palestine\PalestineGeographyProvider;
use AIArmada\Addressing\Geography\Panama\PanamaGeographyProvider;
use AIArmada\Addressing\Geography\Paraguay\ParaguayGeographyProvider;
use AIArmada\Addressing\Geography\Peru\PeruGeographyProvider;
use AIArmada\Addressing\Geography\Poland\PolandGeographyProvider;
use AIArmada\Addressing\Geography\Portugal\PortugalGeographyProvider;
use AIArmada\Addressing\Geography\PuertoRico\PuertoRicoGeographyProvider;
use AIArmada\Addressing\Geography\Reunion\ReunionGeographyProvider;
use AIArmada\Addressing\Geography\Romania\RomaniaGeographyProvider;
use AIArmada\Addressing\Geography\SanMarino\SanMarinoGeographyProvider;
use AIArmada\Addressing\Geography\SaoTomeAndPrincipe\SaoTomeAndPrincipeGeographyProvider;
use AIArmada\Addressing\Geography\SaudiArabia\SaudiArabiaGeographyProvider;
use AIArmada\Addressing\Geography\Senegal\SenegalGeographyProvider;
use AIArmada\Addressing\Geography\Serbia\SerbiaGeographyProvider;
use AIArmada\Addressing\Geography\Slovakia\SlovakiaGeographyProvider;
use AIArmada\Addressing\Geography\Slovenia\SloveniaGeographyProvider;
use AIArmada\Addressing\Geography\Spain\SpainGeographyProvider;
use AIArmada\Addressing\Geography\Suriname\SurinameGeographyProvider;
use AIArmada\Addressing\Geography\Sweden\SwedenGeographyProvider;
use AIArmada\Addressing\Geography\Tajikistan\TajikistanGeographyProvider;
use AIArmada\Addressing\Geography\Thailand\ThailandGeographyProvider;
use AIArmada\Addressing\Geography\Togo\TogoGeographyProvider;
use AIArmada\Addressing\Geography\Tunisia\TunisiaGeographyProvider;
use AIArmada\Addressing\Geography\Turkiye\TurkiyeGeographyProvider;
use AIArmada\Addressing\Geography\Turkmenistan\TurkmenistanGeographyProvider;
use AIArmada\Addressing\Geography\Uruguay\UruguayGeographyProvider;
use AIArmada\Addressing\Geography\Uzbekistan\UzbekistanGeographyProvider;
use AIArmada\Addressing\Geography\Venezuela\VenezuelaGeographyProvider;
use AIArmada\Addressing\Geography\Vietnam\VietnamGeographyProvider;
use AIArmada\Addressing\Geography\WallisAndFutuna\WallisAndFutunaGeographyProvider;
use AIArmada\Addressing\Geography\Yemen\YemenGeographyProvider;

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
    // Aland
    [AlandGeographyProvider::class, ['municipality' => 'Kommun'], []],

    // Albania
    [AlbaniaGeographyProvider::class, ['county' => 'Qark', 'municipality' => 'Bashki'], []],

    // Andorra
    [AndorraGeographyProvider::class, ['parish' => 'Parròquia'], []],

    // Angola
    [AngolaGeographyProvider::class, ['province' => 'Província', 'municipality' => 'Município'], []],

    // Argentina
    [ArgentinaGeographyProvider::class, ['province' => 'Provincia', 'city' => 'Ciudad', 'commune' => 'Comuna', 'department' => 'Departamento'], []],

    // Armenia
    [ArmeniaGeographyProvider::class, ['region' => 'Marz', 'municipality' => 'Hamaynk'], []],

    // Austria
    [AustriaGeographyProvider::class, ['state' => 'Bundesland', 'district' => 'Bezirk', 'statutory_city' => 'Statutarstadt'], []],

    // Azerbaijan
    [AzerbaijanGeographyProvider::class, ['district' => 'Rayon', 'municipality' => 'Şəhər', 'autonomous_republic' => 'Muxtar Respublika', 'local_municipality' => 'Bələdiyyə'], []],

    // Belgium
    [BelgiumGeographyProvider::class, [], [
        ['state_code' => 'VLG', 'type_labels' => ['region' => 'Gewest', 'province' => 'Provincie']],
        ['state_code' => 'WAL', 'type_labels' => ['region' => 'Région', 'province' => 'Province']],
    ]],

    // Benin
    [BeninGeographyProvider::class, ['department' => 'Département', 'commune' => 'Commune'], []],

    // Bhutan
    [BhutanGeographyProvider::class, ['district' => 'Dzongkhag'], []],

    // Bolivia
    [BoliviaGeographyProvider::class, ['department' => 'Departamento', 'province' => 'Provincia'], []],

    // BosniaAndHerzegovina
    [BosniaAndHerzegovinaGeographyProvider::class, ['entity' => 'Entitet', 'district' => 'Distrikt', 'municipality' => 'Općina'], [
        ['state_code' => 'SRP', 'type_labels' => ['municipality' => 'Opština']],
    ]],

    // Brazil
    [BrazilGeographyProvider::class, ['state' => 'Estado', 'federal_district' => 'Distrito Federal', 'municipality' => 'Município', 'district' => 'Distrito'], []],

    // Brunei
    [BruneiGeographyProvider::class, ['district' => 'Daerah', 'mukim' => 'Mukim'], []],

    // Bulgaria
    [BulgariaGeographyProvider::class, ['district' => 'Oblast'], []],

    // BurkinaFaso
    [BurkinaFasoGeographyProvider::class, ['region' => 'Région', 'province' => 'Province'], []],

    // Burundi
    [BurundiGeographyProvider::class, ['province' => 'Province', 'commune' => 'Commune'], []],

    // Cambodia
    [CambodiaGeographyProvider::class, ['province' => 'Khet', 'municipality' => 'Krong', 'district' => 'Srok', 'section' => 'Khan'], []],

    // Cameroon
    [CameroonGeographyProvider::class, ['region' => 'Région', 'department' => 'Département'], []],

    // CapeVerde
    [CapeVerdeGeographyProvider::class, ['municipality' => 'Concelho', 'geographical_region' => 'Região Geográfica', 'parish' => 'Freguesia'], []],

    // CaribbeanNetherlands
    [CaribbeanNetherlandsGeographyProvider::class, ['special_municipality' => 'Bijzondere Gemeente'], []],

    // CentralAfricanRepublic
    [CentralAfricanRepublicGeographyProvider::class, ['prefecture' => 'Préfecture', 'economic_prefecture' => 'Préfecture Économique', 'subprefecture' => 'Sous-préfecture'], []],

    // Chad
    [ChadGeographyProvider::class, ['province' => 'Province', 'department' => 'Département'], []],

    // Chile
    [ChileGeographyProvider::class, ['region' => 'Región', 'province' => 'Provincia'], []],

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

    // CzechRepublic
    [CzechRepublicGeographyProvider::class, ['region' => 'Kraj', 'capital_city' => 'Hlavní Město', 'district' => 'Okres'], []],

    // DemocraticRepublicOfCongo
    [DemocraticRepublicOfCongoGeographyProvider::class, ['province' => 'Province', 'territory' => 'Territoire'], []],

    // Denmark
    [DenmarkGeographyProvider::class, ['region' => 'Region', 'municipality' => 'Kommune'], []],

    // Djibouti
    [DjiboutiGeographyProvider::class, ['region' => 'Région', 'city' => 'Ville', 'subprefecture' => 'Sous-préfecture'], []],

    // DominicanRepublic
    [DominicanRepublicGeographyProvider::class, ['region' => 'Región', 'province' => 'Provincia', 'district' => 'Distrito', 'municipality' => 'Municipio'], []],

    // Ecuador
    [EcuadorGeographyProvider::class, ['province' => 'Provincia', 'canton' => 'Cantón'], []],

    // ElSalvador
    [ElSalvadorGeographyProvider::class, ['department' => 'Departamento', 'municipality' => 'Municipio'], []],

    // EquatorialGuinea
    [EquatorialGuineaGeographyProvider::class, ['region' => 'Región', 'province' => 'Provincia'], []],

    // Eritrea
    [EritreaGeographyProvider::class, ['region' => 'Zoba'], []],

    // Estonia
    [EstoniaGeographyProvider::class, ['county' => 'Maakond', 'rural_municipality' => 'Vald', 'urban_municipality' => 'Linn'], []],

    // Ethiopia
    [EthiopiaGeographyProvider::class, ['region' => 'Kilil'], []],

    // FaroeIslands
    [FaroeIslandsGeographyProvider::class, ['municipality' => 'Kommuna'], []],

    // Finland
    [FinlandGeographyProvider::class, ['region' => 'Maakunta', 'city' => 'Kaupunki', 'municipality' => 'Kunta'], []],

    // France
    [FranceGeographyProvider::class, ['region' => 'Région', 'department' => 'Département'], []],

    // FrenchGuiana
    [FrenchGuianaGeographyProvider::class, ['overseas_region' => 'Région', 'commune' => 'Commune'], []],

    // FrenchPolynesia
    [FrenchPolynesiaGeographyProvider::class, ['division' => 'Subdivision', 'commune' => 'Commune'], []],

    // Gabon
    [GabonGeographyProvider::class, ['province' => 'Province', 'department' => 'Département'], []],

    // Georgia
    [GeorgiaGeographyProvider::class, ['region' => 'Mkhare'], []],

    // Germany
    [GermanyGeographyProvider::class, ['state' => 'Land', 'rural_district' => 'Landkreis', 'urban_district' => 'Kreisfreie Stadt'], []],

    // Greece
    [GreeceGeographyProvider::class, ['administrative_region' => 'Periféreia', 'municipality' => 'Dímos'], []],

    // Greenland
    [GreenlandGeographyProvider::class, ['municipality' => 'Kommune'], []],

    // Guadeloupe
    [GuadeloupeGeographyProvider::class, ['district' => 'Arrondissement', 'commune' => 'Commune'], []],

    // Guatemala
    [GuatemalaGeographyProvider::class, ['department' => 'Departamento', 'municipality' => 'Municipio'], []],

    // GuineaBissau
    [GuineaBissauGeographyProvider::class, ['region' => 'Região', 'autonomous_sector' => 'Sector Autónomo', 'sector' => 'Sector'], []],

    // Guinea
    [GuineaGeographyProvider::class, ['administrative_region' => 'Région', 'governorate' => 'Gouvernorat', 'prefecture' => 'Préfecture'], []],

    // Haiti
    [HaitiGeographyProvider::class, ['department' => 'Département', 'arrondissement' => 'Arrondissement'], []],

    // Honduras
    [HondurasGeographyProvider::class, ['department' => 'Departamento', 'municipality' => 'Municipio'], []],

    // Hungary
    [HungaryGeographyProvider::class, ['county' => 'Vármegye', 'city_with_county_rights' => 'Megyei Jogú Város', 'capital_city' => 'Főváros', 'district' => 'Járás'], []],

    // Iceland
    [IcelandGeographyProvider::class, ['region' => 'Landsvæði', 'municipality' => 'Sveitarfélag'], []],

    // Iran
    [IranGeographyProvider::class, ['province' => 'Ostan', 'county' => 'Shahrestan'], []],

    // Iraq
    [IraqGeographyProvider::class, ['governorate' => 'Muhafaza', 'district' => 'Qadaa'], []],

    // Italy
    [ItalyGeographyProvider::class, ['region' => 'Regione'], []],

    // IvoryCoast
    [IvoryCoastGeographyProvider::class, ['autonomous_district' => 'District Autonome', 'district' => 'District', 'region' => 'Région'], []],

    // Jordan
    [JordanGeographyProvider::class, ['governorate' => 'Muhafaza', 'liwa' => 'Liwa'], []],

    // Kazakhstan
    [KazakhstanGeographyProvider::class, ['region' => 'Oblys', 'city' => 'Qala', 'district' => 'Audan'], []],

    // Kosovo
    [KosovoGeographyProvider::class, ['district' => 'Rajoni', 'municipality' => 'Komuna'], []],

    // Kuwait
    [KuwaitGeographyProvider::class, ['governorate' => 'Muhafaza'], []],

    // Kyrgyzstan
    [KyrgyzstanGeographyProvider::class, ['region' => 'Oblus', 'city' => 'Shaar', 'district' => 'Raion'], []],

    // Laos
    [LaosGeographyProvider::class, ['province' => 'Khoueng', 'district' => 'Muang'], []],

    // Latvia
    [LatviaGeographyProvider::class, ['municipality' => 'Novads', 'state_city' => 'Valstspilsēta', 'parish' => 'Pagasts', 'town' => 'Pilsēta', 'city' => 'Pilsēta'], []],

    // Lebanon
    [LebanonGeographyProvider::class, ['governorate' => 'Muhafaza'], []],

    // Liechtenstein
    [LiechtensteinGeographyProvider::class, ['commune' => 'Gemeinde'], []],

    // Lithuania
    [LithuaniaGeographyProvider::class, ['county' => 'Apskritis', 'district_municipality' => 'Rajono Savivaldybė', 'city_municipality' => 'Miesto Savivaldybė', 'municipality' => 'Savivaldybė'], []],

    // Madagascar
    [MadagascarGeographyProvider::class, ['province' => 'Faritany', 'region' => 'Faritra', 'district' => 'Distrika'], []],

    // Mali
    [MaliGeographyProvider::class, ['district' => 'District', 'region' => 'Région', 'cercle' => 'Cercle'], []],

    // Martinique
    [MartiniqueGeographyProvider::class, ['district' => 'Arrondissement', 'commune' => 'Commune'], []],

    // Mauritania
    [MauritaniaGeographyProvider::class, ['region' => 'Wilaya', 'department' => 'Moughataa'], []],

    // Mexico
    [MexicoGeographyProvider::class, ['state' => 'Estado', 'municipality' => 'Municipio', 'borough' => 'Alcaldía'], []],

    // Moldova
    [MoldovaGeographyProvider::class, ['district' => 'Raion', 'commune' => 'Comună', 'city' => 'Oraș'], []],

    // Monaco
    [MonacoGeographyProvider::class, ['quarter' => 'Quartier'], []],

    // Mongolia
    [MongoliaGeographyProvider::class, ['province' => 'Aimag', 'sum' => 'Sum', 'duureg' => 'Düüreg'], []],

    // Montenegro
    [MontenegroGeographyProvider::class, ['municipality' => 'Opština'], []],

    // Morocco
    [MoroccoGeographyProvider::class, ['region' => 'Région', 'prefecture' => 'Préfecture', 'province' => 'Province'], []],

    // Mozambique
    [MozambiqueGeographyProvider::class, ['province' => 'Província', 'city' => 'Cidade', 'district' => 'Distrito'], []],

    // Nepal
    [NepalGeographyProvider::class, ['province' => 'Pradesh', 'district' => 'Jilla'], []],

    // Netherlands
    [NetherlandsGeographyProvider::class, ['province' => 'Provincie', 'municipality' => 'Gemeente'], []],

    // NewCaledonia
    [NewCaledoniaGeographyProvider::class, ['province' => 'Province', 'commune' => 'Commune'], []],

    // Nicaragua
    [NicaraguaGeographyProvider::class, ['department' => 'Departamento', 'autonomous_region' => 'Región Autónoma', 'municipality' => 'Municipio'], []],

    // Niger
    [NigerGeographyProvider::class, ['region' => 'Région', 'urban_community' => 'Communauté Urbaine', 'department' => 'Département', 'commune' => 'Commune'], []],

    // Nigeria
    [NigeriaGeographyProvider::class, ['lga' => 'LGA'], []],

    // NorthMacedonia
    [NorthMacedoniaGeographyProvider::class, ['municipality' => 'Opština'], []],

    // Norway
    [NorwayGeographyProvider::class, ['county' => 'Fylke', 'municipality' => 'Kommune'], []],

    // Oman
    [OmanGeographyProvider::class, ['governorate' => 'Muhafaza'], []],

    // Palestine
    [PalestineGeographyProvider::class, ['governorate' => 'Muhafaza'], []],

    // Panama
    [PanamaGeographyProvider::class, ['province' => 'Provincia', 'indigenous_region' => 'Comarca Indígena', 'district' => 'Distrito'], []],

    // Paraguay
    [ParaguayGeographyProvider::class, ['department' => 'Departamento', 'capital_district' => 'Distrito Capital', 'district' => 'Distrito'], []],

    // Peru
    [PeruGeographyProvider::class, ['region' => 'Región', 'municipality' => 'Municipalidad', 'province' => 'Provincia'], []],

    // Poland
    [PolandGeographyProvider::class, ['voivodeship' => 'Województwo', 'land_county' => 'Powiat Ziemski', 'city_county' => 'Powiat Grodzki'], []],

    // Portugal
    [PortugalGeographyProvider::class, ['autonomous_region' => 'Região Autónoma', 'district' => 'Distrito', 'municipality' => 'Município'], []],

    // PuertoRico
    [PuertoRicoGeographyProvider::class, ['municipality' => 'Municipio', 'barrio_pueblo' => 'Barrio-Pueblo', 'barrio' => 'Barrio'], []],

    // Reunion
    [ReunionGeographyProvider::class, ['district' => 'Arrondissement', 'commune' => 'Commune'], []],

    // Romania
    [RomaniaGeographyProvider::class, ['department' => 'Județ', 'municipality' => 'Municipiu', 'commune' => 'Comună', 'town' => 'Oraș', 'sector' => 'Sector'], []],

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

    // Slovakia
    [SlovakiaGeographyProvider::class, ['region' => 'Kraj', 'district' => 'Okres'], []],

    // Slovenia
    [SloveniaGeographyProvider::class, ['municipality' => 'Občina', 'urban_municipality' => 'Mestna občina'], []],

    // Spain
    [SpainGeographyProvider::class, ['autonomous_community' => 'Comunidad Autónoma', 'autonomous_city' => 'Ciudad Autónoma', 'province' => 'Provincia'], []],

    // Suriname
    [SurinameGeographyProvider::class, ['district' => 'District', 'resort' => 'Ressort'], []],

    // Sweden
    [SwedenGeographyProvider::class, ['county' => 'Län', 'municipality' => 'Kommun'], []],

    // Tajikistan
    [TajikistanGeographyProvider::class, ['region' => 'Viloyat', 'district' => 'Nohiya', 'city' => 'Shahr'], []],

    // Thailand
    [ThailandGeographyProvider::class, ['province' => 'Changwat'], []],

    // Togo
    [TogoGeographyProvider::class, ['region' => 'Région', 'prefecture' => 'Préfecture'], []],

    // Tunisia
    [TunisiaGeographyProvider::class, ['governorate' => 'Gouvernorat', 'delegation' => 'Délégation'], []],

    // Turkiye
    [TurkiyeGeographyProvider::class, ['province' => 'İl', 'district' => 'İlçe'], []],

    // Turkmenistan
    [TurkmenistanGeographyProvider::class, ['region' => 'Welaýat', 'city' => 'Şäher', 'district' => 'Etrap'], []],

    // Uruguay
    [UruguayGeographyProvider::class, ['department' => 'Departamento', 'municipality' => 'Municipio'], []],

    // Uzbekistan
    [UzbekistanGeographyProvider::class, ['city' => 'Shahar'], []],

    // Venezuela
    [VenezuelaGeographyProvider::class, ['state' => 'Estado', 'capital_district' => 'Distrito Capital', 'federal_dependency' => 'Dependencias Federales', 'municipality' => 'Municipio'], []],

    // Vietnam
    [VietnamGeographyProvider::class, ['province' => 'Tỉnh', 'municipality' => 'Thành phố', 'commune' => 'Xã', 'ward' => 'Phường', 'special_zone' => 'Đặc khu'], []],

    // WallisAndFutuna
    [WallisAndFutunaGeographyProvider::class, ['administrative_precinct' => 'Circonscription', 'district' => 'District'], []],

    // Yemen
    [YemenGeographyProvider::class, ['governorate' => 'Muhafaza', 'municipality' => 'Municipality', 'district' => 'District'], []],
]);

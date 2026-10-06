<?php

declare(strict_types=1);

use AIArmada\Addressing\Contracts\CountryHierarchyProvider;
use AIArmada\Addressing\Geography\Albania\AlbaniaGeographyProvider;
use AIArmada\Addressing\Geography\AmericanSamoa\AmericanSamoaGeographyProvider;
use AIArmada\Addressing\Geography\Armenia\ArmeniaGeographyProvider;
use AIArmada\Addressing\Geography\Austria\AustriaGeographyProvider;
use AIArmada\Addressing\Geography\Belarus\BelarusGeographyProvider;
use AIArmada\Addressing\Geography\Benin\BeninGeographyProvider;
use AIArmada\Addressing\Geography\Bhutan\BhutanGeographyProvider;
use AIArmada\Addressing\Geography\Bolivia\BoliviaGeographyProvider;
use AIArmada\Addressing\Geography\BosniaAndHerzegovina\BosniaAndHerzegovinaGeographyProvider;
use AIArmada\Addressing\Geography\Botswana\BotswanaGeographyProvider;
use AIArmada\Addressing\Geography\Brazil\BrazilGeographyProvider;
use AIArmada\Addressing\Geography\Brunei\BruneiGeographyProvider;
use AIArmada\Addressing\Geography\Bulgaria\BulgariaGeographyProvider;
use AIArmada\Addressing\Geography\Burundi\BurundiGeographyProvider;
use AIArmada\Addressing\Geography\Cambodia\CambodiaGeographyProvider;
use AIArmada\Addressing\Geography\Cameroon\CameroonGeographyProvider;
use AIArmada\Addressing\Geography\Canada\CanadaGeographyProvider;
use AIArmada\Addressing\Geography\CapeVerde\CapeVerdeGeographyProvider;
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
use AIArmada\Addressing\Geography\Ecuador\EcuadorGeographyProvider;
use AIArmada\Addressing\Geography\ElSalvador\ElSalvadorGeographyProvider;
use AIArmada\Addressing\Geography\Eritrea\EritreaGeographyProvider;
use AIArmada\Addressing\Geography\Eswatini\EswatiniGeographyProvider;
use AIArmada\Addressing\Geography\Ethiopia\EthiopiaGeographyProvider;
use AIArmada\Addressing\Geography\FaroeIslands\FaroeIslandsGeographyProvider;
use AIArmada\Addressing\Geography\Finland\FinlandGeographyProvider;
use AIArmada\Addressing\Geography\France\FranceGeographyProvider;
use AIArmada\Addressing\Geography\FrenchGuiana\FrenchGuianaGeographyProvider;
use AIArmada\Addressing\Geography\FrenchPolynesia\FrenchPolynesiaGeographyProvider;
use AIArmada\Addressing\Geography\FrenchSouthernTerritories\FrenchSouthernTerritoriesGeographyProvider;
use AIArmada\Addressing\Geography\Gabon\GabonGeographyProvider;
use AIArmada\Addressing\Geography\Gambia\GambiaGeographyProvider;
use AIArmada\Addressing\Geography\Georgia\GeorgiaGeographyProvider;
use AIArmada\Addressing\Geography\Ghana\GhanaGeographyProvider;
use AIArmada\Addressing\Geography\Greece\GreeceGeographyProvider;
use AIArmada\Addressing\Geography\Greenland\GreenlandGeographyProvider;
use AIArmada\Addressing\Geography\Grenada\GrenadaGeographyProvider;
use AIArmada\Addressing\Geography\Guadeloupe\GuadeloupeGeographyProvider;
use AIArmada\Addressing\Geography\Guatemala\GuatemalaGeographyProvider;
use AIArmada\Addressing\Geography\Guernsey\GuernseyGeographyProvider;
use AIArmada\Addressing\Geography\GuineaBissau\GuineaBissauGeographyProvider;
use AIArmada\Addressing\Geography\Guyana\GuyanaGeographyProvider;
use AIArmada\Addressing\Geography\Haiti\HaitiGeographyProvider;
use AIArmada\Addressing\Geography\Honduras\HondurasGeographyProvider;
use AIArmada\Addressing\Geography\HongKong\HongKongGeographyProvider;
use AIArmada\Addressing\Geography\India\IndiaGeographyProvider;
use AIArmada\Addressing\Geography\Iraq\IraqGeographyProvider;
use AIArmada\Addressing\Geography\IsleOfMan\IsleOfManGeographyProvider;
use AIArmada\Addressing\Geography\Italy\ItalyGeographyProvider;
use AIArmada\Addressing\Geography\IvoryCoast\IvoryCoastGeographyProvider;
use AIArmada\Addressing\Geography\Jamaica\JamaicaGeographyProvider;
use AIArmada\Addressing\Geography\Jersey\JerseyGeographyProvider;
use AIArmada\Addressing\Geography\Kazakhstan\KazakhstanGeographyProvider;
use AIArmada\Addressing\Geography\Kenya\KenyaGeographyProvider;
use AIArmada\Addressing\Geography\Kosovo\KosovoGeographyProvider;
use AIArmada\Addressing\Geography\Kyrgyzstan\KyrgyzstanGeographyProvider;
use AIArmada\Addressing\Geography\Laos\LaosGeographyProvider;
use AIArmada\Addressing\Geography\Lebanon\LebanonGeographyProvider;
use AIArmada\Addressing\Geography\Lesotho\LesothoGeographyProvider;
use AIArmada\Addressing\Geography\Liberia\LiberiaGeographyProvider;
use AIArmada\Addressing\Geography\Liechtenstein\LiechtensteinGeographyProvider;
use AIArmada\Addressing\Geography\Luxembourg\LuxembourgGeographyProvider;
use AIArmada\Addressing\Geography\Madagascar\MadagascarGeographyProvider;
use AIArmada\Addressing\Geography\Maldives\MaldivesGeographyProvider;
use AIArmada\Addressing\Geography\Malta\MaltaGeographyProvider;
use AIArmada\Addressing\Geography\Martinique\MartiniqueGeographyProvider;
use AIArmada\Addressing\Geography\Mauritania\MauritaniaGeographyProvider;
use AIArmada\Addressing\Geography\Mauritius\MauritiusGeographyProvider;
use AIArmada\Addressing\Geography\Mayotte\MayotteGeographyProvider;
use AIArmada\Addressing\Geography\Mexico\MexicoGeographyProvider;
use AIArmada\Addressing\Geography\Mongolia\MongoliaGeographyProvider;
use AIArmada\Addressing\Geography\Montenegro\MontenegroGeographyProvider;
use AIArmada\Addressing\Geography\Montserrat\MontserratGeographyProvider;
use AIArmada\Addressing\Geography\Mozambique\MozambiqueGeographyProvider;
use AIArmada\Addressing\Geography\Namibia\NamibiaGeographyProvider;
use AIArmada\Addressing\Geography\Nauru\NauruGeographyProvider;
use AIArmada\Addressing\Geography\Nepal\NepalGeographyProvider;
use AIArmada\Addressing\Geography\Netherlands\NetherlandsGeographyProvider;
use AIArmada\Addressing\Geography\NewCaledonia\NewCaledoniaGeographyProvider;
use AIArmada\Addressing\Geography\Nicaragua\NicaraguaGeographyProvider;
use AIArmada\Addressing\Geography\Niger\NigerGeographyProvider;
use AIArmada\Addressing\Geography\Nigeria\NigeriaGeographyProvider;
use AIArmada\Addressing\Geography\Niue\NiueGeographyProvider;
use AIArmada\Addressing\Geography\NorthMacedonia\NorthMacedoniaGeographyProvider;
use AIArmada\Addressing\Geography\Norway\NorwayGeographyProvider;
use AIArmada\Addressing\Geography\Pakistan\PakistanGeographyProvider;
use AIArmada\Addressing\Geography\Palau\PalauGeographyProvider;
use AIArmada\Addressing\Geography\Palestine\PalestineGeographyProvider;
use AIArmada\Addressing\Geography\Panama\PanamaGeographyProvider;
use AIArmada\Addressing\Geography\PapuaNewGuinea\PapuaNewGuineaGeographyProvider;
use AIArmada\Addressing\Geography\Paraguay\ParaguayGeographyProvider;
use AIArmada\Addressing\Geography\Peru\PeruGeographyProvider;
use AIArmada\Addressing\Geography\Poland\PolandGeographyProvider;
use AIArmada\Addressing\Geography\Portugal\PortugalGeographyProvider;
use AIArmada\Addressing\Geography\Reunion\ReunionGeographyProvider;
use AIArmada\Addressing\Geography\Russia\RussiaGeographyProvider;
use AIArmada\Addressing\Geography\Rwanda\RwandaGeographyProvider;
use AIArmada\Addressing\Geography\SaintLucia\SaintLuciaGeographyProvider;
use AIArmada\Addressing\Geography\SaintVincentAndTheGrenadines\SaintVincentAndTheGrenadinesGeographyProvider;
use AIArmada\Addressing\Geography\SanMarino\SanMarinoGeographyProvider;
use AIArmada\Addressing\Geography\SaoTomeAndPrincipe\SaoTomeAndPrincipeGeographyProvider;
use AIArmada\Addressing\Geography\SaudiArabia\SaudiArabiaGeographyProvider;
use AIArmada\Addressing\Geography\Senegal\SenegalGeographyProvider;
use AIArmada\Addressing\Geography\Serbia\SerbiaGeographyProvider;
use AIArmada\Addressing\Geography\Seychelles\SeychellesGeographyProvider;
use AIArmada\Addressing\Geography\SierraLeone\SierraLeoneGeographyProvider;
use AIArmada\Addressing\Geography\Slovakia\SlovakiaGeographyProvider;
use AIArmada\Addressing\Geography\Somalia\SomaliaGeographyProvider;
use AIArmada\Addressing\Geography\SouthAfrica\SouthAfricaGeographyProvider;
use AIArmada\Addressing\Geography\SouthKorea\SouthKoreaGeographyProvider;
use AIArmada\Addressing\Geography\SouthSudan\SouthSudanGeographyProvider;
use AIArmada\Addressing\Geography\Sudan\SudanGeographyProvider;
use AIArmada\Addressing\Geography\Suriname\SurinameGeographyProvider;
use AIArmada\Addressing\Geography\Sweden\SwedenGeographyProvider;
use AIArmada\Addressing\Geography\Switzerland\SwitzerlandGeographyProvider;
use AIArmada\Addressing\Geography\Syria\SyriaGeographyProvider;
use AIArmada\Addressing\Geography\Tajikistan\TajikistanGeographyProvider;
use AIArmada\Addressing\Geography\Tanzania\TanzaniaGeographyProvider;
use AIArmada\Addressing\Geography\TimorLeste\TimorLesteGeographyProvider;
use AIArmada\Addressing\Geography\Togo\TogoGeographyProvider;
use AIArmada\Addressing\Geography\Tunisia\TunisiaGeographyProvider;
use AIArmada\Addressing\Geography\Turkmenistan\TurkmenistanGeographyProvider;
use AIArmada\Addressing\Geography\TurksAndCaicos\TurksAndCaicosGeographyProvider;
use AIArmada\Addressing\Geography\Uganda\UgandaGeographyProvider;
use AIArmada\Addressing\Geography\Ukraine\UkraineGeographyProvider;
use AIArmada\Addressing\Geography\UnitedArabEmirates\UnitedArabEmiratesGeographyProvider;
use AIArmada\Addressing\Geography\UnitedKingdom\UnitedKingdomGeographyProvider;
use AIArmada\Addressing\Geography\Uruguay\UruguayGeographyProvider;
use AIArmada\Addressing\Geography\USMinorOutlyingIslands\USMinorOutlyingIslandsGeographyProvider;
use AIArmada\Addressing\Geography\USVirginIslands\USVirginIslandsGeographyProvider;
use AIArmada\Addressing\Geography\Venezuela\VenezuelaGeographyProvider;
use AIArmada\Addressing\Geography\Yemen\YemenGeographyProvider;
use AIArmada\Addressing\Geography\Zambia\ZambiaGeographyProvider;
use AIArmada\Addressing\Geography\Zimbabwe\ZimbabweGeographyProvider;

/**
 * Canonical display names for a sample of each country's shipped areas.
 *
 * GeographyProviderContractTest already proves that every area parses, carries
 * a name, and points at a real parent, so this file does not repeat per-country
 * row counts or parent wiring. What it keeps is the part no generic rule can
 * check: that a known source id still resolves to the right display name, with
 * the right accents, casing, and transliteration. One row per country, holding
 * that country's source-id to name map.
 */
it('resolves canonical area names', function (string $provider, array $expected): void {
    $provider = app($provider);

    expect($provider)->toBeInstanceOf(CountryHierarchyProvider::class);

    $byId = $provider->addressAreaSource()->areas()->keyBy('sourceId');

    foreach ($expected as $sourceId => $name) {
        expect($byId->get($sourceId)?->name)->toBe($name, "area [{$sourceId}] resolved to the wrong name");
    }
})->with([
    'Albania' => [AlbaniaGeographyProvider::class, [
        'al:municipality:tirana' => 'Tirana',
        'al:municipality:durres' => 'Durrës',
        'al:municipality:shkoder' => 'Shkodër',
    ]],

    'AmericanSamoa' => [AmericanSamoaGeographyProvider::class, [
        'as:county:tau' => 'Taʻū',
    ]],

    'Armenia' => [ArmeniaGeographyProvider::class, [
        'am:municipality:gyumri' => 'Gyumri',
        'am:municipality:vanadzor' => 'Vanadzor',
        'am:district:kentron' => 'Kentron',
        'am:municipality:khoy' => 'Khoy',
    ]],

    'Austria' => [AustriaGeographyProvider::class, [
        'at:statutory_city:linz' => 'Linz',
        'at:district:linz-land' => 'Linz-Land',
        'at:statutory_city:salzburg' => 'Salzburg',
    ]],

    'Belarus' => [BelarusGeographyProvider::class, [
        'by:district:brest' => 'Brest',
        'by:district:minsk' => 'Minsk',
        'by:district:vitebsk' => 'Vitebsk',
    ]],

    'Benin' => [BeninGeographyProvider::class, [
        'bj:commune:kandi' => 'Kandi',
        'bj:commune:natitingou' => 'Natitingou',
        'bj:commune:ouidah' => 'Ouidah',
    ]],

    'Bhutan' => [BhutanGeographyProvider::class, [
        'bt:gewog:chhoekhor' => 'Chhoekhor',
        'bt:gewog:bongo' => 'Bongo',
        'bt:gewog:tang' => 'Tang',
    ]],

    'Bolivia' => [BoliviaGeographyProvider::class, [
        'bo:province:cercado' => 'Cercado',
        'bo:province:andres-ibanez' => 'Andrés Ibáñez',
    ]],

    'BosniaAndHerzegovina' => [BosniaAndHerzegovinaGeographyProvider::class, [
        'ba:municipality:banja-luka' => 'Banja Luka',
        'ba:municipality:mostar' => 'Mostar',
        'ba:municipality:bihac' => 'Bihać',
        'ba:municipality:stanari' => 'Stanari',
        'ba:municipality:istocno-novo-sarajevo' => 'Istočno Novo Sarajevo',
        'ba:municipality:bosanski-petrovac' => 'Bosanski Petrovac',
    ]],

    'Botswana' => [BotswanaGeographyProvider::class, [
        'bw:subdistrict:serowe' => 'Serowe',
        'bw:subdistrict:palapye' => 'Palapye',
        'bw:subdistrict:mochudi' => 'Mochudi',
    ]],

    'Brazil' => [BrazilGeographyProvider::class, [
        'br:municipality:3550308' => 'São Paulo',
        'br:municipality:5101837' => 'Boa Esperança do Norte',
        'br:municipality:5300108' => 'Brasília',
        'br:district:2605459' => 'Fernando de Noronha',
    ]],

    'Brunei' => [BruneiGeographyProvider::class, [
        'bn:mukim:pengkalan-batu' => 'Pengkalan Batu',
        'bn:mukim:bangar' => 'Bangar',
    ]],

    'Bulgaria' => [BulgariaGeographyProvider::class, [
        'bg:municipality:sofia' => 'Sofia',
        'bg:municipality:plovdiv' => 'Plovdiv',
        'bg:municipality:burgas' => 'Burgas',
    ]],

    'Burundi' => [BurundiGeographyProvider::class, [
        'bi:commune:karusi' => 'Karusi',
        'bi:commune:shombo' => 'Shombo',
        'bi:commune:mukaza' => 'Mukaza',
    ]],

    'Cambodia' => [CambodiaGeographyProvider::class, [
        'kh:district:mongkol-borey' => 'Mongkol Borey',
        'kh:municipality:kep' => 'Kep',
        'kh:section:chamkar-mon' => 'Chamkar Mon',
    ]],

    'Cameroon' => [CameroonGeographyProvider::class, [
        'cm:department:djerem' => 'Djérem',
        'cm:department:mfoundi' => 'Mfoundi',
        'cm:department:fako' => 'Fako',
    ]],

    'Canada' => [CanadaGeographyProvider::class, [
        'ca:municipality:3520005' => 'Toronto',
        'ca:municipality:5915022' => 'Vancouver',
        'ca:unorganized:1001101' => 'Division No.  1, Subd. V',
    ]],

    'CapeVerde' => [CapeVerdeGeographyProvider::class, [
        'cv:parish:santo-amaro-abade' => 'Santo Amaro Abade',
        'cv:parish:maio:nossa-senhora-da-luz' => 'Nossa Senhora da Luz',
    ]],

    'CentralAfricanRepublic' => [CentralAfricanRepublicGeographyProvider::class, [
        'cf:subprefecture:bamingui' => 'Bamingui',
        'cf:subprefecture:alindao' => 'Alindao',
        'cf:subprefecture:ndele' => 'Ndélé',
    ]],

    'Chad' => [ChadGeographyProvider::class, [
        'td:department:fitri' => 'Fitri',
        'td:department:fada' => 'Fada',
        'td:department:am-djarass' => 'Am-Djarass',
    ]],

    'Chile' => [ChileGeographyProvider::class, [
        'cl:province:santiago' => 'Santiago',
        'cl:province:valparaiso' => 'Valparaíso',
        'cl:province:concepcion' => 'Concepción',
    ]],

    'Colombia' => [ColombiaGeographyProvider::class, [
        'co:municipality:medellin' => 'Medellín',
        'co:locality:suba' => 'Suba',
        'co:municipality:cali' => 'Cali',
    ]],

    'Comoros' => [ComorosGeographyProvider::class, [
        'km:prefecture:moroni-bambao' => 'Moroni-Bambao',
        'km:prefecture:mutsamudu' => 'Mutsamudu',
        'km:prefecture:fomboni' => 'Fomboni',
    ]],

    'Congo' => [CongoGeographyProvider::class, [
        'cg:district:owando' => 'Owando',
        'cg:district:bokoma' => 'Bokoma',
        'cg:district:tchiamba-nzassi' => 'Tchiamba-Nzassi',
    ]],

    'CostaRica' => [CostaRicaGeographyProvider::class, [
        'cr:canton:rio-cuarto' => 'Río Cuarto',
        'cr:canton:monteverde' => 'Monteverde',
        'cr:canton:puerto-jimenez' => 'Puerto Jiménez',
    ]],

    'Croatia' => [CroatiaGeographyProvider::class, [
        'hr:town:split' => 'Split',
        'hr:town:rijeka' => 'Rijeka',
        'hr:town:dubrovnik' => 'Dubrovnik',
    ]],

    'Cuba' => [CubaGeographyProvider::class, [
        'cu:municipality:santiago-de-cuba' => 'Santiago de Cuba',
        'cu:municipality:centro-habana' => 'Centro Habana',
        'cu:municipality:habana-del-este' => 'Habana del Este',
    ]],

    'CzechRepublic' => [CzechRepublicGeographyProvider::class, [
        'cz:district:benesov' => 'Benešov',
        'cz:district:beroun' => 'Beroun',
    ]],

    'DemocraticRepublicOfCongo' => [DemocraticRepublicOfCongoGeographyProvider::class, [
        'cd:territory:aketi' => 'Aketi',
        'cd:territory:bagata' => 'Bagata',
        'cd:territory:beni' => 'Beni',
    ]],

    'Denmark' => [DenmarkGeographyProvider::class, [
        'dk:municipality:copenhagen' => 'Copenhagen',
        'dk:municipality:aarhus' => 'Aarhus',
        'dk:municipality:odense' => 'Odense',
    ]],

    'Ecuador' => [EcuadorGeographyProvider::class, [
        'ec:canton:distrito-metropolitano-de-quito' => 'Distrito Metropolitano de Quito',
        'ec:canton:cuenca' => 'Cuenca',
        'ec:canton:guayaquil' => 'Guayaquil',
    ]],

    'ElSalvador' => [ElSalvadorGeographyProvider::class, [
        'sv:municipality:central-ahuachapan' => 'Central Ahuachapán',
        'sv:municipality:northern-ahuachapan' => 'Northern Ahuachapán',
        'sv:municipality:southern-ahuachapan' => 'Southern Ahuachapán',
    ]],

    'Eritrea' => [EritreaGeographyProvider::class, [
        'er:subregion:keren' => 'Keren',
        'er:subregion:adi-quala' => 'Adi Quala',
        'er:subregion:massawa' => 'Massawa',
    ]],

    'Eswatini' => [EswatiniGeographyProvider::class, [
        'sz:inkhundla:lobamba' => 'Lobamba',
        'sz:inkhundla:mbabane-west' => 'Mbabane West',
        'sz:inkhundla:gilgal' => 'Gilgal',
    ]],

    'Ethiopia' => [EthiopiaGeographyProvider::class, [
        'et:zone:gurage' => 'Gurage',
        'et:woreda:sofi' => 'Sofi',
        'et:zone:bole' => 'Bole',
        'et:zone:borana' => 'Borena',
        'et:zone:east-welega-gimbie' => 'East Welega',
        'et:zone:west-haraghe' => 'West Hararghe',
        'et:zone:mekele' => 'Mekelle',
    ]],

    'FaroeIslands' => [FaroeIslandsGeographyProvider::class, [
        'fo:municipality:torshavn' => 'Tórshavn',
        'fo:municipality:klaksvik' => 'Klaksvík',
        'fo:municipality:sunda' => 'Sunda',
    ]],

    'Finland' => [FinlandGeographyProvider::class, [
        'fi:city:helsinki' => 'Helsinki',
        'fi:city:espoo' => 'Espoo',
        'fi:city:tampere' => 'Tampere',
    ]],

    'France' => [FranceGeographyProvider::class, [
        'fr:department:paris' => 'Paris',
        'fr:department:nord' => 'Nord',
        'fr:department:bouches-du-rhone' => 'Bouches-du-Rhône',
    ]],

    'FrenchGuiana' => [FrenchGuianaGeographyProvider::class, [
        'gf:commune:cayenne' => 'Cayenne',
        'gf:commune:kourou' => 'Kourou',
        'gf:commune:saint-laurent-du-maroni' => 'Saint-Laurent-du-Maroni',
    ]],

    'FrenchPolynesia' => [FrenchPolynesiaGeographyProvider::class, [
        'pf:commune:papeete' => 'Papeete',
        'pf:commune:fatu-hiva' => 'Fatu-Hiva',
        'pf:commune:ua-pou' => 'Ua-Pou',
    ]],

    'FrenchSouthernTerritories' => [FrenchSouthernTerritoriesGeographyProvider::class, [
        'tf:district:adelie-land' => 'Adélie Land',
    ]],

    'Gabon' => [GabonGeographyProvider::class, [
        'ga:department:komo' => 'Komo',
        'ga:department:noya' => 'Noya',
        'ga:department:libreville' => 'Libreville',
    ]],

    'Gambia' => [GambiaGeographyProvider::class, [
        'gm:district:banjul-central' => 'Banjul Central',
        'gm:district:basse-fulladu-east' => 'Basse Fulladu East',
        'gm:city:kanifing' => 'Kanifing',
    ]],

    'Georgia' => [GeorgiaGeographyProvider::class, [
        'ge:municipality:gori' => 'Gori',
        'ge:city:batumi' => 'Batumi',
        'ge:district:gldani' => 'Gldani',
    ]],

    'Ghana' => [GhanaGeographyProvider::class, [
        'gh:metropolitan_city:kumasi' => 'Kumasi',
        'gh:municipality:asunafo-north' => 'Asunafo North',
        'gh:district:birim-north' => 'Birim North',
    ]],

    'Greece' => [GreeceGeographyProvider::class, [
        'gr:municipality:athens' => 'Athens',
        'gr:municipality:thessaloniki' => 'Thessaloniki',
        'gr:municipality:patras' => 'Patras',
    ]],

    'Greenland' => [GreenlandGeographyProvider::class, [
        'gl:municipality:sermersooq' => 'Sermersooq',
    ]],

    'Grenada' => [GrenadaGeographyProvider::class, [
        'gd:dependency:carriacou' => 'Carriacou',
    ]],

    'Guadeloupe' => [GuadeloupeGeographyProvider::class, [
        'gp:commune:les-abymes' => 'Les Abymes',
        'gp:commune:baie-mahault' => 'Baie-Mahault',
        'gp:commune:basse-terre' => 'Basse-Terre',
    ]],

    'Guatemala' => [GuatemalaGeographyProvider::class, [
        'gt:municipality:coban' => 'Cobán',
        'gt:municipality:quetzaltenango' => 'Quetzaltenango',
        'gt:municipality:ciudad-de-guatemala' => 'Ciudad de Guatemala',
    ]],

    'Guernsey' => [GuernseyGeographyProvider::class, [
        'gg:dependency:alderney' => 'Alderney',
        'gg:dependency:sark' => 'Sark',
    ]],

    'GuineaBissau' => [GuineaBissauGeographyProvider::class, [
        'gw:sector:bambadinca' => 'Bambadinca',
        'gw:sector:gabu' => 'Gabú',
        'gw:sector:uno' => 'Uno',
    ]],

    'Guyana' => [GuyanaGeographyProvider::class, [
        'gy:town:georgetown' => 'Georgetown',
        'gy:town:linden' => 'Linden',
        'gy:neighbourhood_democratic_council:wakenaam' => 'Wakenaam',
    ]],

    'Haiti' => [HaitiGeographyProvider::class, [
        'ht:arrondissement:cap-haitien' => 'Cap-Haïtien',
        'ht:arrondissement:port-au-prince' => 'Port-au-Prince',
        'ht:arrondissement:la-gonave' => 'La Gonâve',
    ]],

    'Honduras' => [HondurasGeographyProvider::class, [
        'hn:municipality:distrito-central' => 'Distrito Central',
        'hn:municipality:san-pedro-sula' => 'San Pedro Sula',
        'hn:municipality:la-ceiba' => 'La Ceiba',
    ]],

    'HongKong' => [HongKongGeographyProvider::class, [
        'hk:district:central-and-western' => 'Central and Western',
    ]],

    'India' => [IndiaGeographyProvider::class, [
        'in:district:602' => 'South Andaman',
    ]],

    'Iraq' => [IraqGeographyProvider::class, [
        'iq:district:mosul' => 'Mosul',
        'iq:district:abu-ghraib' => 'Abu Ghraib',
        'iq:district:makhmur' => 'Makhmur',
    ]],

    'IsleOfMan' => [IsleOfManGeographyProvider::class, [
        'im:town:douglas' => 'Douglas',
        'im:parish:braddan' => 'Braddan',
        'im:village:port-erin' => 'Port Erin',
    ]],

    'Italy' => [ItalyGeographyProvider::class, [
        'it:metropolitan_city:rome' => 'Rome',
        'it:metropolitan_city:milan' => 'Milan',
        'it:metropolitan_city:naples' => 'Naples',
    ]],

    'IvoryCoast' => [IvoryCoastGeographyProvider::class, [
        'ci:region:belier' => 'Bélier',
        'ci:region:san-pedro' => 'San-Pédro',
        'ci:region:folon' => 'Folon',
    ]],

    'Jamaica' => [JamaicaGeographyProvider::class, [
        'jm:parish:clarendon' => 'Clarendon',
    ]],

    'Jersey' => [JerseyGeographyProvider::class, [
        'je:vingtaine:la-grande-vingtaine' => 'La Grande Vingtaine',
        'je:cueillette:la-grande-cueillette' => 'La Grande Cueillette',
        'je:canton:canton-de-bas-de-la-vingtaine-de-la-ville' => 'Canton de Bas de la Vingtaine de la Ville',
    ]],

    'Kazakhstan' => [KazakhstanGeographyProvider::class, [
        'kz:district:abai' => 'Abai',
        'kz:district:talgar' => 'Talgar',
        'kz:district:saryagash' => 'Saryagash',
    ]],

    'Kenya' => [KenyaGeographyProvider::class, [
        'ke:constituency:westlands' => 'Westlands',
        'ke:constituency:garissa-township' => 'Garissa Township',
        'ke:constituency:bomet-central' => 'Bomet Central',
    ]],

    'Kosovo' => [KosovoGeographyProvider::class, [
        'xk:municipality:pristina' => 'Pristina',
        'xk:municipality:prizren' => 'Prizren',
        'xk:municipality:mitrovica' => 'Mitrovica',
    ]],

    'Kyrgyzstan' => [KyrgyzstanGeographyProvider::class, [
        'kg:district:alamudun' => 'Alamüdün',
        'kg:district:birinchi-may' => 'Birinchi May',
        'kg:district:kara-suu' => 'Kara-Suu',
    ]],

    'Laos' => [LaosGeographyProvider::class, [
        'la:district:chanthabuly' => 'Chanthabuly',
        'la:district:sikhottabong' => 'Sikhottabong',
        'la:district:xaysetha' => 'Xaysetha',
    ]],

    'Lebanon' => [LebanonGeographyProvider::class, [
        'lb:caza:akkar' => 'Akkar',
        'lb:caza:byblos' => 'Byblos',
        'lb:caza:tripoli' => 'Tripoli',
    ]],

    'Lesotho' => [LesothoGeographyProvider::class, [
        'ls:constituency:taung' => 'Taung',
        'ls:constituency:mechachane' => 'Mechachane',
    ]],

    'Liberia' => [LiberiaGeographyProvider::class, [
        'lr:district:klay' => 'Klay',
        'lr:district:sanniquellie-mahn' => 'Sanniquellie Mahn',
        'lr:district:barclayville' => 'Barclayville',
    ]],

    'Liechtenstein' => [LiechtensteinGeographyProvider::class, [
        'li:commune:balzers' => 'Balzers',
    ]],

    'Luxembourg' => [LuxembourgGeographyProvider::class, [
        'lu:commune:luxembourg-city' => 'Luxembourg City',
        'lu:commune:esch-sur-alzette' => 'Esch-sur-Alzette',
        'lu:commune:differdange' => 'Differdange',
    ]],

    'Madagascar' => [MadagascarGeographyProvider::class, [
        'mg:region:analamanga' => 'Analamanga',
        'mg:region:diana' => 'Diana',
        'mg:region:ambatosoa' => 'Ambatosoa',
        'mg:region:matsiatra-ambony' => 'Matsiatra Ambony',
        'mg:district:antananarivo-avaradrano' => 'Antananarivo-Avaradrano',
        'mg:district:mananara-avaratra' => 'Mananara Avaratra',
    ]],

    'Maldives' => [MaldivesGeographyProvider::class, [
        'mv:island:thulusdhoo' => 'Thulusdhoo',
        'mv:island:hithadhoo' => 'Hithadhoo',
        'mv:island:fuvahmulah' => 'Fuvahmulah',
    ]],

    'Malta' => [MaltaGeographyProvider::class, [
        'mt:local_council:attard' => 'Attard',
    ]],

    'Martinique' => [MartiniqueGeographyProvider::class, [
        'mq:commune:schoelcher' => 'Schœlcher',
        'mq:commune:fort-de-france' => 'Fort-de-France',
        'mq:commune:sainte-anne' => 'Sainte-Anne',
    ]],

    'Mauritania' => [MauritaniaGeographyProvider::class, [
        'mr:department:adel-bagrou' => 'Adel Bagrou',
        'mr:department:aioun' => 'Aïoun',
        'mr:department:atar' => 'Atar',
    ]],

    'Mauritius' => [MauritiusGeographyProvider::class, [
        'mu:city:port-louis' => 'Port Louis',
        'mu:town:curepipe' => 'Curepipe',
        'mu:village:chamarel' => 'Chamarel',
        'mu:village:vingt-cinq' => 'Vingt-Cinq',
        'mu:village:lalmatie' => 'Lalmatie',
        'mu:village:belle-vue-haurel' => 'Belle Vue Haurel',
        'mu:village:l-escalier' => "L'Escalier",
    ]],

    'Mayotte' => [MayotteGeographyProvider::class, [
        'yt:commune:acoua' => 'Acoua',
    ]],

    'Mexico' => [MexicoGeographyProvider::class, [
        'mx:municipality:25019' => 'Eldorado',
        'mx:municipality:25020' => 'Juan José Ríos',
        'mx:municipality:24059' => 'Villa de Pozos',
        'mx:municipality:01012' => 'Villa Juárez',
        'mx:borough:09002' => 'Azcapotzalco',
        'mx:municipality:08008' => 'Batopilas de Manuel Gómez Morín',
    ]],

    'Mongolia' => [MongoliaGeographyProvider::class, [
        'mn:sum:kharkhorin' => 'Kharkhorin',
        'mn:sum:dalanzadgad' => 'Dalanzadgad',
        'mn:duureg:ulaanbaatar:bayangol' => 'Bayangol',
    ]],

    'Montenegro' => [MontenegroGeographyProvider::class, [
        'me:municipality:bar' => 'Bar',
    ]],

    'Montserrat' => [MontserratGeographyProvider::class, [
        // B1: Saint Patrick was a phantom parish (a destroyed
        // village, GeoNames PPLW); only three parishes exist.
        'ms:parish:saint-georges' => 'Saint Georges',
    ]],

    'Mozambique' => [MozambiqueGeographyProvider::class, [
        'mz:district:kampfumo' => 'KaMpfumo',
        'mz:district:doa' => 'Doa',
        'mz:district:guro' => 'Guro',
        'mz:district:ile' => 'Ile',
    ]],

    'Namibia' => [NamibiaGeographyProvider::class, [
        'na:constituency:daures' => 'Dâures',
        'na:constituency:tondoro' => 'Tondoro',
        'na:constituency:oshikunde' => 'Oshikunde',
    ]],

    'Nauru' => [NauruGeographyProvider::class, [
        'nr:district:aiwo' => 'Aiwo',
    ]],

    'Nepal' => [NepalGeographyProvider::class, [
        'np:district:kathmandu' => 'Kathmandu',
        'np:district:kaski' => 'Kaski',
        'np:district:jhapa' => 'Jhapa',
    ]],

    'Netherlands' => [NetherlandsGeographyProvider::class, [
        'nl:municipality:amsterdam' => 'Amsterdam',
        'nl:municipality:rotterdam' => 'Rotterdam',
        'nl:municipality:utrecht' => 'Utrecht',
    ]],

    'NewCaledonia' => [NewCaledoniaGeographyProvider::class, [
        'nc:commune:noumea' => 'Nouméa',
        'nc:commune:poya' => 'Poya',
        'nc:commune:lifou' => 'Lifou',
    ]],

    'Nicaragua' => [NicaraguaGeographyProvider::class, [
        'ni:municipality:managua' => 'Managua',
        'ni:municipality:granada' => 'Granada',
        'ni:municipality:bluefields' => 'Bluefields',
    ]],

    'Niger' => [NigerGeographyProvider::class, [
        'ne:department:arlit' => 'Arlit',
        'ne:department:dosso' => 'Dosso',
        'ne:commune:niamey-i' => 'Niamey I',
    ]],

    'Nigeria' => [NigeriaGeographyProvider::class, [
        'ng:lga:abia:aba-north' => 'Aba North',
        'ng:area_council:abuja-federal-capital-territory:abaji' => 'Abaji',
    ]],

    'Niue' => [NiueGeographyProvider::class, [
        'nu:village:alofi-north' => 'Alofi North',
    ]],

    'NorthMacedonia' => [NorthMacedoniaGeographyProvider::class, [
        'mk:municipality:aerodrom' => 'Aerodrom',
    ]],

    'Norway' => [NorwayGeographyProvider::class, [
        'no:municipality:oslo' => 'Oslo',
        'no:municipality:bergen' => 'Bergen',
        'no:municipality:trondheim' => 'Trondheim',
    ]],

    'Pakistan' => [PakistanGeographyProvider::class, [
        'pk:district:kacchi' => 'Kacchi',
        'pk:district:qila-abdullah' => 'Qila Abdullah',
        'pk:district:battagram' => 'Battagram',
        'pk:district:hattian-bala' => 'Hattian Bala',
        'pk:district:neelam-valley' => 'Neelam Valley',
        'pk:district:sudhanoti' => 'Sudhanoti',
    ]],

    'Palau' => [PalauGeographyProvider::class, [
        'pw:state:aimeliik' => 'Aimeliik',
    ]],

    'Palestine' => [PalestineGeographyProvider::class, [
        'ps:governorate:bethlehem' => 'Bethlehem',
    ]],

    'Panama' => [PanamaGeographyProvider::class, [
        'pa:district:panama' => 'Panamá',
        'pa:district:veraguas:santa-fe' => 'Santa Fe',
        'pa:district:david' => 'David',
    ]],

    'PapuaNewGuinea' => [PapuaNewGuineaGeographyProvider::class, [
        'pg:district:chuave' => 'Chuave',
        'pg:district:north-fly' => 'North Fly',
        'pg:district:port-moresby-south' => 'Port Moresby South',
    ]],

    'Paraguay' => [ParaguayGeographyProvider::class, [
        'py:district:ciudad-del-este' => 'Ciudad del Este',
        'py:district:asuncion' => 'Asunción',
    ]],

    'Peru' => [PeruGeographyProvider::class, [
        'pe:province:lima' => 'Lima',
        'pe:province:callao' => 'Callao',
        'pe:province:cusco' => 'Cusco',
    ]],

    'Poland' => [PolandGeographyProvider::class, [
        'pl:city_county:warszawa' => 'Warszawa',
        'pl:city_county:krakow' => 'Kraków',
        'pl:land_county:powiat-krakowski' => 'powiat krakowski',
    ]],

    'Portugal' => [PortugalGeographyProvider::class, [
        'pt:municipality:lisbon' => 'Lisboa',
        'pt:municipality:porto' => 'Porto',
        'pt:municipality:sintra' => 'Sintra',
    ]],

    'Reunion' => [ReunionGeographyProvider::class, [
        're:commune:cilaos' => 'Cilaos',
        're:commune:bras-panon' => 'Bras-Panon',
        're:commune:saint-denis' => 'Saint-Denis',
    ]],

    'Russia' => [RussiaGeographyProvider::class, [
        'ru:krai:altai-krai' => 'Altai Krai',
    ]],

    'Rwanda' => [RwandaGeographyProvider::class, [
        'rw:district:gasabo' => 'Gasabo',
        'rw:district:nyarugenge' => 'Nyarugenge',
        'rw:district:karongi' => 'Karongi',
    ]],

    'SaintLucia' => [SaintLuciaGeographyProvider::class, [
        'lc:district:castries' => 'Castries',
    ]],

    'SaintVincentAndTheGrenadines' => [SaintVincentAndTheGrenadinesGeographyProvider::class, [
        'vc:parish:charlotte' => 'Charlotte',
    ]],

    'SanMarino' => [SanMarinoGeographyProvider::class, [
        'sm:municipality:borgo-maggiore' => 'Borgo Maggiore',
    ]],

    'SaoTomeAndPrincipe' => [SaoTomeAndPrincipeGeographyProvider::class, [
        'st:autonomous_region:principe' => 'Príncipe',
    ]],

    'SaudiArabia' => [SaudiArabiaGeographyProvider::class, [
        'sa:governorate:yanbu' => 'Yanbu',
        'sa:governorate:taif' => 'Taif',
        'sa:governorate:umluj' => 'Umluj',
    ]],

    'Senegal' => [SenegalGeographyProvider::class, [
        'sn:department:dakar' => 'Dakar',
        'sn:department:keur-massar' => 'Keur Massar',
        'sn:department:dagana' => 'Dagana',
    ]],

    'Serbia' => [SerbiaGeographyProvider::class, [
        'rs:city:novi-sad' => 'Novi Sad',
        'rs:city_municipality:zemun' => 'Zemun',
        'rs:city_municipality:novi-beograd' => 'Novi Beograd',
    ]],

    'Seychelles' => [SeychellesGeographyProvider::class, [
        'sc:district:anse-boileau' => 'Anse Boileau',
    ]],

    'SierraLeone' => [SierraLeoneGeographyProvider::class, [
        'sl:district:bo' => 'Bo',
        'sl:district:western-urban' => 'Western Urban',
        'sl:district:kono' => 'Kono',
    ]],

    'Slovakia' => [SlovakiaGeographyProvider::class, [
        'sk:district:bratislava-i' => 'Bratislava I',
        'sk:district:kosice-iii' => 'Košice III',
        'sk:district:bardejov' => 'Bardejov',
    ]],

    'Somalia' => [SomaliaGeographyProvider::class, [
        'so:district:borama' => 'Borama',
        'so:district:berbera' => 'Berbera',
        'so:district:kismayo' => 'Kismayo',
    ]],

    'SouthAfrica' => [SouthAfricaGeographyProvider::class, [
        'za:district_municipality:sarah-baartman' => 'Sarah Baartman',
        'za:city_municipality:city-of-ekurhuleni' => 'City of Ekurhuleni',
        'za:district_municipality:west-coast' => 'West Coast',
    ]],

    'SouthKorea' => [SouthKoreaGeographyProvider::class, [
        'kr:city:suwon' => 'Suwon',
        'kr:county:gijang' => 'Gijang',
        'kr:district:gangnam' => 'Gangnam',
    ]],

    'SouthSudan' => [SouthSudanGeographyProvider::class, [
        'ss:county:wau' => 'Wau',
        'ss:county:jur-river' => 'Jur River',
        'ss:county:pibor' => 'Pibor',
    ]],

    'Sudan' => [SudanGeographyProvider::class, [
        'sd:district:al-kamlin' => 'Al Kamlin',
        'sd:district:abyei' => 'Abyei',
        'sd:district:north-kordofan:ar-rahad' => 'Ar Rahad',
    ]],

    'Suriname' => [SurinameGeographyProvider::class, [
        'sr:resort:kwakoegron' => 'Kwakoegron',
        'sr:resort:marshallkreek' => 'Marshallkreek',
        'sr:resort:centrum' => 'Centrum',
    ]],

    'Sweden' => [SwedenGeographyProvider::class, [
        'se:municipality:stockholm' => 'Stockholm',
        'se:municipality:gothenburg' => 'Göteborg',
        'se:municipality:malmo' => 'Malmö',
    ]],

    'Switzerland' => [SwitzerlandGeographyProvider::class, [
        'ch:district:zurich' => 'Zürich',
        'ch:district:luzern' => 'Luzern',
        'ch:district:bern-mittelland' => 'Bern-Mittelland',
    ]],

    'Syria' => [SyriaGeographyProvider::class, [
        'sy:district:homs' => 'Homs',
        'sy:district:damascus' => 'Damascus',
        'sy:district:al-shaddadah' => 'Al-Shaddadah',
    ]],

    'Tajikistan' => [TajikistanGeographyProvider::class, [
        'tj:district:bobojon-ghafurov' => 'Bobojon Ghafurov',
        'tj:city:khujand' => 'Khujand',
        'tj:district:ibn-sina' => 'Ibn Sina',
    ]],

    'Tanzania' => [TanzaniaGeographyProvider::class, [
        'tz:district:madaba' => 'Madaba',
        'tz:district:bumbuli' => 'Bumbuli',
        'tz:district:nanyamba-town' => 'Nanyamba Town',
        'tz:district:zanzibar-city' => 'Zanzibar City',
    ]],

    'TimorLeste' => [TimorLesteGeographyProvider::class, [
        'tl:administrative_post:cristo-rei' => 'Cristo Rei',
        'tl:administrative_post:pante-macassar' => 'Pante Macassar',
        'tl:administrative_post:quelicai-antiga' => 'Quelicai Antiga',
    ]],

    'Togo' => [TogoGeographyProvider::class, [
        'tg:prefecture:blitta' => 'Blitta',
        'tg:prefecture:golfe' => 'Golfe',
        'tg:prefecture:assoli' => 'Assoli',
    ]],

    'Tunisia' => [TunisiaGeographyProvider::class, [
        'tn:delegation:ariana-ville' => 'Ariana Ville',
        'tn:delegation:beja-nord' => 'Béja Nord',
        'tn:delegation:hammamet' => 'Hammamet',
    ]],

    'Turkmenistan' => [TurkmenistanGeographyProvider::class, [
        'tm:district:bagtyyarlyk' => 'Bagtyýarlyk',
        'tm:district:tejen' => 'Tejen',
        'tm:district:kerki' => 'Kerki',
    ]],

    'TurksAndCaicos' => [TurksAndCaicosGeographyProvider::class, [
        'tc:district:grand-turk' => 'Grand Turk',
    ]],

    'USMinorOutlyingIslands' => [USMinorOutlyingIslandsGeographyProvider::class, [
        'um:island:wake-island' => 'Wake Island',
    ]],

    'USVirginIslands' => [USVirginIslandsGeographyProvider::class, [
        'vi:subdistrict:charlotte-amalie' => 'Charlotte Amalie',
        'vi:subdistrict:saint-thomas:east-end' => 'East End',
        'vi:subdistrict:cruz-bay' => 'Cruz Bay',
    ]],

    'Uganda' => [UgandaGeographyProvider::class, [
        'ug:city:kampala' => 'Kampala',
        'ug:city:arua' => 'Arua',
        'ug:district:madi-okollo' => 'Madi-Okollo',
        'ug:district:rwampara' => 'Rwampara',
    ]],

    'Ukraine' => [UkraineGeographyProvider::class, [
        'ua:raion:kharkiv' => 'Kharkiv',
        'ua:raion:lviv' => 'Lviv',
        'ua:raion:odesa' => 'Odesa',
    ]],

    'UnitedArabEmirates' => [UnitedArabEmiratesGeographyProvider::class, [
        'ae:emirate:abu-dhabi' => 'Abu Dhabi',
    ]],

    'UnitedKingdom' => [UnitedKingdomGeographyProvider::class, [
        'gb:county:greater-london' => 'Greater London',
        'gb:council_area:glasgow-city' => 'Glasgow City',
        'gb:district:antrim-and-newtownabbey' => 'Antrim and Newtownabbey',
    ]],

    'Uruguay' => [UruguayGeographyProvider::class, [
        'uy:municipality:municipality-a' => 'Municipio A',
        'uy:municipality:maldonado' => 'Maldonado',
        'uy:municipality:ciudad-de-la-costa' => 'Ciudad de la Costa',
    ]],

    'Venezuela' => [VenezuelaGeographyProvider::class, [
        've:municipality:chacao' => 'Chacao',
        've:municipality:baruta' => 'Baruta',
        've:municipality:libertador-bolivarian' => 'Libertador',
    ]],

    'Yemen' => [YemenGeographyProvider::class, [
        'ye:district:khamir' => 'Khamir',
        'ye:district:crater' => 'Crater',
        'ye:district:az-zahir' => 'Az Zahir',
    ]],

    'Zambia' => [ZambiaGeographyProvider::class, [
        'zm:district:kabwe' => 'Kabwe',
        'zm:district:ngabwe' => 'Ngabwe',
        'zm:district:shibuyunji' => 'Shibuyunji',
    ]],

    'Zimbabwe' => [ZimbabweGeographyProvider::class, [
        'zw:district:harare' => 'Harare',
        'zw:district:bulawayo' => 'Bulawayo',
        'zw:district:mutare' => 'Mutare',
    ]],
]);

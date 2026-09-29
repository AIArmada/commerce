<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Aland\AlandAddressFormatter;
use AIArmada\Addressing\Geography\AmericanSamoa\AmericanSamoaAddressFormatter;
use AIArmada\Addressing\Geography\Angola\AngolaAddressFormatter;
use AIArmada\Addressing\Geography\AntiguaAndBarbuda\AntiguaAndBarbudaAddressFormatter;
use AIArmada\Addressing\Geography\Aruba\ArubaAddressFormatter;
use AIArmada\Addressing\Geography\Austria\AustriaAddressFormatter;
use AIArmada\Addressing\Geography\Bahamas\BahamasAddressFormatter;
use AIArmada\Addressing\Geography\Barbados\BarbadosAddressFormatter;
use AIArmada\Addressing\Geography\Belgium\BelgiumAddressFormatter;
use AIArmada\Addressing\Geography\Benin\BeninAddressFormatter;
use AIArmada\Addressing\Geography\Bhutan\BhutanAddressFormatter;
use AIArmada\Addressing\Geography\BosniaAndHerzegovina\BosniaAndHerzegovinaAddressFormatter;
use AIArmada\Addressing\Geography\Brunei\BruneiAddressFormatter;
use AIArmada\Addressing\Geography\BurkinaFaso\BurkinaFasoAddressFormatter;
use AIArmada\Addressing\Geography\Cambodia\CambodiaAddressFormatter;
use AIArmada\Addressing\Geography\CapeVerde\CapeVerdeAddressFormatter;
use AIArmada\Addressing\Geography\CaymanIslands\CaymanIslandsAddressFormatter;
use AIArmada\Addressing\Geography\Chad\ChadAddressFormatter;
use AIArmada\Addressing\Geography\China\ChinaAddressFormatter;
use AIArmada\Addressing\Geography\Congo\CongoAddressFormatter;
use AIArmada\Addressing\Geography\Croatia\CroatiaAddressFormatter;
use AIArmada\Addressing\Geography\Cyprus\CyprusAddressFormatter;
use AIArmada\Addressing\Geography\DemocraticRepublicOfCongo\DemocraticRepublicOfCongoAddressFormatter;
use AIArmada\Addressing\Geography\Djibouti\DjiboutiAddressFormatter;
use AIArmada\Addressing\Geography\DominicanRepublic\DominicanRepublicAddressFormatter;
use AIArmada\Addressing\Geography\ElSalvador\ElSalvadorAddressFormatter;
use AIArmada\Addressing\Geography\Eritrea\EritreaAddressFormatter;
use AIArmada\Addressing\Geography\Eswatini\EswatiniAddressFormatter;
use AIArmada\Addressing\Geography\FaroeIslands\FaroeIslandsAddressFormatter;
use AIArmada\Addressing\Geography\Finland\FinlandAddressFormatter;
use AIArmada\Addressing\Geography\FrenchPolynesia\FrenchPolynesiaAddressFormatter;
use AIArmada\Addressing\Geography\Gabon\GabonAddressFormatter;
use AIArmada\Addressing\Geography\Georgia\GeorgiaAddressFormatter;
use AIArmada\Addressing\Geography\Greece\GreeceAddressFormatter;
use AIArmada\Addressing\Geography\Grenada\GrenadaAddressFormatter;
use AIArmada\Addressing\Geography\Guam\GuamAddressFormatter;
use AIArmada\Addressing\Geography\Guernsey\GuernseyAddressFormatter;
use AIArmada\Addressing\Geography\Guinea\GuineaAddressFormatter;
use AIArmada\Addressing\Geography\Haiti\HaitiAddressFormatter;
use AIArmada\Addressing\Geography\HongKong\HongKongAddressFormatter;
use AIArmada\Addressing\Geography\Iceland\IcelandAddressFormatter;
use AIArmada\Addressing\Geography\Iran\IranAddressFormatter;
use AIArmada\Addressing\Geography\IsleOfMan\IsleOfManAddressFormatter;
use AIArmada\Addressing\Geography\IvoryCoast\IvoryCoastAddressFormatter;
use AIArmada\Addressing\Geography\Japan\JapanAddressFormatter;
use AIArmada\Addressing\Geography\Kazakhstan\KazakhstanAddressFormatter;
use AIArmada\Addressing\Geography\Kiribati\KiribatiAddressFormatter;
use AIArmada\Addressing\Geography\Kyrgyzstan\KyrgyzstanAddressFormatter;
use AIArmada\Addressing\Geography\Latvia\LatviaAddressFormatter;
use AIArmada\Addressing\Geography\Lesotho\LesothoAddressFormatter;
use AIArmada\Addressing\Geography\Libya\LibyaAddressFormatter;
use AIArmada\Addressing\Geography\Lithuania\LithuaniaAddressFormatter;
use AIArmada\Addressing\Geography\Madagascar\MadagascarAddressFormatter;
use AIArmada\Addressing\Geography\Maldives\MaldivesAddressFormatter;
use AIArmada\Addressing\Geography\Malta\MaltaAddressFormatter;
use AIArmada\Addressing\Geography\Martinique\MartiniqueAddressFormatter;
use AIArmada\Addressing\Geography\Mauritius\MauritiusAddressFormatter;
use AIArmada\Addressing\Geography\Micronesia\MicronesiaAddressFormatter;
use AIArmada\Addressing\Geography\Monaco\MonacoAddressFormatter;
use AIArmada\Addressing\Geography\Montenegro\MontenegroAddressFormatter;
use AIArmada\Addressing\Geography\Morocco\MoroccoAddressFormatter;
use AIArmada\Addressing\Geography\Namibia\NamibiaAddressFormatter;
use AIArmada\Addressing\Geography\Nepal\NepalAddressFormatter;
use AIArmada\Addressing\Geography\NewZealand\NewZealandAddressFormatter;
use AIArmada\Addressing\Geography\Niger\NigerAddressFormatter;
use AIArmada\Addressing\Geography\Niue\NiueAddressFormatter;
use AIArmada\Addressing\Geography\NorthMacedonia\NorthMacedoniaAddressFormatter;
use AIArmada\Addressing\Geography\Pakistan\PakistanAddressFormatter;
use AIArmada\Addressing\Geography\Palestine\PalestineAddressFormatter;
use AIArmada\Addressing\Geography\PapuaNewGuinea\PapuaNewGuineaAddressFormatter;
use AIArmada\Addressing\Geography\Philippines\PhilippinesAddressFormatter;
use AIArmada\Addressing\Geography\PuertoRico\PuertoRicoAddressFormatter;
use AIArmada\Addressing\Geography\Reunion\ReunionAddressFormatter;
use AIArmada\Addressing\Geography\Rwanda\RwandaAddressFormatter;
use AIArmada\Addressing\Geography\SaintHelena\SaintHelenaAddressFormatter;
use AIArmada\Addressing\Geography\SaintLucia\SaintLuciaAddressFormatter;
use AIArmada\Addressing\Geography\SaintPierreAndMiquelon\SaintPierreAndMiquelonAddressFormatter;
use AIArmada\Addressing\Geography\Samoa\SamoaAddressFormatter;
use AIArmada\Addressing\Geography\SaoTomeAndPrincipe\SaoTomeAndPrincipeAddressFormatter;
use AIArmada\Addressing\Geography\Senegal\SenegalAddressFormatter;
use AIArmada\Addressing\Geography\Seychelles\SeychellesAddressFormatter;
use AIArmada\Addressing\Geography\Slovakia\SlovakiaAddressFormatter;
use AIArmada\Addressing\Geography\SolomonIslands\SolomonIslandsAddressFormatter;
use AIArmada\Addressing\Geography\SouthKorea\SouthKoreaAddressFormatter;
use AIArmada\Addressing\Geography\Spain\SpainAddressFormatter;
use AIArmada\Addressing\Geography\Suriname\SurinameAddressFormatter;
use AIArmada\Addressing\Geography\Switzerland\SwitzerlandAddressFormatter;
use AIArmada\Addressing\Geography\Taiwan\TaiwanAddressFormatter;
use AIArmada\Addressing\Geography\Thailand\ThailandAddressFormatter;
use AIArmada\Addressing\Geography\Togo\TogoAddressFormatter;
use AIArmada\Addressing\Geography\TrinidadAndTobago\TrinidadAndTobagoAddressFormatter;
use AIArmada\Addressing\Geography\Turkiye\TurkiyeAddressFormatter;
use AIArmada\Addressing\Geography\TurksAndCaicos\TurksAndCaicosAddressFormatter;
use AIArmada\Addressing\Geography\Ukraine\UkraineAddressFormatter;
use AIArmada\Addressing\Geography\UnitedArabEmirates\UnitedArabEmiratesAddressFormatter;
use AIArmada\Addressing\Geography\Uruguay\UruguayAddressFormatter;
use AIArmada\Addressing\Geography\USMinorOutlyingIslands\USMinorOutlyingIslandsAddressFormatter;
use AIArmada\Addressing\Geography\Venezuela\VenezuelaAddressFormatter;
use AIArmada\Addressing\Geography\WallisAndFutuna\WallisAndFutunaAddressFormatter;
use AIArmada\Addressing\Geography\Zambia\ZambiaAddressFormatter;

/**
 * Every country's address formatter, one dataset row each (shard 2 of 4).
 *
 * Formatter output is a user-visible string, so it stays pinned per country.
 * A row is the whole assertion: the input address and the exact string the
 * formatter must produce. Supporting a new country means adding a row here,
 * not creating a file.
 *
 * The rows are split across 4 files because Pest parallelises by file:
 * one file holding every case would run serially in a single worker.
 */
it('formats an address for every country', function (string $formatter, array $input, string $expected): void {
    expect(app($formatter)->format(AddressData::from($input)))->toBe($expected);
})->with([
    // Aland
    [AlandAddressFormatter::class, ['line1' => 'Stadshusparken',
        'city' => 'MARIEHAMN',
        'postcode' => '22100',
        'country_code' => 'AX', ], "Stadshusparken\n22100 MARIEHAMN\nAland Islands"],

    // AmericanSamoa
    [AmericanSamoaAddressFormatter::class, ['line1' => 'PO BOX 1234',
        'city' => 'PAGO PAGO',
        'postcode' => '96799',
        'country_code' => 'AS', ], "PO BOX 1234\nPAGO PAGO AS 96799\nAmerican Samoa"],

    // Angola
    [AngolaAddressFormatter::class, ['line1' => 'Rua Ndunduma 51',
        'city' => 'LUANDA',
        'country_code' => 'AO', ], "Rua Ndunduma 51\nLUANDA\nAngola"],

    // AntiguaAndBarbuda
    [AntiguaAndBarbudaAddressFormatter::class, ['line1' => 'P.O. Box 123',
        'city' => "ST. JOHN'S",
        'postcode' => '99999',
        'country_code' => 'AG', ], "P.O. Box 123\nST. JOHN'S\n99999\nAntigua and Barbuda"],

    // Aruba
    [ArubaAddressFormatter::class, ['line1' => 'Sun Plaza Suite 110',
        'line2' => 'L.G. Smith Boulevard #160',
        'city' => 'ORANJESTAD',
        'country_code' => 'AW', ], "Sun Plaza Suite 110\nL.G. Smith Boulevard #160\nORANJESTAD\nAruba"],

    // Austria
    [AustriaAddressFormatter::class, ['line1' => 'Dorfstrasse 7',
        'city' => 'Hallstatt',
        'state' => 'Upper Austria',
        'postcode' => '4830',
        'country_code' => 'AT', ], "Dorfstrasse 7\n4830 Hallstatt\nUpper Austria\nAustria"],

    // Bahamas
    [BahamasAddressFormatter::class, ['line1' => 'P.O. Box N-8302',
        'city' => 'Nassau',
        'postcode' => '99999',
        'country_code' => 'BS', ], "P.O. Box N-8302\nNassau\n99999\nThe Bahamas"],

    // Barbados
    [BarbadosAddressFormatter::class, ['line1' => 'General Post Office',
        'line2' => 'Cheapside',
        'city' => 'Bridgetown',
        'state' => 'St. Michael',
        'postcode' => 'BB11000',
        'country_code' => 'BB', ], "General Post Office\nCheapside\nBridgetown\nSt. Michael BB11000\nBarbados"],

    // Belgium
    [BelgiumAddressFormatter::class, ['line1' => 'Rue de la Loi 16',
        'city' => 'Bruxelles',
        'postcode' => '1000',
        'country_code' => 'BE', ], "Rue de la Loi 16\n1000 Bruxelles\nBelgium"],

    // Benin
    [BeninAddressFormatter::class, ['line1' => '10 BP 648',
        'city' => 'COTONOU',
        'postcode' => '99999',
        'country_code' => 'BJ', ], "10 BP 648\nCOTONOU\n99999\nBenin"],

    // Bhutan
    [BhutanAddressFormatter::class, ['line1' => 'Chang Lam, JD House',
        'city' => 'Thimphu',
        'state' => 'Thimphu',
        'country_code' => 'BT', ], "Chang Lam, JD House\nThimphu\nBhutan"],

    // BosniaAndHerzegovina
    [BosniaAndHerzegovinaAddressFormatter::class, ['line1' => 'Sapna BB',
        'city' => 'SAPNA',
        'postcode' => '75411',
        'country_code' => 'BA', ], "Sapna BB\n75411 SAPNA\nBosnia and Herzegovina"],

    // Brunei
    [BruneiAddressFormatter::class, ['line1' => 'No. 7 Simpang 170, Jalan Muara',
        'city' => 'Muara',
        'postcode' => 'BT2328',
        'country_code' => 'BN',
        'components' => ['kampung' => 'Kampong Kapok'], ], "No. 7 Simpang 170, Jalan Muara\nKampong Kapok\nMuara BT2328\nBrunei Darussalam"],

    // BurkinaFaso
    [BurkinaFasoAddressFormatter::class, ['line1' => 'Secteur 3',
        'city' => 'TENKODOGO',
        'state' => 'Centre-Est',
        'postcode' => '70000',
        'country_code' => 'BF', ], "Secteur 3\n70000 TENKODOGO\nCentre-Est\nBurkina Faso"],

    // Cambodia
    [CambodiaAddressFormatter::class, ['line1' => '100E0 Street 118',
        'city' => 'Krong Siem Reap',
        'state' => 'Siem Reap',
        'postcode' => '17000',
        'country_code' => 'KH', ], "100E0 Street 118\nKrong Siem Reap\nSiem Reap 17000\nCambodia"],

    // CapeVerde
    [CapeVerdeAddressFormatter::class, ['line1' => 'Rua 5 de Julho 138/Platô',
        'city' => 'PRAIA',
        'postcode' => '7600-120',
        'country_code' => 'CV', ], "Rua 5 de Julho 138/Platô\n7600-120 PRAIA\nCape Verde"],

    // CaymanIslands
    [CaymanIslandsAddressFormatter::class, ['line1' => 'P.O. Box 12',
        'state' => 'Cayman Brac',
        'postcode' => 'KY2-2100',
        'country_code' => 'KY', ], "P.O. Box 12\nCayman Brac  KY2-2100\nCayman Islands"],

    // Chad
    [ChadAddressFormatter::class, ['line1' => 'Avenue des Martyrs',
        'city' => 'Moundou',
        'state' => 'Logone Occidental',
        'country_code' => 'TD', ], "Avenue des Martyrs\nMoundou\nLogone Occidental\nChad"],

    // China
    [ChinaAddressFormatter::class, ['line1' => 'No.12 Zhichun Road',
        'city' => 'Haidian District',
        'state' => 'BEIJING',
        'postcode' => '100191',
        'country_code' => 'CN', ], "No.12 Zhichun Road\nHaidian District\n100191 BEIJING\nChina"],

    // Congo
    [CongoAddressFormatter::class, ['line1' => '12, rue Kakamoueka',
        'city' => 'BRAZZAVILLE',
        'country_code' => 'CG', ], "12, rue Kakamoueka\nBRAZZAVILLE\nCongo"],

    // Croatia
    [CroatiaAddressFormatter::class, ['line1' => 'Krapinska 17/I stan 4',
        'city' => 'ZAGREB',
        'postcode' => 'HR-10000',
        'country_code' => 'HR', ], "Krapinska 17/I stan 4\nHR-10000 ZAGREB\nCroatia"],

    // Cyprus
    [CyprusAddressFormatter::class, ['line1' => 'Sofochleous 26',
        'city' => 'Strovolos',
        'postcode' => 'CY-2008',
        'country_code' => 'CY', ], "Sofochleous 26\nCY-2008 Strovolos\nCyprus"],

    // DemocraticRepublicOfCongo
    [DemocraticRepublicOfCongoAddressFormatter::class, ['line1' => 'Avenue de la Poste N°1',
        'city' => 'LIMETE',
        'state' => 'KINSHASA',
        'postcode' => '1004131',
        'country_code' => 'CD', ], "Avenue de la Poste N°1\nLIMETE\n1004131 KINSHASA\nDemocratic Republic of the Congo"],

    // Djibouti
    [DjiboutiAddressFormatter::class, ['line1' => 'BP 12',
        'city' => 'Arta',
        'state' => 'Arta',
        'postcode' => '77201',
        'country_code' => 'DJ', ], "BP 12\n77201 Arta\nDjibouti"],

    // DominicanRepublic
    [DominicanRepublicAddressFormatter::class, ['line1' => 'Calle del Sol 12',
        'city' => 'SANTIAGO',
        'postcode' => '51000',
        'country_code' => 'DO', ], "Calle del Sol 12\n51000 SANTIAGO\nDominican Republic"],

    // ElSalvador
    [ElSalvadorAddressFormatter::class, ['line1' => '6a AVENIDA NORTE 165',
        'city' => 'SAN SALVADOR',
        'postcode' => '2201',
        'country_code' => 'SV', ], "6a AVENIDA NORTE 165\n2201 SAN SALVADOR\nEl Salvador"],

    // Eritrea
    [EritreaAddressFormatter::class, ['line1' => 'Awet Street 4',
        'city' => 'ASMARA',
        'country_code' => 'ER', ], "Awet Street 4\nASMARA\nEritrea"],

    // Eswatini
    [EswatiniAddressFormatter::class, ['line1' => 'P.O. Box 125',
        'city' => 'MBABANE',
        'postcode' => 'H100',
        'country_code' => 'SZ', ], "P.O. Box 125\nMBABANE\nH100\nEswatini"],

    // FaroeIslands
    [FaroeIslandsAddressFormatter::class, ['line1' => 'Bøgøta 5',
        'city' => 'Klaksvík',
        'postcode' => 'FO-700',
        'country_code' => 'FO', ], "Bøgøta 5\nFO-700 Klaksvík\nFaroe Islands"],

    // Finland
    [FinlandAddressFormatter::class, ['line1' => 'PL 900',
        'city' => 'HELSINKI',
        'postcode' => '00101',
        'country_code' => 'FI', ], "PL 900\n00101 HELSINKI\nFinland"],

    // FrenchPolynesia
    [FrenchPolynesiaAddressFormatter::class, ['line1' => 'BP 123',
        'city' => 'PAPEETE',
        'postcode' => '98714',
        'country_code' => 'PF', ], "BP 123\n98714 PAPEETE\nFrench Polynesia"],

    // Gabon
    [GabonAddressFormatter::class, ['line1' => 'BP 13210',
        'city' => 'LIBREVILLE',
        'postcode' => '01',
        'country_code' => 'GA', ], "BP 13210\n01 LIBREVILLE\nGabon"],

    // Georgia
    [GeorgiaAddressFormatter::class, ['line1' => 'Pekinia ave. #39, flat 66',
        'city' => 'TBILISI',
        'postcode' => '0160',
        'country_code' => 'GE', ], "Pekinia ave. #39, flat 66\n0160 TBILISI\nGeorgia"],

    // Greece
    [GreeceAddressFormatter::class, ['line1' => '1, D. GOUNARI STREET',
        'city' => 'MAROUSI',
        'postcode' => '151 24',
        'country_code' => 'GR', ], "1, D. GOUNARI STREET\n151 24 MAROUSI\nGreece"],

    // Grenada
    [GrenadaAddressFormatter::class, ['line1' => 'Woburn',
        'city' => "ST. GEORGE'S",
        'country_code' => 'GD', ], "Woburn\nST. GEORGE'S\nGrenada"],

    // Guam
    [GuamAddressFormatter::class, ['line1' => '489 ARMY DR',
        'city' => 'BARRIGADA',
        'postcode' => '96913-9998',
        'country_code' => 'GU', ], "489 ARMY DR\nBARRIGADA GU 96913-9998\nGuam"],

    // Guernsey
    [GuernseyAddressFormatter::class, ['line1' => 'Anybank House',
        'line2' => 'Le Pollet',
        'line3' => 'St Peter Port',
        'city' => 'GUERNSEY',
        'postcode' => 'GY1 1AA',
        'country_code' => 'GG', ], "Anybank House\nLe Pollet\nSt Peter Port\nGUERNSEY\nGY1 1AA\nGuernsey"],

    // Guinea
    [GuineaAddressFormatter::class, ['line1' => 'BP 457',
        'city' => 'CONAKRY',
        'postcode' => '001',
        'country_code' => 'GN', ], "BP 457\n001 CONAKRY\nGuinea"],

    // Haiti
    [HaitiAddressFormatter::class, ['line1' => 'Rue Samba 1',
        'city' => 'DELMAS',
        'postcode' => 'HT6120',
        'country_code' => 'HT', ], "Rue Samba 1\nHT6120 DELMAS\nHaiti"],

    // HongKong
    [HongKongAddressFormatter::class, ['line1' => 'Flat 25, 12/F',
        'line2' => 'Acacia Building',
        'line3' => '150 Kennedy Road',
        'city' => 'WAN CHAI',
        'country_code' => 'HK', ], "Flat 25, 12/F\nAcacia Building\n150 Kennedy Road\nWAN CHAI\nHong Kong"],

    // Iceland
    [IcelandAddressFormatter::class, ['line1' => 'Tryggvagötu 5',
        'city' => 'HAFNARFIRÐI',
        'postcode' => '220',
        'country_code' => 'IS', ], "Tryggvagötu 5\n220 HAFNARFIRÐI\nIceland"],

    // Iran
    [IranAddressFormatter::class, ['line1' => 'West 196 street',
        'city' => 'Tehranpars',
        'state' => 'Tehran Province',
        'country_code' => 'IR', ], "West 196 street\nTehranpars\nTehran Province\nIran"],

    // IsleOfMan
    [IsleOfManAddressFormatter::class, ['line1' => 'P.O. Box 177',
        'city' => 'DOUGLAS',
        'postcode' => 'IM99 1PS',
        'country_code' => 'IM', ], "P.O. Box 177\nDOUGLAS\nIM99 1PS\nIsle of Man"],

    // IvoryCoast
    [IvoryCoastAddressFormatter::class, ['line1' => '06 B.P. 37',
        'city' => 'ABIDJAN',
        'postcode' => '99999',
        'country_code' => 'CI', ], "06 B.P. 37\nABIDJAN\n99999\nIvory Coast"],

    // Japan
    [JapanAddressFormatter::class, ['line1' => '4-3-2, Hakusan',
        'city' => 'Bunkyo-ku',
        'state' => 'TOKYO',
        'country_code' => 'JP', ], "4-3-2, Hakusan\nBunkyo-ku, TOKYO\nJapan"],

    // Kazakhstan
    [KazakhstanAddressFormatter::class, ['line1' => 'ul. Ryskulbekov, dom16, kv 224',
        'city' => 'ASTANA',
        'postcode' => 'Z00Y5M7',
        'country_code' => 'KZ', ], "ul. Ryskulbekov, dom16, kv 224\nZ00Y5M7, ASTANA\nKazakhstan"],

    // Kiribati
    [KiribatiAddressFormatter::class, ['line1' => 'PO Box 3',
        'city' => 'Kiritimati',
        'postcode' => 'KI0303',
        'country_code' => 'KI', ], "PO Box 3\nKiritimati KI0303\nKiribati"],

    // Kyrgyzstan
    [KyrgyzstanAddressFormatter::class, ['line1' => '193, Avenue Chuy, apt. 28',
        'city' => 'BISHKEK',
        'postcode' => '720001',
        'country_code' => 'KG', ], "193, Avenue Chuy, apt. 28\n720001 BISHKEK\nKyrgyzstan"],

    // Latvia
    [LatviaAddressFormatter::class, ['line1' => 'Kr. Barona street 7, dz. 1',
        'city' => 'RIGA',
        'postcode' => 'LV-1050',
        'country_code' => 'LV', ], "Kr. Barona street 7, dz. 1\nRIGA, LV-1050\nLatvia"],

    // Lesotho
    [LesothoAddressFormatter::class, ['line1' => 'P.O. Box 500',
        'city' => 'MASERU',
        'postcode' => '100',
        'country_code' => 'LS', ], "P.O. Box 500\nMASERU 100\nLesotho"],

    // Libya
    [LibyaAddressFormatter::class, ['line1' => 'Av. Al Ghazaly 12',
        'city' => 'TRIPOLI',
        'country_code' => 'LY', ], "Av. Al Ghazaly 12\nTRIPOLI\nLibya"],

    // Lithuania
    [LithuaniaAddressFormatter::class, ['line1' => 'Laisvės pr. 40-12',
        'city' => 'Vilnius',
        'postcode' => 'LT-04340',
        'country_code' => 'LT', ], "Laisvės pr. 40-12\nLT-04340 Vilnius\nLithuania"],

    // Madagascar
    [MadagascarAddressFormatter::class, ['line1' => 'Lot II M 85 D Antsahameva',
        'city' => 'TOAMASINA',
        'postcode' => '501',
        'country_code' => 'MG', ], "Lot II M 85 D Antsahameva\n501 TOAMASINA\nMadagascar"],

    // Maldives
    [MaldivesAddressFormatter::class, ['line1' => 'Nirolhu Magu 8',
        'city' => 'Hulhumale',
        'state' => 'Kaafu',
        'postcode' => '23000',
        'country_code' => 'MV', ], "Nirolhu Magu 8\nHulhumale 23000\nKaafu\nMaldives"],

    // Malta
    [MaltaAddressFormatter::class, ['line1' => 'Palace Square 1',
        'city' => 'VALLETTA',
        'postcode' => 'VLT 1117',
        'country_code' => 'MT', ], "Palace Square 1\nVALLETTA\nVLT 1117\nMalta"],

    // Martinique
    [MartiniqueAddressFormatter::class, ['line1' => 'Rue de la Liberté 9',
        'city' => 'FORT-DE-FRANCE',
        'postcode' => '97200',
        'country_code' => 'MQ', ], "Rue de la Liberté 9\n97200 FORT-DE-FRANCE\nMartinique"],

    // Mauritius
    [MauritiusAddressFormatter::class, ['line1' => 'Rue de la Solidarité',
        'city' => 'Port Mathurin',
        'state' => 'Rodrigues Island',
        'postcode' => 'R5135',
        'country_code' => 'MU', ], "Rue de la Solidarité\nPort Mathurin R5135\nRodrigues Island\nMauritius"],

    // Micronesia
    [MicronesiaAddressFormatter::class, ['line1' => 'PO Box 123',
        'city' => 'Pohnpei',
        'postcode' => '96941',
        'country_code' => 'FM', ], "PO Box 123\nPohnpei FM 96941\nMicronesia"],

    // Monaco
    [MonacoAddressFormatter::class, ['line1' => '1 AVENUE DE L HERMITAGE',
        'city' => 'MONACO',
        'postcode' => '98000',
        'country_code' => 'MC', ], "1 AVENUE DE L HERMITAGE\n98000 MONACO\nMonaco"],

    // Montenegro
    [MontenegroAddressFormatter::class, ['line1' => 'Ul. Slobode br. 1',
        'city' => 'PODGORICA',
        'postcode' => '81000',
        'country_code' => 'ME', ], "Ul. Slobode br. 1\n81000 PODGORICA\nMontenegro"],

    // Morocco
    [MoroccoAddressFormatter::class, ['line1' => '23 BOULEVARD TAROUDANT',
        'city' => 'ERRACHIDIA',
        'postcode' => '52000',
        'country_code' => 'MA', ], "23 BOULEVARD TAROUDANT\n52000 ERRACHIDIA\nMorocco"],

    // Namibia
    [NamibiaAddressFormatter::class, ['line1' => 'PO Box 999',
        'city' => 'OKAHANDJA',
        'postcode' => '12004',
        'country_code' => 'NA', ], "PO Box 999\nOKAHANDJA\n12004\nNamibia"],

    // Nepal
    [NepalAddressFormatter::class, ['line1' => '102, Mitery Marg',
        'city' => 'KATHMANDU',
        'state' => 'Bagmati',
        'country_code' => 'NP', ], "102, Mitery Marg\nKATHMANDU\nBagmati\nNepal"],

    // NewZealand
    [NewZealandAddressFormatter::class, ['line1' => '100 Queen Street',
        'city' => 'Auckland',
        'postcode' => '1010',
        'country_code' => 'NZ', ], "100 Queen Street\n1010 Auckland\nNew Zealand"],

    // Niger
    [NigerAddressFormatter::class, ['line1' => 'BP 502',
        'city' => 'NIAMEY',
        'postcode' => '8001',
        'country_code' => 'NE', ], "BP 502\n8001 NIAMEY\nNiger"],

    // Niue
    [NiueAddressFormatter::class, ['line1' => 'Huihui Road',
        'city' => 'Alofi',
        'postcode' => '9974',
        'country_code' => 'NU', ], "Huihui Road\nAlofi 9974\nNiue"],

    // NorthMacedonia
    [NorthMacedoniaAddressFormatter::class, ['line1' => 'Bulevar JNA 12',
        'city' => 'KUMANOVO',
        'postcode' => '1310',
        'country_code' => 'MK', ], "Bulevar JNA 12\n1310 KUMANOVO\nNorth Macedonia"],

    // Pakistan
    [PakistanAddressFormatter::class, ['line1' => 'House No 17-B',
        'city' => 'ISLAMABAD',
        'postcode' => '44000',
        'country_code' => 'PK', ], "House No 17-B\nISLAMABAD-44000\nPakistan"],

    // Palestine
    [PalestineAddressFormatter::class, ['line1' => 'Dahiyat al Bareed',
        'city' => 'JERUSALEM',
        'postcode' => 'P126',
        'country_code' => 'PS', ], "Dahiyat al Bareed\nJERUSALEM P126\nPalestine"],

    // PapuaNewGuinea
    [PapuaNewGuineaAddressFormatter::class, ['line1' => 'PO Box 555',
        'city' => 'Lae',
        'postcode' => '211',
        'country_code' => 'PG', ], "PO Box 555\nLae 211\nPapua New Guinea"],

    // Philippines
    [PhilippinesAddressFormatter::class, ['line1' => 'Rm 602 FUBC Bldg, Escolta',
        'city' => 'MANILA',
        'postcode' => '1008',
        'country_code' => 'PH', ], "Rm 602 FUBC Bldg, Escolta\n1008 MANILA\nPhilippines"],
    [PhilippinesAddressFormatter::class, ['line1' => 'Rm 602 FUBC Bldg, Escolta',
        'city' => 'manila',
        'state' => 'MANILA',
        'postcode' => '1008',
        'country_code' => 'PH', ], "Rm 602 FUBC Bldg, Escolta\n1008 MANILA\nPhilippines"],

    // PuertoRico
    [PuertoRicoAddressFormatter::class, ['line1' => 'URB LAS GLADIOLAS',
        'line2' => '150 CALLE A',
        'city' => 'SAN JUAN',
        'postcode' => '00926-0221',
        'country_code' => 'PR', ], "URB LAS GLADIOLAS\n150 CALLE A\nSAN JUAN PR 00926-0221\nPuerto Rico"],

    // Reunion
    [ReunionAddressFormatter::class, ['line1' => 'BP 300',
        'city' => 'SAINT-PIERRE',
        'postcode' => '97410',
        'country_code' => 'RE', ], "BP 300\n97410 SAINT-PIERRE\nReunion"],

    // Rwanda
    [RwandaAddressFormatter::class, ['line1' => 'B.P. 3425',
        'city' => 'KIGALI',
        'country_code' => 'RW', ], "B.P. 3425\nKIGALI\nRwanda"],

    // SaintHelena
    [SaintHelenaAddressFormatter::class, ['line1' => '95 MARKET STREET',
        'city' => 'JAMESTOWN',
        'postcode' => 'STHL 1ZZ',
        'country_code' => 'SH', ], "95 MARKET STREET\nJAMESTOWN STHL 1ZZ\nSaint Helena"],

    // SaintLucia
    [SaintLuciaAddressFormatter::class, ['line1' => 'Block A, Apt 146',
        'line2' => 'High Street',
        'city' => 'CASTRIES',
        'postcode' => 'LC04  101',
        'country_code' => 'LC', ], "Block A, Apt 146\nHigh Street\nCASTRIES, LC04  101\nSaint Lucia"],

    // SaintPierreAndMiquelon
    [SaintPierreAndMiquelonAddressFormatter::class, ['line1' => '24 rue de Paris',
        'city' => 'Saint-Pierre',
        'postcode' => '97500',
        'country_code' => 'PM', ], "24 rue de Paris\n97500 Saint-Pierre\nSaint Pierre and Miquelon"],

    // Samoa
    [SamoaAddressFormatter::class, ['line1' => 'Salenesa Street Motootua',
        'city' => 'Apia',
        'postcode' => 'WS1330',
        'country_code' => 'WS', ], "Salenesa Street Motootua\nApia WS1330\nSamoa"],

    // SaoTomeAndPrincipe
    [SaoTomeAndPrincipeAddressFormatter::class, ['line1' => 'Rua 3 de Fevereiro',
        'city' => 'São Tomé',
        'country_code' => 'ST', ], "Rua 3 de Fevereiro\nSão Tomé\nSao Tome and Principe"],

    // Senegal
    [SenegalAddressFormatter::class, ['line1' => 'BP 1534',
        'city' => 'Ziguinchor',
        'state' => 'Ziguinchor',
        'postcode' => '27000',
        'country_code' => 'SN', ], "BP 1534\n27000 Ziguinchor\nSenegal"],

    // Seychelles
    [SeychellesAddressFormatter::class, ['line1' => 'P.O. Box 1538',
        'city' => 'Victoria',
        'postcode' => '99999',
        'country_code' => 'SC', ], "P.O. Box 1538\nVictoria\n99999\nSeychelles"],

    // Slovakia
    [SlovakiaAddressFormatter::class, ['line1' => 'Národná 5',
        'city' => 'Žilina',
        'postcode' => '010 01',
        'country_code' => 'SK', ], "Národná 5\n010 01 Žilina\nSlovakia"],

    // SolomonIslands
    [SolomonIslandsAddressFormatter::class, ['line1' => 'PO Box 1',
        'city' => 'Honiara',
        'postcode' => '99999',
        'country_code' => 'SB', ], "PO Box 1\nHoniara\n99999\nSolomon Islands"],

    // SouthKorea
    [SouthKoreaAddressFormatter::class, ['line1' => '97-1 Toegye-ro, Jung-gu',
        'state' => 'DAEGU',
        'postcode' => '42007',
        'country_code' => 'KR', ], "97-1 Toegye-ro, Jung-gu\nDAEGU 42007\nSouth Korea"],
    [SouthKoreaAddressFormatter::class, ['line1' => '97-1 Toegye-ro, Jung-gu',
        'state' => 'Seoul',
        'country_code' => 'KR', ], "97-1 Toegye-ro, Jung-gu\nSeoul\nSouth Korea"],

    // Spain
    [SpainAddressFormatter::class, ['line1' => 'Calle Huertas 18, 4º, C',
        'city' => 'MARBELLA',
        'state' => 'MÁLAGA',
        'postcode' => '29400',
        'country_code' => 'ES', ], "Calle Huertas 18, 4º, C\n29400 MARBELLA\nMÁLAGA\nSpain"],

    // Suriname
    [SurinameAddressFormatter::class, ['line1' => 'Walapastraat 2',
        'line2' => 'Bloemendal',
        'city' => 'PARAMARIBO',
        'country_code' => 'SR', ], "Walapastraat 2\nBloemendal\nPARAMARIBO\nSuriname"],

    // Switzerland
    [SwitzerlandAddressFormatter::class, ['line1' => 'Solothurnerstrasse 28',
        'city' => 'BETTLACH',
        'postcode' => '2544',
        'country_code' => 'CH', ], "Solothurnerstrasse 28\n2544 BETTLACH\nSwitzerland"],

    // Taiwan
    [TaiwanAddressFormatter::class, ['line1' => 'No. 55, Sec. 2, Jinshan S. Rd.',
        'city' => 'Taipei City',
        'postcode' => '106409',
        'country_code' => 'TW', ], "No. 55, Sec. 2, Jinshan S. Rd.\nTaipei City 106409\nTaiwan"],

    // Thailand
    [ThailandAddressFormatter::class, ['line1' => '199/63 Moo 1, Tumbol Bangtalad',
        'city' => 'Amphoe Pak Kret',
        'state' => 'Nonthaburi',
        'postcode' => '11120',
        'country_code' => 'TH', ], "199/63 Moo 1, Tumbol Bangtalad\nAmphoe Pak Kret, Nonthaburi\n11120\nThailand"],

    // Togo
    [TogoAddressFormatter::class, ['line1' => 'B.P. 526',
        'city' => 'LOME',
        'country_code' => 'TG', ], "B.P. 526\nLOME\nTogo"],

    // TrinidadAndTobago
    [TrinidadAndTobagoAddressFormatter::class, ['line1' => '135-137 Southern Main Road',
        'city' => 'CHAGUANAS',
        'postcode' => '500234',
        'country_code' => 'TT', ], "135-137 Southern Main Road\nCHAGUANAS 500234\nTrinidad and Tobago"],

    // Turkiye
    [TurkiyeAddressFormatter::class, ['line1' => 'Doğanbey Mah.',
        'city' => 'ULUS',
        'state' => 'ANKARA',
        'postcode' => '06101',
        'country_code' => 'TR', ], "Doğanbey Mah.\n06101 ULUS/ANKARA\nTürkiye"],

    // TurksAndCaicos
    [TurksAndCaicosAddressFormatter::class, ['line1' => 'Airport road',
        'city' => 'DOWNTOWN, PROVIDENCIALES',
        'country_code' => 'TC', ], "Airport road\nDOWNTOWN, PROVIDENCIALES\nTurks and Caicos Islands"],

    // USMinorOutlyingIslands
    [USMinorOutlyingIslandsAddressFormatter::class, ['line1' => 'PO Box 501',
        'city' => 'Wake Island',
        'postcode' => '96898',
        'country_code' => 'UM', ], "PO Box 501\nWake Island\n96898\nUnited States Minor Outlying Islands"],

    // Ukraine
    [UkraineAddressFormatter::class, ['line1' => 'vul. Khreshchatyk, 22',
        'city' => 'KYIV',
        'postcode' => '01055',
        'country_code' => 'UA', ], "vul. Khreshchatyk, 22\nKYIV\n01055\nUkraine"],

    // UnitedArabEmirates
    [UnitedArabEmiratesAddressFormatter::class, ['line1' => 'PO BOX 111',
        'city' => 'Al Ain',
        'state' => 'Abu Dhabi',
        'country_code' => 'AE', ], "PO BOX 111\nAl Ain\nAbu Dhabi\nUnited Arab Emirates"],

    // Uruguay
    [UruguayAddressFormatter::class, ['line1' => 'Av. 18 de Julio 1000',
        'city' => 'MONTEVIDEO',
        'postcode' => '11600',
        'country_code' => 'UY', ], "Av. 18 de Julio 1000\n11600 – MONTEVIDEO\nUruguay"],

    // Venezuela
    [VenezuelaAddressFormatter::class, ['line1' => 'AV. FUERZAS ARMADAS',
        'line2' => 'TORRE SAN JOSÉ, ENTRADA B',
        'line3' => 'PISO 5, APARTAMENTO 20',
        'city' => 'CARACAS',
        'state' => 'D.C.',
        'postcode' => '1010',
        'country_code' => 'VE', ], "AV. FUERZAS ARMADAS\nTORRE SAN JOSÉ, ENTRADA B\nPISO 5, APARTAMENTO 20\nCARACAS 1010\nD.C.\nVenezuela"],

    // WallisAndFutuna
    [WallisAndFutunaAddressFormatter::class, ['line1' => 'BP 5',
        'city' => 'LEAVA',
        'postcode' => '98620',
        'country_code' => 'WF', ], "BP 5\n98620 LEAVA\nWallis and Futuna Islands"],

    // Zambia
    [ZambiaAddressFormatter::class, ['line1' => '21 Independence Avenue',
        'city' => 'LUSAKA',
        'state' => 'Lusaka',
        'country_code' => 'ZM', ], "21 Independence Avenue\nLUSAKA\nZambia"],
]);

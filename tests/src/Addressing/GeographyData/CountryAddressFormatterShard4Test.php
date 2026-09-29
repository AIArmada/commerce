<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Albania\AlbaniaAddressFormatter;
use AIArmada\Addressing\Geography\AmericanSamoa\AmericanSamoaAddressFormatter;
use AIArmada\Addressing\Geography\Anguilla\AnguillaAddressFormatter;
use AIArmada\Addressing\Geography\Argentina\ArgentinaAddressFormatter;
use AIArmada\Addressing\Geography\Aruba\ArubaAddressFormatter;
use AIArmada\Addressing\Geography\Azerbaijan\AzerbaijanAddressFormatter;
use AIArmada\Addressing\Geography\Bahrain\BahrainAddressFormatter;
use AIArmada\Addressing\Geography\Belarus\BelarusAddressFormatter;
use AIArmada\Addressing\Geography\Belize\BelizeAddressFormatter;
use AIArmada\Addressing\Geography\Bermuda\BermudaAddressFormatter;
use AIArmada\Addressing\Geography\Bolivia\BoliviaAddressFormatter;
use AIArmada\Addressing\Geography\Botswana\BotswanaAddressFormatter;
use AIArmada\Addressing\Geography\Bulgaria\BulgariaAddressFormatter;
use AIArmada\Addressing\Geography\Burundi\BurundiAddressFormatter;
use AIArmada\Addressing\Geography\Cameroon\CameroonAddressFormatter;
use AIArmada\Addressing\Geography\CaribbeanNetherlands\CaribbeanNetherlandsAddressFormatter;
use AIArmada\Addressing\Geography\CentralAfricanRepublic\CentralAfricanRepublicAddressFormatter;
use AIArmada\Addressing\Geography\Chile\ChileAddressFormatter;
use AIArmada\Addressing\Geography\Colombia\ColombiaAddressFormatter;
use AIArmada\Addressing\Geography\Congo\CongoAddressFormatter;
use AIArmada\Addressing\Geography\Croatia\CroatiaAddressFormatter;
use AIArmada\Addressing\Geography\Cyprus\CyprusAddressFormatter;
use AIArmada\Addressing\Geography\Denmark\DenmarkAddressFormatter;
use AIArmada\Addressing\Geography\Dominica\DominicaAddressFormatter;
use AIArmada\Addressing\Geography\Ecuador\EcuadorAddressFormatter;
use AIArmada\Addressing\Geography\ElSalvador\ElSalvadorAddressFormatter;
use AIArmada\Addressing\Geography\Eritrea\EritreaAddressFormatter;
use AIArmada\Addressing\Geography\Eswatini\EswatiniAddressFormatter;
use AIArmada\Addressing\Geography\Fiji\FijiAddressFormatter;
use AIArmada\Addressing\Geography\France\FranceAddressFormatter;
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
use AIArmada\Addressing\Geography\Iraq\IraqAddressFormatter;
use AIArmada\Addressing\Geography\IsleOfMan\IsleOfManAddressFormatter;
use AIArmada\Addressing\Geography\Jamaica\JamaicaAddressFormatter;
use AIArmada\Addressing\Geography\Jersey\JerseyAddressFormatter;
use AIArmada\Addressing\Geography\Kazakhstan\KazakhstanAddressFormatter;
use AIArmada\Addressing\Geography\Kosovo\KosovoAddressFormatter;
use AIArmada\Addressing\Geography\Kyrgyzstan\KyrgyzstanAddressFormatter;
use AIArmada\Addressing\Geography\Latvia\LatviaAddressFormatter;
use AIArmada\Addressing\Geography\Lesotho\LesothoAddressFormatter;
use AIArmada\Addressing\Geography\Libya\LibyaAddressFormatter;
use AIArmada\Addressing\Geography\Lithuania\LithuaniaAddressFormatter;
use AIArmada\Addressing\Geography\Malawi\MalawiAddressFormatter;
use AIArmada\Addressing\Geography\Mali\MaliAddressFormatter;
use AIArmada\Addressing\Geography\MarshallIslands\MarshallIslandsAddressFormatter;
use AIArmada\Addressing\Geography\Mauritania\MauritaniaAddressFormatter;
use AIArmada\Addressing\Geography\Mayotte\MayotteAddressFormatter;
use AIArmada\Addressing\Geography\Micronesia\MicronesiaAddressFormatter;
use AIArmada\Addressing\Geography\Monaco\MonacoAddressFormatter;
use AIArmada\Addressing\Geography\Montenegro\MontenegroAddressFormatter;
use AIArmada\Addressing\Geography\Mozambique\MozambiqueAddressFormatter;
use AIArmada\Addressing\Geography\Nauru\NauruAddressFormatter;
use AIArmada\Addressing\Geography\Netherlands\NetherlandsAddressFormatter;
use AIArmada\Addressing\Geography\NewZealand\NewZealandAddressFormatter;
use AIArmada\Addressing\Geography\Niger\NigerAddressFormatter;
use AIArmada\Addressing\Geography\NorthKorea\NorthKoreaAddressFormatter;
use AIArmada\Addressing\Geography\Norway\NorwayAddressFormatter;
use AIArmada\Addressing\Geography\Palau\PalauAddressFormatter;
use AIArmada\Addressing\Geography\Panama\PanamaAddressFormatter;
use AIArmada\Addressing\Geography\Paraguay\ParaguayAddressFormatter;
use AIArmada\Addressing\Geography\Philippines\PhilippinesAddressFormatter;
use AIArmada\Addressing\Geography\Poland\PolandAddressFormatter;
use AIArmada\Addressing\Geography\PuertoRico\PuertoRicoAddressFormatter;
use AIArmada\Addressing\Geography\Romania\RomaniaAddressFormatter;
use AIArmada\Addressing\Geography\Rwanda\RwandaAddressFormatter;
use AIArmada\Addressing\Geography\SaintHelena\SaintHelenaAddressFormatter;
use AIArmada\Addressing\Geography\SaintLucia\SaintLuciaAddressFormatter;
use AIArmada\Addressing\Geography\SaintPierreAndMiquelon\SaintPierreAndMiquelonAddressFormatter;
use AIArmada\Addressing\Geography\Samoa\SamoaAddressFormatter;
use AIArmada\Addressing\Geography\SaoTomeAndPrincipe\SaoTomeAndPrincipeAddressFormatter;
use AIArmada\Addressing\Geography\Serbia\SerbiaAddressFormatter;
use AIArmada\Addressing\Geography\SierraLeone\SierraLeoneAddressFormatter;
use AIArmada\Addressing\Geography\Slovenia\SloveniaAddressFormatter;
use AIArmada\Addressing\Geography\Somalia\SomaliaAddressFormatter;
use AIArmada\Addressing\Geography\SouthKorea\SouthKoreaAddressFormatter;
use AIArmada\Addressing\Geography\SriLanka\SriLankaAddressFormatter;
use AIArmada\Addressing\Geography\Suriname\SurinameAddressFormatter;
use AIArmada\Addressing\Geography\Switzerland\SwitzerlandAddressFormatter;
use AIArmada\Addressing\Geography\Tajikistan\TajikistanAddressFormatter;
use AIArmada\Addressing\Geography\TimorLeste\TimorLesteAddressFormatter;
use AIArmada\Addressing\Geography\Togo\TogoAddressFormatter;
use AIArmada\Addressing\Geography\TrinidadAndTobago\TrinidadAndTobagoAddressFormatter;
use AIArmada\Addressing\Geography\Turkmenistan\TurkmenistanAddressFormatter;
use AIArmada\Addressing\Geography\Tuvalu\TuvaluAddressFormatter;
use AIArmada\Addressing\Geography\UnitedArabEmirates\UnitedArabEmiratesAddressFormatter;
use AIArmada\Addressing\Geography\UnitedKingdom\UnitedKingdomAddressFormatter;
use AIArmada\Addressing\Geography\USVirginIslands\USVirginIslandsAddressFormatter;
use AIArmada\Addressing\Geography\Uzbekistan\UzbekistanAddressFormatter;
use AIArmada\Addressing\Geography\Venezuela\VenezuelaAddressFormatter;
use AIArmada\Addressing\Geography\Yemen\YemenAddressFormatter;
use AIArmada\Addressing\Geography\Zimbabwe\ZimbabweAddressFormatter;

/**
 * Every country's address formatter, one dataset row each (shard 3 of 4).
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
    // Albania
    [AlbaniaAddressFormatter::class, ['line1' => 'Ruga Myslym Shyri',
        'line2' => 'Pallati 37 shkalla 4 apartamenti 15',
        'city' => 'TIRANA',
        'postcode' => '1001',
        'country_code' => 'AL', ], "Ruga Myslym Shyri\nPallati 37 shkalla 4 apartamenti 15\n1001\nTIRANA\nAlbania"],

    // AmericanSamoa
    [AmericanSamoaAddressFormatter::class, ['line1' => 'PO BOX 999',
        'city' => 'PAGO PAGO',
        'postcode' => '96799-1234',
        'country_code' => 'AS', ], "PO BOX 999\nPAGO PAGO AS 96799-1234\nAmerican Samoa"],

    // Anguilla
    [AnguillaAddressFormatter::class, ['line1' => 'P.O. Box 60',
        'city' => 'The Valley',
        'postcode' => 'AI-2640',
        'country_code' => 'AI', ], "P.O. Box 60\nThe Valley\nAI-2640\nAnguilla"],

    // Argentina
    [ArgentinaAddressFormatter::class, ['line1' => 'TUCUMAN 1560',
        'city' => 'VILLA MARIA',
        'postcode' => 'Y5900FNF',
        'country_code' => 'AR', ], "TUCUMAN 1560\nY5900FNF VILLA MARIA\nArgentina"],

    // Aruba
    [ArubaAddressFormatter::class, ['line1' => 'L.G. Smith Boulevard #160',
        'city' => 'ORANJESTAD',
        'postcode' => '99999',
        'country_code' => 'AW', ], "L.G. Smith Boulevard #160\nORANJESTAD\n99999\nAruba"],

    // Azerbaijan
    [AzerbaijanAddressFormatter::class, ['line1' => 'Zrifliyeva küç., ev 9',
        'city' => 'Bakı',
        'postcode' => 'AZ1010',
        'country_code' => 'AZ', ], "Zrifliyeva küç., ev 9\nAZ1010 Bakı\nAzerbaijan"],

    // Bahrain
    [BahrainAddressFormatter::class, ['line1' => 'House no. 888',
        'city' => 'AL-MANAMAH',
        'postcode' => '317',
        'country_code' => 'BH', ], "House no. 888\nAL-MANAMAH 317\nBahrain"],

    // Belarus
    [BelarusAddressFormatter::class, ['line1' => 'pr-t Masherova, d.1, kv.12',
        'city' => 'Minsk',
        'postcode' => '220005',
        'country_code' => 'BY', ], "pr-t Masherova, d.1, kv.12\n220005, Minsk\nBelarus"],

    // Belize
    [BelizeAddressFormatter::class, ['line1' => 'C Street  Apt 2',
        'city' => 'KINGS PARK, BELIZE CITY',
        'country_code' => 'BZ', ], "C Street  Apt 2\nKINGS PARK, BELIZE CITY\nBelize"],

    // Bermuda
    [BermudaAddressFormatter::class, ['line1' => 'Upper Apt # 1',
        'line2' => '9 Leafy Lane',
        'state' => "SMITH'S",
        'postcode' => 'FL 07',
        'country_code' => 'BM', ], "Upper Apt # 1\n9 Leafy Lane\nSMITH'S FL 07\nBermuda"],

    // Bolivia
    [BoliviaAddressFormatter::class, ['line1' => 'CALLE AZURDUY 158',
        'city' => 'SUCRE',
        'country_code' => 'BO', ], "CALLE AZURDUY 158\nSUCRE\nBolivia"],

    // Botswana
    [BotswanaAddressFormatter::class, ['line1' => 'P.O. Box 231',
        'city' => 'HUKUNTSI',
        'country_code' => 'BW', ], "P.O. Box 231\nHUKUNTSI\nBotswana"],

    // Bulgaria
    [BulgariaAddressFormatter::class, ['line1' => 'ul. Aleksandur Ekzarkh 2',
        'city' => 'PLOVDIV',
        'postcode' => '4000',
        'country_code' => 'BG', ], "ul. Aleksandur Ekzarkh 2\n4000 PLOVDIV\nBulgaria"],

    // Burundi
    [BurundiAddressFormatter::class, ['line1' => 'BP 1915',
        'city' => 'MUKAZA',
        'state' => 'Bujumbura',
        'country_code' => 'BI', ], "BP 1915\nMUKAZA\nBujumbura\nBurundi"],

    // Cameroon
    [CameroonAddressFormatter::class, ['line1' => 'B.P. 8035',
        'city' => 'YAOUNDE',
        'country_code' => 'CM', ], "B.P. 8035\nYAOUNDE\nCameroon"],

    // CaribbeanNetherlands
    [CaribbeanNetherlandsAddressFormatter::class, ['line1' => 'Kaya Grandi 5',
        'city' => 'KRALENDIJK',
        'state' => 'Bonaire',
        'country_code' => 'BQ', ], "Kaya Grandi 5\nKRALENDIJK\nBonaire\nBonaire, Sint Eustatius and Saba"],

    // CentralAfricanRepublic
    [CentralAfricanRepublicAddressFormatter::class, ['line1' => 'BP 729',
        'city' => 'BANGUI',
        'country_code' => 'CF', ], "BP 729\nBANGUI\nCentral African Republic"],

    // Chile
    [ChileAddressFormatter::class, ['line1' => 'Moneda 1152',
        'city' => 'SANTIAGO',
        'state' => 'REGION METROPOLITANA',
        'postcode' => '8340648',
        'country_code' => 'CL', ], "Moneda 1152\n8340648 SANTIAGO\nREGION METROPOLITANA\nChile"],

    // Colombia
    [ColombiaAddressFormatter::class, ['line1' => 'CARRERA 7 NO. 27-18',
        'city' => 'PLANETA RICA',
        'state' => 'CORDOBA',
        'postcode' => '233057',
        'country_code' => 'CO', ], "CARRERA 7 NO. 27-18\nPLANETA RICA 233057\nCORDOBA\nColombia"],

    // Congo
    [CongoAddressFormatter::class, ['line1' => '12, rue Kakamoueka',
        'city' => 'BRAZZAVILLE',
        'postcode' => '99999',
        'country_code' => 'CG', ], "12, rue Kakamoueka\nBRAZZAVILLE\n99999\nCongo"],

    // Croatia
    [CroatiaAddressFormatter::class, ['line1' => 'P.P. 105',
        'city' => 'SPLIT',
        'postcode' => '21001',
        'country_code' => 'HR', ], "P.P. 105\n21001 SPLIT\nCroatia"],

    // Cyprus
    [CyprusAddressFormatter::class, ['line1' => 'Griva Digeni 10',
        'city' => 'Larnaka',
        'postcode' => '6036',
        'country_code' => 'CY', ], "Griva Digeni 10\n6036 Larnaka\nCyprus"],

    // Denmark
    [DenmarkAddressFormatter::class, ['line1' => 'Kastanievej 15, 2, Agerskov',
        'city' => 'SKANDERBORG',
        'postcode' => '8660',
        'country_code' => 'DK', ], "Kastanievej 15, 2, Agerskov\n8660 SKANDERBORG\nDenmark"],

    // Dominica
    [DominicaAddressFormatter::class, ['line1' => 'Bay Front',
        'city' => 'ROSEAU',
        'country_code' => 'DM', ], "Bay Front\nROSEAU\nDominica"],

    // Ecuador
    [EcuadorAddressFormatter::class, ['line1' => 'Francisco Dalmau 547 y Calle 2',
        'line2' => 'Conjunto Real Audiencia, Bloque 3',
        'line3' => 'Conocoto',
        'city' => 'QUITO',
        'postcode' => '170303',
        'country_code' => 'EC', ], "Francisco Dalmau 547 y Calle 2\nConjunto Real Audiencia, Bloque 3\nConocoto\n170303 - QUITO\nEcuador"],

    // ElSalvador
    [ElSalvadorAddressFormatter::class, ['line1' => 'APARTADO POSTAL 131',
        'line2' => 'SUCURSAL SOPAYANGO',
        'city' => 'SAN SALVADOR',
        'postcode' => '1116',
        'country_code' => 'SV', ], "APARTADO POSTAL 131\nSUCURSAL SOPAYANGO\n1116 SAN SALVADOR\nEl Salvador"],

    // Eritrea
    [EritreaAddressFormatter::class, ['line1' => 'Awet Street 4',
        'city' => 'ASMARA',
        'postcode' => '99999',
        'country_code' => 'ER', ], "Awet Street 4\nASMARA\n99999\nEritrea"],

    // Eswatini
    [EswatiniAddressFormatter::class, ['line1' => 'P.O. Box 200',
        'city' => 'Manzini',
        'state' => 'Manzini',
        'postcode' => 'M200',
        'country_code' => 'SZ', ], "P.O. Box 200\nManzini\nM200\nEswatini"],

    // Fiji
    [FijiAddressFormatter::class, ['line1' => '14 VIRIA STREET',
        'line2' => 'VATUWAQA',
        'city' => 'SUVA',
        'country_code' => 'FJ', ], "14 VIRIA STREET\nVATUWAQA\nSUVA\nFiji Islands"],

    // France
    [FranceAddressFormatter::class, ['line1' => '25 RUE DES FLEURS',
        'city' => 'LIBOURNE',
        'postcode' => '33500',
        'country_code' => 'FR', ], "25 RUE DES FLEURS\n33500 LIBOURNE\nFrance"],

    // FrenchPolynesia
    [FrenchPolynesiaAddressFormatter::class, ['line1' => 'Rue de l’Aéroport',
        'city' => 'FAAA',
        'postcode' => '98704',
        'country_code' => 'PF', ], "Rue de l’Aéroport\n98704 FAAA\nFrench Polynesia"],

    // Gabon
    [GabonAddressFormatter::class, ['line1' => 'BP 45',
        'city' => 'TCHIBANGA',
        'state' => 'Nyanga',
        'postcode' => '05',
        'country_code' => 'GA', ], "BP 45\n05 TCHIBANGA\nNyanga\nGabon"],

    // Georgia
    [GeorgiaAddressFormatter::class, ['line1' => 'Tavisupleba Street 5',
        'city' => 'Telavi',
        'state' => 'Kakheti',
        'postcode' => '2200',
        'country_code' => 'GE', ], "Tavisupleba Street 5\n2200 Telavi\nKakheti\nGeorgia"],

    // Greece
    [GreeceAddressFormatter::class, ['line1' => 'P.O. BOX 999',
        'city' => 'MAROUSI',
        'postcode' => '151 10',
        'country_code' => 'GR', ], "P.O. BOX 999\n151 10 MAROUSI\nGreece"],

    // Grenada
    [GrenadaAddressFormatter::class, ['line1' => 'P.O. BOX 1234',
        'city' => "ST. GEORGE'S",
        'country_code' => 'GD', ], "P.O. BOX 1234\nST. GEORGE'S\nGrenada"],

    // Guam
    [GuamAddressFormatter::class, ['line1' => 'PO Box 1',
        'city' => 'Hagatna',
        'postcode' => '96910',
        'country_code' => 'GU', ], "PO Box 1\nHagatna GU 96910\nGuam"],

    // Guernsey
    [GuernseyAddressFormatter::class, ['line1' => 'La Seigneurie',
        'city' => 'SARK',
        'postcode' => 'GY10 1SF',
        'country_code' => 'GG', ], "La Seigneurie\nSARK\nGY10 1SF\nGuernsey"],

    // Guinea
    [GuineaAddressFormatter::class, ['line1' => 'BP 12',
        'city' => 'Labé',
        'state' => 'Labé',
        'postcode' => '201',
        'country_code' => 'GN', ], "BP 12\n201 Labé\nGuinea"],

    // Haiti
    [HaitiAddressFormatter::class, ['line1' => 'Rue Capois 5',
        'city' => 'PORT-AU-PRINCE',
        'postcode' => 'HT6110',
        'country_code' => 'HT', ], "Rue Capois 5\nHT6110 PORT-AU-PRINCE\nHaiti"],

    // HongKong
    [HongKongAddressFormatter::class, ['line1' => '150 Kennedy Road',
        'city' => 'WAN CHAI',
        'postcode' => '000',
        'country_code' => 'HK', ], "150 Kennedy Road\nWAN CHAI\n000\nHong Kong"],

    // Iceland
    [IcelandAddressFormatter::class, ['line1' => 'Ingólfsstræti 3',
        'city' => 'REYKJAVÍK',
        'postcode' => '121',
        'country_code' => 'IS', ], "Ingólfsstræti 3\n121 REYKJAVÍK\nIceland"],

    // Iraq
    [IraqAddressFormatter::class, ['line1' => 'Hay AL Asmaee, Zukak 2',
        'city' => 'AL ASMAEE',
        'state' => 'AL BASRAH',
        'postcode' => '61002',
        'country_code' => 'IQ', ], "Hay AL Asmaee, Zukak 2\nAL ASMAEE, AL BASRAH\n61002\nIraq"],

    // IsleOfMan
    [IsleOfManAddressFormatter::class, ['line1' => '50 Athol Street',
        'city' => 'Douglas',
        'state' => 'Middle',
        'postcode' => 'IM1 1JB',
        'country_code' => 'IM', ], "50 Athol Street\nDouglas\nMiddle\nIM1 1JB\nIsle of Man"],

    // Jamaica
    [JamaicaAddressFormatter::class, ['line1' => 'Lot 19 Mona Estate',
        'line2' => 'Bog Walk',
        'line3' => 'Bog Walk PO',
        'city' => 'St. Catherine',
        'country_code' => 'JM', ], "Lot 19 Mona Estate\nBog Walk\nBog Walk PO\nSt. Catherine\nJamaica"],

    // Jersey
    [JerseyAddressFormatter::class, ['line1' => 'Town View',
        'line2' => 'Stopford Road',
        'line3' => 'St Helier',
        'city' => 'JERSEY',
        'postcode' => 'JE2 4LB',
        'country_code' => 'JE', ], "Town View\nStopford Road\nSt Helier\nJERSEY\nJE2 4LB\nJersey"],

    // Kazakhstan
    [KazakhstanAddressFormatter::class, ['line1' => 'Abay Street 1',
        'city' => 'Taldykorgan',
        'state' => 'Jetisu',
        'postcode' => '040000',
        'country_code' => 'KZ', ], "Abay Street 1\n040000, Taldykorgan\nJetisu\nKazakhstan"],

    // Kosovo
    [KosovoAddressFormatter::class, ['line1' => 'Rruga Lidhja e Prizrenit 10',
        'city' => 'Pristina',
        'postcode' => '10000',
        'country_code' => 'XK', ], "Rruga Lidhja e Prizrenit 10\n10000 Pristina\nKosovo"],

    // Kyrgyzstan
    [KyrgyzstanAddressFormatter::class, ['line1' => 'Lenin Street 12',
        'city' => 'KARAKOL',
        'state' => 'Issyk-Kul',
        'postcode' => '721600',
        'country_code' => 'KG', ], "Lenin Street 12\n721600 KARAKOL\nIssyk-Kul\nKyrgyzstan"],

    // Latvia
    [LatviaAddressFormatter::class, ['line1' => 'Valdemāra street 42, Ainaži',
        'city' => 'SALACGRIVAS NOV.',
        'postcode' => 'LV-4035',
        'country_code' => 'LV', ], "Valdemāra street 42, Ainaži\nSALACGRIVAS NOV., LV-4035\nLatvia"],

    // Lesotho
    [LesothoAddressFormatter::class, ['line1' => 'P.O. Box 500',
        'city' => 'Maseru',
        'state' => 'Maseru',
        'country_code' => 'LS', ], "P.O. Box 500\nMaseru\nLesotho"],

    // Libya
    [LibyaAddressFormatter::class, ['line1' => 'Av. Al Ghazaly 12',
        'city' => 'TRIPOLI',
        'postcode' => '99999',
        'country_code' => 'LY', ], "Av. Al Ghazaly 12\nTRIPOLI\n99999\nLibya"],

    // Lithuania
    [LithuaniaAddressFormatter::class, ['line1' => 'Laisvės al. 60',
        'city' => 'Kaunas',
        'postcode' => '44280',
        'country_code' => 'LT', ], "Laisvės al. 60\n44280 Kaunas\nLithuania"],

    // Malawi
    [MalawiAddressFormatter::class, ['line1' => '21 Dunduzu Avenue',
        'city' => 'KASUNGU',
        'postcode' => '102010',
        'country_code' => 'MW', ], "21 Dunduzu Avenue\n102010 KASUNGU\nMalawi"],

    // Mali
    [MaliAddressFormatter::class, ['line1' => 'Rue 406 – porte 39',
        'line2' => 'Magnabougou',
        'city' => 'BAMAKO',
        'country_code' => 'ML', ], "Rue 406 – porte 39\nMagnabougou\nBAMAKO\nMali"],

    // MarshallIslands
    [MarshallIslandsAddressFormatter::class, ['line1' => 'P.O. Box 175',
        'city' => 'Majuro',
        'postcode' => '96960',
        'country_code' => 'MH', ], "P.O. Box 175\nMajuro MH 96960\nMarshall Islands"],

    // Mauritania
    [MauritaniaAddressFormatter::class, ['line1' => 'B.P. 35',
        'city' => 'NOUAKCHOTT',
        'country_code' => 'MR', ], "B.P. 35\nNOUAKCHOTT\nMauritania"],

    // Mayotte
    [MayotteAddressFormatter::class, ['line1' => 'Rue de la Mairie',
        'city' => 'MAMOUDZOU',
        'postcode' => '97600',
        'country_code' => 'YT', ], "Rue de la Mairie\n97600 MAMOUDZOU\nMayotte"],

    // Micronesia
    [MicronesiaAddressFormatter::class, ['line1' => 'PO Box 9',
        'city' => 'Weno',
        'postcode' => '96942',
        'country_code' => 'FM', ], "PO Box 9\nWeno FM 96942\nMicronesia"],

    // Monaco
    [MonacoAddressFormatter::class, ['line1' => 'BP 112',
        'city' => 'MONACO',
        'postcode' => '98001',
        'country_code' => 'MC', ], "BP 112\n98001 MONACO\nMonaco"],

    // Montenegro
    [MontenegroAddressFormatter::class, ['line1' => 'Jadranska magistrala 5',
        'city' => 'BAR',
        'postcode' => '85000',
        'country_code' => 'ME', ], "Jadranska magistrala 5\n85000 BAR\nMontenegro"],

    // Mozambique
    [MozambiqueAddressFormatter::class, ['line1' => 'AV. Julius Nyerere 3412',
        'city' => 'MAPUTO',
        'state' => 'MAPUTO',
        'postcode' => '1100',
        'country_code' => 'MZ', ], "AV. Julius Nyerere 3412\n1100 MAPUTO\nMAPUTO\nMozambique"],

    // Nauru
    [NauruAddressFormatter::class, ['line1' => 'Mr John James',
        'city' => 'BOE DISTRICT',
        'postcode' => 'NRU68',
        'country_code' => 'NR', ], "Mr John James\nBOE DISTRICT\nNRU68\nNauru"],

    // Netherlands
    [NetherlandsAddressFormatter::class, ['line1' => 'Drieslag 5-1',
        'city' => 'ARNHEM',
        'postcode' => '6832 am',
        'country_code' => 'NL', ], "Drieslag 5-1\n6832 AM  ARNHEM\nNetherlands"],

    // NewZealand
    [NewZealandAddressFormatter::class, ['line1' => 'PO Box 1',
        'city' => 'Wellington',
        'postcode' => '6011',
        'country_code' => 'NZ', ], "PO Box 1\n6011 Wellington\nNew Zealand"],

    // Niger
    [NigerAddressFormatter::class, ['line1' => 'BP 502',
        'city' => 'NY',
        'state' => 'Niamey',
        'postcode' => '8000',
        'country_code' => 'NE', ], "BP 502\n8000 NY\nNiamey\nNiger"],

    // NorthKorea
    [NorthKoreaAddressFormatter::class, ['line1' => 'Quartier Bottongang',
        'city' => 'PYONGYANG',
        'country_code' => 'KP', ], "Quartier Bottongang\nPYONGYANG\nNorth Korea"],

    // Norway
    [NorwayAddressFormatter::class, ['line1' => 'Karl Johansgate 25 B',
        'city' => 'OSLO',
        'postcode' => '0025',
        'country_code' => 'NO', ], "Karl Johansgate 25 B\n0025 OSLO\nNorway"],

    // Palau
    [PalauAddressFormatter::class, ['line1' => 'PO Box 100',
        'city' => 'Koror',
        'postcode' => '96940',
        'country_code' => 'PW', ], "PO Box 100\nKoror PW 96940\nPalau"],

    // Panama
    [PanamaAddressFormatter::class, ['line1' => 'VIA ESPAÑA, CALLE 6ta',
        'line2' => 'EDIFICIO DEL PADRO No2, TERCER PISO, LOCAL No10, PARQUE LEFEVRE',
        'city' => 'PARQUE LEFEVRE',
        'state' => 'PROVINCIA DE PANAMÁ',
        'country_code' => 'PA', ], "VIA ESPAÑA, CALLE 6ta\nEDIFICIO DEL PADRO No2, TERCER PISO, LOCAL No10, PARQUE LEFEVRE\nPARQUE LEFEVRE\nPROVINCIA DE PANAMÁ\nPanama"],

    // Paraguay
    [ParaguayAddressFormatter::class, ['line1' => 'Estrella Nº 340, casi Yegros',
        'line2' => 'Edif. España, Bloque A, Piso 3, Depto. 10',
        'city' => 'ASUNCIÓN',
        'state' => 'CENTRAL',
        'postcode' => '001218',
        'country_code' => 'PY', ], "Estrella Nº 340, casi Yegros\nEdif. España, Bloque A, Piso 3, Depto. 10\n001218 ASUNCIÓN\nCENTRAL\nParaguay"],

    // Philippines
    [PhilippinesAddressFormatter::class, ['line1' => '96 Hermogenes St., Sofa Subdivision',
        'city' => 'San Fernando',
        'state' => 'PAMPANGA',
        'postcode' => '2000',
        'country_code' => 'PH', ], "96 Hermogenes St., Sofa Subdivision\nSan Fernando\n2000 PAMPANGA\nPhilippines"],

    // Poland
    [PolandAddressFormatter::class, ['line1' => 'Ul. Kręta 15m 10',
        'city' => 'WARSZAWA',
        'postcode' => '00-950',
        'country_code' => 'PL', ], "Ul. Kręta 15m 10\n00-950 WARSZAWA\nPoland"],

    // PuertoRico
    [PuertoRicoAddressFormatter::class, ['line1' => 'Calle Luna 10',
        'city' => 'Santurce',
        'state' => 'San Juan',
        'postcode' => '00907',
        'country_code' => 'PR', ], "Calle Luna 10\nSanturce\nSan Juan PR 00907\nPuerto Rico"],

    // Romania
    [RomaniaAddressFormatter::class, ['line1' => 'Drumul Taberei nr. 35, bl. F5, sc. 2, parter, ap. 23',
        'line2' => 'Sector 6',
        'city' => 'BUCHAREST',
        'postcode' => '061357',
        'country_code' => 'RO', ], "Drumul Taberei nr. 35, bl. F5, sc. 2, parter, ap. 23\nSector 6\n061357 BUCHAREST\nRomania"],

    // Rwanda
    [RwandaAddressFormatter::class, ['line1' => 'KN 3 Road',
        'city' => 'Butare',
        'state' => 'Southern',
        'country_code' => 'RW', ], "KN 3 Road\nButare\nSouthern\nRwanda"],

    // SaintHelena
    [SaintHelenaAddressFormatter::class, ['line1' => 'PO Box 1',
        'city' => 'Georgetown',
        'postcode' => 'ASCN 1ZZ',
        'country_code' => 'SH', ], "PO Box 1\nGeorgetown ASCN 1ZZ\nSaint Helena"],

    // SaintLucia
    [SaintLuciaAddressFormatter::class, ['line1' => 'Church Street',
        'city' => 'CHOISEUL',
        'postcode' => 'LC10  101',
        'country_code' => 'LC', ], "Church Street\nCHOISEUL, LC10  101\nSaint Lucia"],

    // SaintPierreAndMiquelon
    [SaintPierreAndMiquelonAddressFormatter::class, ['line1' => 'Rue de la Chapelle',
        'city' => 'Miquelon',
        'postcode' => '97500',
        'country_code' => 'PM', ], "Rue de la Chapelle\n97500 Miquelon\nSaint Pierre and Miquelon"],

    // Samoa
    [SamoaAddressFormatter::class, ['line1' => 'PO Box 1',
        'city' => 'Apia',
        'postcode' => 'WS1330',
        'country_code' => 'WS', ], "PO Box 1\nApia WS1330\nSamoa"],

    // SaoTomeAndPrincipe
    [SaoTomeAndPrincipeAddressFormatter::class, ['line1' => 'Rua 3 de Fevereiro',
        'city' => 'São Tomé',
        'postcode' => '99999',
        'country_code' => 'ST', ], "Rua 3 de Fevereiro\nSão Tomé\n99999\nSao Tome and Principe"],

    // Serbia
    [SerbiaAddressFormatter::class, ['line1' => 'Beogradska 3',
        'city' => 'BAJMOK',
        'postcode' => '24210',
        'country_code' => 'RS', ], "Beogradska 3\n24210 BAJMOK\nSerbia"],

    // SierraLeone
    [SierraLeoneAddressFormatter::class, ['line1' => '7A Ross Road Cline',
        'city' => 'FREETOWN',
        'country_code' => 'SL', ], "7A Ross Road Cline\nFREETOWN\nSierra Leone"],

    // Slovenia
    [SloveniaAddressFormatter::class, ['line1' => 'Prešemova ul. 16',
        'city' => 'KRANJ',
        'postcode' => '4000',
        'country_code' => 'SI', ], "Prešemova ul. 16\n4000 KRANJ\nSlovenia"],

    // Somalia
    [SomaliaAddressFormatter::class, ['line1' => 'P.O. Box 1001',
        'city' => 'KISMAYU',
        'country_code' => 'SO', ], "P.O. Box 1001\nKISMAYU\nSomalia"],

    // SouthKorea
    [SouthKoreaAddressFormatter::class, ['line1' => '97-1 Toegye-ro, Jung-gu',
        'city' => 'Seoul',
        'state' => 'Seoul',
        'postcode' => '03187',
        'country_code' => 'KR', ], "97-1 Toegye-ro, Jung-gu\nSeoul 03187\nSouth Korea"],
    [SouthKoreaAddressFormatter::class, ['line1' => '97-1 Toegye-ro, Jung-gu',
        'city' => 'Seoul',
        'state' => 'Seoul',
        'country_code' => 'KR', ], "97-1 Toegye-ro, Jung-gu\nSeoul\nSouth Korea"],

    // SriLanka
    [SriLankaAddressFormatter::class, ['line1' => '201 Shanti Villa',
        'line2' => 'Silkhouse Street',
        'city' => 'KANDY',
        'postcode' => '20000',
        'country_code' => 'LK', ], "201 Shanti Villa\nSilkhouse Street\nKANDY\n20000\nSri Lanka"],

    // Suriname
    [SurinameAddressFormatter::class, ['line1' => 'Walapastraat 2',
        'city' => 'PARAMARIBO',
        'postcode' => '99999',
        'country_code' => 'SR', ], "Walapastraat 2\nPARAMARIBO\n99999\nSuriname"],

    // Switzerland
    [SwitzerlandAddressFormatter::class, ['line1' => 'Route de la Gare 2',
        'city' => 'CERNIAZ VD',
        'postcode' => '1556',
        'country_code' => 'CH', ], "Route de la Gare 2\n1556 CERNIAZ VD\nSwitzerland"],

    // Tajikistan
    [TajikistanAddressFormatter::class, ['line1' => 'Khoutchandi 7',
        'city' => 'GARM',
        'postcode' => '735450',
        'country_code' => 'TJ', ], "Khoutchandi 7\n735450 GARM\nTajikistan"],

    // TimorLeste
    [TimorLesteAddressFormatter::class, ['line1' => 'AVENIDA CAPITA SINMAU',
        'state' => 'AINARO',
        'postcode' => 'TL42000',
        'country_code' => 'TL', ], "AVENIDA CAPITA SINMAU\nAINARO TL42000\nTimor-Leste"],

    // Togo
    [TogoAddressFormatter::class, ['line1' => 'Rue des Jasmins',
        'city' => 'Kpalimé',
        'state' => 'Plateaux',
        'country_code' => 'TG', ], "Rue des Jasmins\nKpalimé\nPlateaux\nTogo"],

    // TrinidadAndTobago
    [TrinidadAndTobagoAddressFormatter::class, ['line1' => 'PO Box 1872',
        'city' => 'PORT-OF-SPAIN',
        'postcode' => '150123',
        'country_code' => 'TT', ], "PO Box 1872\nPORT-OF-SPAIN 150123\nTrinidad and Tobago"],

    // Turkmenistan
    [TurkmenistanAddressFormatter::class, ['line1' => 'j. 19 otag 1',
        'line2' => 'kv. 122',
        'city' => 'BALKANABAT',
        'state' => 'Balkan',
        'postcode' => '745100',
        'country_code' => 'TM', ], "j. 19 otag 1\nkv. 122\nBALKANABAT\nBalkan\n745100\nTurkmenistan"],

    // Tuvalu
    [TuvaluAddressFormatter::class, ['line1' => 'PO Box 1',
        'city' => 'Funafuti',
        'country_code' => 'TV', ], "PO Box 1\nFunafuti\nTuvalu"],

    // USVirginIslands
    [USVirginIslandsAddressFormatter::class, ['line1' => '123 Main St.',
        'city' => 'ST THOMAS',
        'postcode' => '00802-1222',
        'country_code' => 'VI', ], "123 Main St.\nST THOMAS VI 00802-1222\nVirgin Islands (US)"],

    // UnitedArabEmirates
    [UnitedArabEmiratesAddressFormatter::class, ['line1' => 'PO BOX 111',
        'city' => 'DUBAI',
        'country_code' => 'AE', ], "PO BOX 111\nDUBAI\nUnited Arab Emirates"],

    // UnitedKingdom
    [UnitedKingdomAddressFormatter::class, ['line1' => '49 Featherstone Street',
        'city' => 'LONDON',
        'state' => 'Greater London',
        'postcode' => 'ec1y 8sy',
        'country_code' => 'GB', ], "49 Featherstone Street\nLONDON\nEC1Y 8SY\nUnited Kingdom"],

    // Uzbekistan
    [UzbekistanAddressFormatter::class, ['line1' => 'pr-t Mustakillik, d. 5, kv. 12',
        'city' => 'g. Tashkent 123',
        'postcode' => '100123',
        'country_code' => 'UZ', ], "pr-t Mustakillik, d. 5, kv. 12\n100123, g. Tashkent 123\nUzbekistan"],

    // Venezuela
    [VenezuelaAddressFormatter::class, ['line1' => 'Calle Bolívar 3',
        'city' => 'SANARE',
        'state' => 'LARA',
        'postcode' => '3028-A',
        'country_code' => 'VE', ], "Calle Bolívar 3\nSANARE 3028-A\nLARA\nVenezuela"],

    // Yemen
    [YemenAddressFormatter::class, ['line1' => 'B.P. 1993',
        'city' => "SANA'A",
        'country_code' => 'YE', ], "B.P. 1993\nSANA'A\nYemen"],

    // Zimbabwe
    [ZimbabweAddressFormatter::class, ['line1' => '34–6th Crescent',
        'line2' => 'Warren Park 1',
        'city' => 'HARARE',
        'country_code' => 'ZW', ], "34–6th Crescent\nWarren Park 1\nHARARE\nZimbabwe"],
]);

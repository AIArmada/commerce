<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Aland\AlandAddressFormatter;
use AIArmada\Addressing\Geography\Algeria\AlgeriaAddressFormatter;
use AIArmada\Addressing\Geography\Andorra\AndorraAddressFormatter;
use AIArmada\Addressing\Geography\AntiguaAndBarbuda\AntiguaAndBarbudaAddressFormatter;
use AIArmada\Addressing\Geography\Armenia\ArmeniaAddressFormatter;
use AIArmada\Addressing\Geography\Austria\AustriaAddressFormatter;
use AIArmada\Addressing\Geography\Bahamas\BahamasAddressFormatter;
use AIArmada\Addressing\Geography\Barbados\BarbadosAddressFormatter;
use AIArmada\Addressing\Geography\Belgium\BelgiumAddressFormatter;
use AIArmada\Addressing\Geography\Benin\BeninAddressFormatter;
use AIArmada\Addressing\Geography\Bhutan\BhutanAddressFormatter;
use AIArmada\Addressing\Geography\BosniaAndHerzegovina\BosniaAndHerzegovinaAddressFormatter;
use AIArmada\Addressing\Geography\Brazil\BrazilAddressFormatter;
use AIArmada\Addressing\Geography\BurkinaFaso\BurkinaFasoAddressFormatter;
use AIArmada\Addressing\Geography\Cambodia\CambodiaAddressFormatter;
use AIArmada\Addressing\Geography\CapeVerde\CapeVerdeAddressFormatter;
use AIArmada\Addressing\Geography\CaymanIslands\CaymanIslandsAddressFormatter;
use AIArmada\Addressing\Geography\Chad\ChadAddressFormatter;
use AIArmada\Addressing\Geography\China\ChinaAddressFormatter;
use AIArmada\Addressing\Geography\Comoros\ComorosAddressFormatter;
use AIArmada\Addressing\Geography\CostaRica\CostaRicaAddressFormatter;
use AIArmada\Addressing\Geography\Cuba\CubaAddressFormatter;
use AIArmada\Addressing\Geography\CzechRepublic\CzechRepublicAddressFormatter;
use AIArmada\Addressing\Geography\Djibouti\DjiboutiAddressFormatter;
use AIArmada\Addressing\Geography\DominicanRepublic\DominicanRepublicAddressFormatter;
use AIArmada\Addressing\Geography\Egypt\EgyptAddressFormatter;
use AIArmada\Addressing\Geography\EquatorialGuinea\EquatorialGuineaAddressFormatter;
use AIArmada\Addressing\Geography\Estonia\EstoniaAddressFormatter;
use AIArmada\Addressing\Geography\FaroeIslands\FaroeIslandsAddressFormatter;
use AIArmada\Addressing\Geography\Finland\FinlandAddressFormatter;
use AIArmada\Addressing\Geography\FrenchGuiana\FrenchGuianaAddressFormatter;
use AIArmada\Addressing\Geography\FrenchSouthernTerritories\FrenchSouthernTerritoriesAddressFormatter;
use AIArmada\Addressing\Geography\Gambia\GambiaAddressFormatter;
use AIArmada\Addressing\Geography\Ghana\GhanaAddressFormatter;
use AIArmada\Addressing\Geography\Greenland\GreenlandAddressFormatter;
use AIArmada\Addressing\Geography\Guadeloupe\GuadeloupeAddressFormatter;
use AIArmada\Addressing\Geography\Guatemala\GuatemalaAddressFormatter;
use AIArmada\Addressing\Geography\GuineaBissau\GuineaBissauAddressFormatter;
use AIArmada\Addressing\Geography\Guyana\GuyanaAddressFormatter;
use AIArmada\Addressing\Geography\Honduras\HondurasAddressFormatter;
use AIArmada\Addressing\Geography\Hungary\HungaryAddressFormatter;
use AIArmada\Addressing\Geography\Iran\IranAddressFormatter;
use AIArmada\Addressing\Geography\Ireland\IrelandAddressFormatter;
use AIArmada\Addressing\Geography\IvoryCoast\IvoryCoastAddressFormatter;
use AIArmada\Addressing\Geography\Japan\JapanAddressFormatter;
use AIArmada\Addressing\Geography\Jordan\JordanAddressFormatter;
use AIArmada\Addressing\Geography\Kiribati\KiribatiAddressFormatter;
use AIArmada\Addressing\Geography\Kuwait\KuwaitAddressFormatter;
use AIArmada\Addressing\Geography\Laos\LaosAddressFormatter;
use AIArmada\Addressing\Geography\Lebanon\LebanonAddressFormatter;
use AIArmada\Addressing\Geography\Liberia\LiberiaAddressFormatter;
use AIArmada\Addressing\Geography\Liechtenstein\LiechtensteinAddressFormatter;
use AIArmada\Addressing\Geography\Luxembourg\LuxembourgAddressFormatter;
use AIArmada\Addressing\Geography\Maldives\MaldivesAddressFormatter;
use AIArmada\Addressing\Geography\Malta\MaltaAddressFormatter;
use AIArmada\Addressing\Geography\Martinique\MartiniqueAddressFormatter;
use AIArmada\Addressing\Geography\Mauritius\MauritiusAddressFormatter;
use AIArmada\Addressing\Geography\Mexico\MexicoAddressFormatter;
use AIArmada\Addressing\Geography\Moldova\MoldovaAddressFormatter;
use AIArmada\Addressing\Geography\Mongolia\MongoliaAddressFormatter;
use AIArmada\Addressing\Geography\Montserrat\MontserratAddressFormatter;
use AIArmada\Addressing\Geography\Namibia\NamibiaAddressFormatter;
use AIArmada\Addressing\Geography\Nepal\NepalAddressFormatter;
use AIArmada\Addressing\Geography\NewCaledonia\NewCaledoniaAddressFormatter;
use AIArmada\Addressing\Geography\Nicaragua\NicaraguaAddressFormatter;
use AIArmada\Addressing\Geography\Niue\NiueAddressFormatter;
use AIArmada\Addressing\Geography\NorthMacedonia\NorthMacedoniaAddressFormatter;
use AIArmada\Addressing\Geography\Oman\OmanAddressFormatter;
use AIArmada\Addressing\Geography\Palestine\PalestineAddressFormatter;
use AIArmada\Addressing\Geography\PapuaNewGuinea\PapuaNewGuineaAddressFormatter;
use AIArmada\Addressing\Geography\Peru\PeruAddressFormatter;
use AIArmada\Addressing\Geography\Philippines\PhilippinesAddressFormatter;
use AIArmada\Addressing\Geography\Portugal\PortugalAddressFormatter;
use AIArmada\Addressing\Geography\Reunion\ReunionAddressFormatter;
use AIArmada\Addressing\Geography\Russia\RussiaAddressFormatter;
use AIArmada\Addressing\Geography\SaintBarthelemy\SaintBarthelemyAddressFormatter;
use AIArmada\Addressing\Geography\SaintKittsAndNevis\SaintKittsAndNevisAddressFormatter;
use AIArmada\Addressing\Geography\SaintMartin\SaintMartinAddressFormatter;
use AIArmada\Addressing\Geography\SaintVincentAndTheGrenadines\SaintVincentAndTheGrenadinesAddressFormatter;
use AIArmada\Addressing\Geography\SanMarino\SanMarinoAddressFormatter;
use AIArmada\Addressing\Geography\Senegal\SenegalAddressFormatter;
use AIArmada\Addressing\Geography\Seychelles\SeychellesAddressFormatter;
use AIArmada\Addressing\Geography\Slovakia\SlovakiaAddressFormatter;
use AIArmada\Addressing\Geography\SolomonIslands\SolomonIslandsAddressFormatter;
use AIArmada\Addressing\Geography\SouthAfrica\SouthAfricaAddressFormatter;
use AIArmada\Addressing\Geography\SouthKorea\SouthKoreaAddressFormatter;
use AIArmada\Addressing\Geography\SouthSudan\SouthSudanAddressFormatter;
use AIArmada\Addressing\Geography\Sudan\SudanAddressFormatter;
use AIArmada\Addressing\Geography\Sweden\SwedenAddressFormatter;
use AIArmada\Addressing\Geography\Syria\SyriaAddressFormatter;
use AIArmada\Addressing\Geography\Tanzania\TanzaniaAddressFormatter;
use AIArmada\Addressing\Geography\TimorLeste\TimorLesteAddressFormatter;
use AIArmada\Addressing\Geography\Tonga\TongaAddressFormatter;
use AIArmada\Addressing\Geography\Tunisia\TunisiaAddressFormatter;
use AIArmada\Addressing\Geography\TurksAndCaicos\TurksAndCaicosAddressFormatter;
use AIArmada\Addressing\Geography\Uganda\UgandaAddressFormatter;
use AIArmada\Addressing\Geography\UnitedArabEmirates\UnitedArabEmiratesAddressFormatter;
use AIArmada\Addressing\Geography\Uruguay\UruguayAddressFormatter;
use AIArmada\Addressing\Geography\USMinorOutlyingIslands\USMinorOutlyingIslandsAddressFormatter;
use AIArmada\Addressing\Geography\Vanuatu\VanuatuAddressFormatter;
use AIArmada\Addressing\Geography\WallisAndFutuna\WallisAndFutunaAddressFormatter;
use AIArmada\Addressing\Geography\Zambia\ZambiaAddressFormatter;

/**
 * Every country's address formatter, one dataset row each (shard 1 of 4).
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
        'postcode' => 'AX-22100',
        'country_code' => 'AX', ], "Stadshusparken\nAX-22100 MARIEHAMN\nAland Islands"],

    // Algeria
    [AlgeriaAddressFormatter::class, ['line1' => "2, rue de l'Indépendance",
        'city' => 'ALGIERS',
        'postcode' => '16027',
        'country_code' => 'DZ', ], "2, rue de l'Indépendance\n16027 ALGIERS\nAlgeria"],

    // Andorra
    [AndorraAddressFormatter::class, ['line1' => 'Avinguda Meritxell 10',
        'city' => 'ANDORRA LA VELLA',
        'postcode' => 'AD500',
        'country_code' => 'AD', ], "Avinguda Meritxell 10\nAD500 ANDORRA LA VELLA\nAndorra"],

    // AntiguaAndBarbuda
    [AntiguaAndBarbudaAddressFormatter::class, ['line1' => 'P.O. Box 123',
        'city' => "ST. JOHN'S",
        'country_code' => 'AG', ], "P.O. Box 123\nST. JOHN'S\nAntigua and Barbuda"],

    // Armenia
    [ArmeniaAddressFormatter::class, ['line1' => 'Mashtots Street 1',
        'city' => 'Vedi',
        'state' => 'Ararat',
        'postcode' => '0601',
        'country_code' => 'AM', ], "Mashtots Street 1\n0601 Vedi\nArarat\nArmenia"],

    // Austria
    [AustriaAddressFormatter::class, ['line1' => 'Rennbahnweg 25/2/15',
        'city' => 'WIEN',
        'postcode' => '1220',
        'country_code' => 'AT', ], "Rennbahnweg 25/2/15\n1220 WIEN\nAustria"],

    // Bahamas
    [BahamasAddressFormatter::class, ['line1' => 'P.O. Box GT 2001',
        'city' => 'Nassau',
        'country_code' => 'BS', ], "P.O. Box GT 2001\nNassau\nThe Bahamas"],

    // Barbados
    [BarbadosAddressFormatter::class, ['line1' => '#35, "The Grotto"',
        'line2' => '04th Ave., Cherry Lane',
        'line3' => 'Farm Road',
        'state' => 'St. Peter',
        'postcode' => 'BB26028',
        'country_code' => 'BB', ], "#35, \"The Grotto\"\n04th Ave., Cherry Lane\nFarm Road\nSt. Peter BB26028\nBarbados"],

    // Belgium
    [BelgiumAddressFormatter::class, ['line1' => 'Volklorenlaan 81 bus 15',
        'city' => 'Wilrijk',
        'postcode' => '2610',
        'country_code' => 'BE', ], "Volklorenlaan 81 bus 15\n2610 Wilrijk\nBelgium"],

    // Benin
    [BeninAddressFormatter::class, ['line1' => '10 BP 648',
        'city' => 'COTONOU',
        'country_code' => 'BJ', ], "10 BP 648\nCOTONOU\nBenin"],

    // Bhutan
    [BhutanAddressFormatter::class, ['line1' => 'Chang Lam, JD House',
        'line2' => 'Flat No. 2B',
        'city' => 'Thimphu',
        'state' => 'Thimphu',
        'postcode' => '11001',
        'country_code' => 'BT', ], "Chang Lam, JD House\nFlat No. 2B\nThimphu 11001\nBhutan"],

    // BosniaAndHerzegovina
    [BosniaAndHerzegovinaAddressFormatter::class, ['line1' => 'Semira Fraste E6/6',
        'city' => 'SARAJEVO',
        'postcode' => '71000',
        'country_code' => 'BA', ], "Semira Fraste E6/6\n71000 SARAJEVO\nBosnia and Herzegovina"],

    // Brazil
    [BrazilAddressFormatter::class, ['line1' => 'RUA XV DE NOVEMBRO, 1751',
        'city' => 'GUARAPUAVA',
        'state' => 'Paraná',
        'postcode' => '85070-200',
        'country_code' => 'BR', ], "RUA XV DE NOVEMBRO, 1751\nGUARAPUAVA - PR\n85070-200\nBrazil"],

    // BurkinaFaso
    [BurkinaFasoAddressFormatter::class, ['line1' => '566 Avenue de la Nation',
        'city' => 'OUAGADOUGOU',
        'postcode' => '10010',
        'country_code' => 'BF', ], "566 Avenue de la Nation\n10010 OUAGADOUGOU\nBurkina Faso"],

    // Cambodia
    [CambodiaAddressFormatter::class, ['line1' => '100E0 Street 118',
        'state' => 'PHNOM PENH',
        'postcode' => '120209',
        'country_code' => 'KH', ], "100E0 Street 118\nPHNOM PENH 120209\nCambodia"],

    // CapeVerde
    [CapeVerdeAddressFormatter::class, ['line1' => 'Rua 5 de Julho 138/Platô',
        'line2' => 'C.P. 38',
        'city' => 'PRAIA',
        'postcode' => '7600',
        'country_code' => 'CV', ], "Rua 5 de Julho 138/Platô\nC.P. 38\n7600 PRAIA\nCape Verde"],

    // CaymanIslands
    [CaymanIslandsAddressFormatter::class, ['line1' => 'P.O. Box 802',
        'state' => 'Grand Cayman',
        'postcode' => 'KY1-1103',
        'country_code' => 'KY', ], "P.O. Box 802\nGrand Cayman  KY1-1103\nCayman Islands"],

    // Chad
    [ChadAddressFormatter::class, ['line1' => 'BP 4148',
        'city' => 'NDJAMENA',
        'country_code' => 'TD', ], "BP 4148\nNDJAMENA\nChad"],

    // China
    [ChinaAddressFormatter::class, ['line1' => 'No.1 Jianguomenwai Avenue',
        'state' => 'BEIJING',
        'postcode' => '100004',
        'country_code' => 'CN', ], "No.1 Jianguomenwai Avenue\n100004 BEIJING\nChina"],

    // Comoros
    [ComorosAddressFormatter::class, ['line1' => 'Rue de la Corniche',
        'city' => 'Moroni',
        'state' => 'Grande Comore',
        'country_code' => 'KM', ], "Rue de la Corniche\nMoroni\nGrande Comore\nComoros"],

    // CostaRica
    [CostaRicaAddressFormatter::class, ['line1' => 'Apdo 257 – 3017',
        'city' => 'Heredia, San Isidro, San Isidro',
        'postcode' => '3017-40601',
        'country_code' => 'CR', ], "Apdo 257 – 3017\nHeredia, San Isidro, San Isidro\n3017-40601\nCosta Rica"],

    // Cuba
    [CubaAddressFormatter::class, ['line1' => 'Calle 23 No. 55',
        'city' => 'CIUDAD HABANA',
        'postcode' => '10600',
        'country_code' => 'CU', ], "Calle 23 No. 55\n10600 CIUDAD HABANA\nCuba"],

    // CzechRepublic
    [CzechRepublicAddressFormatter::class, ['line1' => 'Roprachtice 129',
        'city' => 'Roprachtice',
        'state' => 'Liberecký kraj',
        'postcode' => '513 01',
        'country_code' => 'CZ', ], "Roprachtice 129\n513 01 Roprachtice\nLiberecký kraj\nCzech Republic"],

    // Djibouti
    [DjiboutiAddressFormatter::class, ['line1' => 'BP 1663',
        'city' => 'DJIBOUTI VILLE',
        'postcode' => '77101',
        'country_code' => 'DJ', ], "BP 1663\n77101 DJIBOUTI VILLE\nDjibouti"],

    // DominicanRepublic
    [DominicanRepublicAddressFormatter::class, ['line1' => 'C/45 # 33',
        'line2' => 'Katanga, Los Minas',
        'city' => 'SANTO DOMINGO',
        'postcode' => '11903',
        'country_code' => 'DO', ], "C/45 # 33\nKatanga, Los Minas\n11903 SANTO DOMINGO\nDominican Republic"],

    // Egypt
    [EgyptAddressFormatter::class, ['line1' => '30 Moussa Galal street',
        'city' => 'Al-Mohandessine',
        'state' => 'Giza',
        'postcode' => '3759914',
        'country_code' => 'EG', ], "30 Moussa Galal street\nAl-Mohandessine\nGiza\n3759914\nEgypt"],

    // EquatorialGuinea
    [EquatorialGuineaAddressFormatter::class, ['line1' => 'Apartado postal 7',
        'city' => 'MALABO',
        'postcode' => '99999',
        'country_code' => 'GQ', ], "Apartado postal 7\nMALABO\n99999\nEquatorial Guinea"],

    // Estonia
    [EstoniaAddressFormatter::class, ['line1' => 'Allika talu',
        'line2' => 'Halliste alevik',
        'city' => 'VILJANDIMAA',
        'postcode' => '69501',
        'country_code' => 'EE', ], "Allika talu\nHalliste alevik\n69501 VILJANDIMAA\nEstonia"],

    // FaroeIslands
    [FaroeIslandsAddressFormatter::class, ['line1' => 'Óðinshædd 2',
        'city' => 'Tórshavn',
        'postcode' => 'FO-100',
        'country_code' => 'FO', ], "Óðinshædd 2\nFO-100 Tórshavn\nFaroe Islands"],

    // Finland
    [FinlandAddressFormatter::class, ['line1' => 'Mäkelänkatu 25 B 13',
        'city' => 'HELSINKI',
        'postcode' => '00550',
        'country_code' => 'FI', ], "Mäkelänkatu 25 B 13\n00550 HELSINKI\nFinland"],

    // FrenchGuiana
    [FrenchGuianaAddressFormatter::class, ['line1' => 'Avenue des Roches 1',
        'city' => 'KOUROU',
        'postcode' => '97310',
        'country_code' => 'GF', ], "Avenue des Roches 1\n97310 KOUROU\nFrench Guiana"],

    // FrenchSouthernTerritories
    [FrenchSouthernTerritoriesAddressFormatter::class, ['line1' => 'Base Alfred Faure',
        'city' => 'Port-aux-Français',
        'postcode' => '98400',
        'country_code' => 'TF', ], "Base Alfred Faure\nPort-aux-Français\n98400\nFrench Southern Territories"],

    // Gambia
    [GambiaAddressFormatter::class, ['line1' => 'Main Street',
        'city' => 'Basse',
        'state' => 'Upper River',
        'country_code' => 'GM', ], "Main Street\nBasse\nUpper River\nThe Gambia"],

    // Ghana
    [GhanaAddressFormatter::class, ['line1' => 'P. O. BOX GP 224',
        'city' => 'Accra-Central',
        'state' => 'GREATER ACCRA',
        'postcode' => 'GA-183-8164',
        'country_code' => 'GH', ], "P. O. BOX GP 224\nAccra-Central GA-183-8164\nGREATER ACCRA\nGhana"],

    // Greenland
    [GreenlandAddressFormatter::class, ['line1' => 'PO Box 9',
        'city' => 'Ilulissat',
        'postcode' => '3952',
        'country_code' => 'GL', ], "PO Box 9\n3952 Ilulissat\nGreenland"],

    // Guadeloupe
    [GuadeloupeAddressFormatter::class, ['line1' => 'Rue Frébault 8',
        'city' => 'POINTE-A-PITRE',
        'postcode' => '97110',
        'country_code' => 'GP', ], "Rue Frébault 8\n97110 POINTE-A-PITRE\nGuadeloupe"],

    // Guatemala
    [GuatemalaAddressFormatter::class, ['line1' => 'Calle Principal 1',
        'city' => 'Villa Canales',
        'postcode' => '01065',
        'country_code' => 'GT', ], "Calle Principal 1\n01065 - Villa Canales\nGuatemala"],

    // GuineaBissau
    [GuineaBissauAddressFormatter::class, ['line1' => 'Rua Justino Lopes 12C',
        'city' => 'BISSAU',
        'country_code' => 'GW', ], "Rua Justino Lopes 12C\nBISSAU\nGuinea-Bissau"],

    // Guyana
    [GuyanaAddressFormatter::class, ['line1' => 'Lot 12 Public Road',
        'city' => 'East Coast Demerara',
        'postcode' => '4212501',
        'country_code' => 'GY', ], "Lot 12 Public Road\nEast Coast Demerara\n4212501\nGuyana"],

    // Honduras
    [HondurasAddressFormatter::class, ['line1' => 'Barrio El Centro',
        'city' => 'LAS LAJAS',
        'state' => 'COMAYAGUA',
        'postcode' => 'CM1102',
        'country_code' => 'HN', ], "Barrio El Centro\nCM1102 LAS LAJAS\nCOMAYAGUA\nHonduras"],

    // Hungary
    [HungaryAddressFormatter::class, ['line1' => 'PF. 83',
        'city' => 'DABAS',
        'postcode' => '2380',
        'country_code' => 'HU', ], "PF. 83\n2380 DABAS\nHungary"],

    // Iran
    [IranAddressFormatter::class, ['line1' => 'West 196 street',
        'line2' => 'No. 12 third floor',
        'city' => 'Tehranpars',
        'state' => 'Tehran Province',
        'postcode' => '1619614153',
        'country_code' => 'IR', ], "West 196 street\nNo. 12 third floor\nTehranpars\nTehran Province\n1619614153\nIran"],

    // Ireland
    [IrelandAddressFormatter::class, ['line1' => '12 Grafton Street',
        'city' => 'DUBLIN 2',
        'state' => 'Dublin',
        'postcode' => 'D02 TF12',
        'country_code' => 'IE', ], "12 Grafton Street\nDUBLIN 2\nDublin\nD02 TF12\nIreland"],

    // IvoryCoast
    [IvoryCoastAddressFormatter::class, ['line1' => '06 B.P. 37',
        'city' => 'ABIDJAN',
        'country_code' => 'CI', ], "06 B.P. 37\nABIDJAN\nIvory Coast"],

    // Japan
    [JapanAddressFormatter::class, ['line1' => '10-23, Mitsugi 1-chome',
        'city' => 'Musashi-Murayama-shi',
        'state' => 'TOKYO',
        'postcode' => '231-0012',
        'country_code' => 'JP', ], "10-23, Mitsugi 1-chome\nMusashi-Murayama-shi, TOKYO\n231-0012 Japan"],

    // Jordan
    [JordanAddressFormatter::class, ['line1' => 'Al Mohazab Al Halabi',
        'city' => 'AMMAN',
        'postcode' => '11937',
        'country_code' => 'JO', ], "Al Mohazab Al Halabi\nAMMAN 11937\nJordan"],

    // Kiribati
    [KiribatiAddressFormatter::class, ['line1' => 'PO Box 487',
        'line2' => 'Betio',
        'city' => 'Sth Tarawa',
        'postcode' => 'KI0108',
        'country_code' => 'KI', ], "PO Box 487\nBetio\nSth Tarawa KI0108\nKiribati"],

    // Kuwait
    [KuwaitAddressFormatter::class, ['line1' => 'Al-Sabbahiya',
        'city' => 'KUWAIT',
        'postcode' => '54551',
        'country_code' => 'KW', ], "Al-Sabbahiya\n54551 KUWAIT\nKuwait"],

    // Laos
    [LaosAddressFormatter::class, ['line1' => '14, rue That Louang',
        'city' => 'Xaysetha',
        'state' => 'Vientiane',
        'postcode' => '01160',
        'country_code' => 'LA', ], "14, rue That Louang\n01160 Xaysetha\nVientiane\nLaos"],

    // Lebanon
    [LebanonAddressFormatter::class, ['line1' => 'Building Al Amal, 2nd floor',
        'city' => 'Raoucheh',
        'state' => 'Beirut',
        'country_code' => 'LB', ], "Building Al Amal, 2nd floor\nRaoucheh\nBeirut\nLebanon"],

    // Liberia
    [LiberiaAddressFormatter::class, ['line1' => 'Water Street',
        'city' => 'Monrovia',
        'state' => 'Montserrado',
        'country_code' => 'LR', ], "Water Street\nMonrovia\nMontserrado\nLiberia"],

    // Liechtenstein
    [LiechtensteinAddressFormatter::class, ['line1' => 'Poststrasse 1',
        'city' => 'Schaan',
        'postcode' => 'LI-9494',
        'country_code' => 'LI', ], "Poststrasse 1\nLI-9494 Schaan\nLiechtenstein"],

    // Luxembourg
    [LuxembourgAddressFormatter::class, ['line1' => '2, rue de la Gare',
        'city' => 'Luxembourg',
        'state' => 'Luxembourg',
        'postcode' => 'L-1118',
        'country_code' => 'LU', ], "2, rue de la Gare\nL-1118 Luxembourg\nLuxembourg"],

    // Maldives
    [MaldivesAddressFormatter::class, ['line1' => '26, BODUTHAKURUFAANU MAGU',
        'city' => 'MALÉ',
        'postcode' => '20026',
        'country_code' => 'MV', ], "26, BODUTHAKURUFAANU MAGU\nMALÉ 20026\nMaldives"],

    // Malta
    [MaltaAddressFormatter::class, ['line1' => '38 Triq it-Tempji Neolitici',
        'city' => 'IL-HAMRUN',
        'postcode' => 'HMR 1428',
        'country_code' => 'MT', ], "38 Triq it-Tempji Neolitici\nIL-HAMRUN\nHMR 1428\nMalta"],

    // Martinique
    [MartiniqueAddressFormatter::class, ['line1' => '25 RUE CARNOT',
        'city' => 'LA TRINITE',
        'postcode' => '97220',
        'country_code' => 'MQ', ], "25 RUE CARNOT\n97220 LA TRINITE\nMartinique"],

    // Mauritius
    [MauritiusAddressFormatter::class, ['line1' => '10, rue Claude Delaître',
        'line2' => 'Les Guibies',
        'city' => 'PORT LOUIS',
        'postcode' => '11213',
        'country_code' => 'MU', ], "10, rue Claude Delaître\nLes Guibies\nPORT LOUIS 11213\nMauritius"],

    // Mexico
    [MexicoAddressFormatter::class, ['line1' => 'Insurgentes Sur 1602',
        'city' => 'MEXICO',
        'state' => 'Ciudad de México',
        'postcode' => '02860',
        'country_code' => 'MX', ], "Insurgentes Sur 1602\n02860 MEXICO, CDMX\nMexico"],

    // Moldova
    [MoldovaAddressFormatter::class, ['line1' => 'Str. Eminescu, nr. 25/1, ap. 14',
        'city' => 'CHISINAU',
        'postcode' => '2012',
        'country_code' => 'MD', ], "Str. Eminescu, nr. 25/1, ap. 14\n2012, CHISINAU\nMoldova"],

    // Mongolia
    [MongoliaAddressFormatter::class, ['line1' => 'Jigjidjav street 9-11 toot',
        'city' => 'Bayanzurkh',
        'state' => 'Ulaanbaatar',
        'postcode' => '14560',
        'country_code' => 'MN', ], "Jigjidjav street 9-11 toot\nBayanzurkh\nUlaanbaatar 14560\nMongolia"],

    // Montserrat
    [MontserratAddressFormatter::class, ['line1' => 'PO Box 12',
        'city' => 'Olveston',
        'postcode' => 'MSR1350',
        'country_code' => 'MS', ], "PO Box 12\nOlveston, MSR1350\nMontserrat"],

    // Namibia
    [NamibiaAddressFormatter::class, ['line1' => 'Private bag 13678',
        'city' => 'WINDHOEK',
        'postcode' => '10005',
        'country_code' => 'NA', ], "Private bag 13678\nWINDHOEK\n10005\nNamibia"],

    // Nepal
    [NepalAddressFormatter::class, ['line1' => '102, Mitery Marg',
        'line2' => 'Baneshwore',
        'city' => 'KATHMANDU',
        'state' => 'Bagmati',
        'postcode' => '44601',
        'country_code' => 'NP', ], "102, Mitery Marg\nBaneshwore\nKATHMANDU 44601\nBagmati\nNepal"],

    // NewCaledonia
    [NewCaledoniaAddressFormatter::class, ['line1' => 'BP 485',
        'city' => 'MONT-DORE',
        'postcode' => '98810',
        'country_code' => 'NC', ], "BP 485\n98810 MONT-DORE\nNew Caledonia"],

    // Nicaragua
    [NicaraguaAddressFormatter::class, ['line1' => 'Calle La Calzada 4',
        'city' => 'Granada',
        'postcode' => '43000',
        'country_code' => 'NI', ], "Calle La Calzada 4\n43000\nGranada\nNicaragua"],

    // Niue
    [NiueAddressFormatter::class, ['line1' => 'PO Box 39',
        'city' => 'Alofi Central',
        'postcode' => '9974',
        'country_code' => 'NU', ], "PO Box 39\nAlofi Central 9974\nNiue"],

    // NorthMacedonia
    [NorthMacedoniaAddressFormatter::class, ['line1' => '"Ilindenska" 2/1-8',
        'city' => 'SKOPJE',
        'postcode' => '1020',
        'country_code' => 'MK', ], "\"Ilindenska\" 2/1-8\n1020 SKOPJE\nNorth Macedonia"],

    // Oman
    [OmanAddressFormatter::class, ['line1' => 'P.O. Box 15',
        'city' => 'AL-KHOER',
        'postcode' => '133',
        'country_code' => 'OM', ], "P.O. Box 15\n133\nAL-KHOER\nOman"],

    // Palestine
    [PalestineAddressFormatter::class, ['line1' => 'Irsal Street 10',
        'city' => 'RAMALLAH AND AL-BIREH',
        'postcode' => 'P6100154',
        'country_code' => 'PS', ], "Irsal Street 10\nRAMALLAH AND AL-BIREH P6100154\nPalestine"],

    // PapuaNewGuinea
    [PapuaNewGuineaAddressFormatter::class, ['line1' => 'Section 20 Lot 40 Lagatoi Place',
        'city' => 'Port Moresby',
        'postcode' => '111',
        'country_code' => 'PG', ], "Section 20 Lot 40 Lagatoi Place\nPort Moresby 111\nPapua New Guinea"],

    // Peru
    [PeruAddressFormatter::class, ['line1' => 'Jr. Jorge Salazar Araoz. N° 171',
        'state' => 'LIMA',
        'postcode' => '15074',
        'country_code' => 'PE', ], "Jr. Jorge Salazar Araoz. N° 171\n15074\nLIMA\nPeru"],

    // Philippines
    [PhilippinesAddressFormatter::class, ['line1' => 'P.O. Box 1121, Araneta Center P.O.',
        'city' => 'Quezon City',
        'state' => 'METRO MANILA',
        'postcode' => '1135',
        'country_code' => 'PH', ], "P.O. Box 1121, Araneta Center P.O.\n1135 Quezon City, METRO MANILA\nPhilippines"],

    // Portugal
    [PortugalAddressFormatter::class, ['line1' => 'Avenida da Liberdade 100',
        'city' => 'LISBOA',
        'postcode' => '1601-801',
        'country_code' => 'PT', ], "Avenida da Liberdade 100\n1601-801 LISBOA\nPortugal"],

    // Reunion
    [ReunionAddressFormatter::class, ['line1' => 'Rue de Paris',
        'city' => 'SAINT-DENIS',
        'postcode' => '97400',
        'country_code' => 'RE', ], "Rue de Paris\n97400 SAINT-DENIS\nReunion"],

    // Russia
    [RussiaAddressFormatter::class, ['line1' => 'ul. Lesnaya d. 5, kv.176',
        'city' => 'MOSKVA',
        'postcode' => '123456',
        'country_code' => 'RU', ], "ul. Lesnaya d. 5, kv.176\nMOSKVA\n123456\nRussia"],

    // SaintBarthelemy
    [SaintBarthelemyAddressFormatter::class, ['line1' => 'Rue de la République 2',
        'city' => 'Gustavia',
        'postcode' => '97133',
        'country_code' => 'BL', ], "Rue de la République 2\n97133 Gustavia\nSaint-Barthelemy"],

    // SaintKittsAndNevis
    [SaintKittsAndNevisAddressFormatter::class, ['line1' => 'Main Street',
        'city' => 'Charlestown',
        'state' => 'Nevis',
        'postcode' => 'KN0902',
        'country_code' => 'KN', ], "Main Street\nCharlestown\nNevis\nKN0902\nSaint Kitts and Nevis"],

    // SaintMartin
    [SaintMartinAddressFormatter::class, ['line1' => 'Rue de la République 4',
        'city' => 'Marigot',
        'postcode' => '97150',
        'country_code' => 'MF', ], "Rue de la République 4\n97150 Marigot\nSaint-Martin (French part)"],

    // SaintVincentAndTheGrenadines
    [SaintVincentAndTheGrenadinesAddressFormatter::class, ['line1' => 'P.O BOX BQ400',
        'city' => 'BEQUIA',
        'postcode' => 'VC0400',
        'country_code' => 'VC', ], "P.O BOX BQ400\nBEQUIA\nVC0400\nSaint Vincent and the Grenadines"],

    // SanMarino
    [SanMarinoAddressFormatter::class, ['line1' => 'Via del Serrone 12',
        'city' => 'Serravalle',
        'postcode' => '47899',
        'country_code' => 'SM', ], "Via del Serrone 12\n47899 Serravalle\nSan Marino"],

    // Senegal
    [SenegalAddressFormatter::class, ['line1' => '12 AVENUE CHEIKH ANTA DIOP',
        'city' => 'DAKAR',
        'postcode' => '12500',
        'country_code' => 'SN', ], "12 AVENUE CHEIKH ANTA DIOP\n12500 DAKAR\nSenegal"],

    // Seychelles
    [SeychellesAddressFormatter::class, ['line1' => 'S19 - E36',
        'city' => 'Victoria',
        'state' => 'Mahé',
        'country_code' => 'SC', ], "S19 - E36\nVictoria\nMahé\nSeychelles"],

    // Slovakia
    [SlovakiaAddressFormatter::class, ['line1' => 'Lúčna 1157/13',
        'city' => 'TRNAVA',
        'postcode' => '917 01',
        'country_code' => 'SK', ], "Lúčna 1157/13\n917 01 TRNAVA\nSlovakia"],

    // SolomonIslands
    [SolomonIslandsAddressFormatter::class, ['line1' => 'PO Box 1',
        'city' => 'Honiara',
        'country_code' => 'SB', ], "PO Box 1\nHoniara\nSolomon Islands"],

    // SouthAfrica
    [SouthAfricaAddressFormatter::class, ['line1' => '442 Thirteenth Avenue',
        'city' => 'FISH HOEK',
        'state' => 'Western Cape',
        'postcode' => '7975',
        'country_code' => 'ZA', ], "442 Thirteenth Avenue\nFISH HOEK\n7975\nSouth Africa"],

    // SouthKorea
    [SouthKoreaAddressFormatter::class, ['line1' => '97-1 Toegye-ro',
        'city' => 'Jung-gu',
        'state' => 'Seoul',
        'country_code' => 'KR', ], "97-1 Toegye-ro\nJung-gu\nSeoul\nSouth Korea"],

    // SouthSudan
    [SouthSudanAddressFormatter::class, ['line1' => 'P.O Box 449',
        'city' => 'JUBA',
        'postcode' => '99999',
        'country_code' => 'SS', ], "P.O Box 449\nJUBA\n99999\nSouth Sudan"],

    // Sudan
    [SudanAddressFormatter::class, ['line1' => 'B.P. 211',
        'city' => 'KHARTOUM',
        'postcode' => '11111',
        'country_code' => 'SD', ], "B.P. 211\n11111\nKHARTOUM\nSudan"],

    // Sweden
    [SwedenAddressFormatter::class, ['line1' => 'BOX 222',
        'city' => 'STOCKHOLM',
        'postcode' => '111 81',
        'country_code' => 'SE', ], "BOX 222\n111 81 STOCKHOLM\nSweden"],

    // Syria
    [SyriaAddressFormatter::class, ['line1' => 'Rue Youssef Al Azamah, no 25',
        'city' => 'DAMASCUS',
        'postcode' => '0100',
        'country_code' => 'SY', ], "Rue Youssef Al Azamah, no 25\nDAMASCUS\n0100\nSyria"],

    // Tanzania
    [TanzaniaAddressFormatter::class, ['line1' => '22 Ally Hassan Mwinyi',
        'city' => 'MSASANI',
        'state' => 'DAR ES SALAM',
        'postcode' => '14111',
        'country_code' => 'TZ', ], "22 Ally Hassan Mwinyi\n14111 MSASANI\nDAR ES SALAM\nTanzania"],

    // TimorLeste
    [TimorLesteAddressFormatter::class, ['line1' => 'Avenida Presidente Nicolau Lobato',
        'city' => 'DILI',
        'state' => 'DILI',
        'postcode' => 'TL10901',
        'country_code' => 'TL', ], "Avenida Presidente Nicolau Lobato\nDILI TL10901\nTimor-Leste"],

    // Tonga
    [TongaAddressFormatter::class, ['line1' => 'PO Box 1',
        'city' => 'Nuku’alofa',
        'postcode' => '99999',
        'country_code' => 'TO', ], "PO Box 1\nNuku’alofa\n99999\nTonga"],

    // Tunisia
    [TunisiaAddressFormatter::class, ['line1' => '15 AVENUE BOURGUIBA',
        'city' => 'TUNIS',
        'state' => 'Tunis',
        'postcode' => '1002',
        'country_code' => 'TN', ], "15 AVENUE BOURGUIBA\n1002 TUNIS\nTunisia"],

    // TurksAndCaicos
    [TurksAndCaicosAddressFormatter::class, ['line1' => 'George Brown Post Office',
        'line2' => 'Airport road',
        'city' => 'DOWNTOWN, PROVIDENCIALES',
        'postcode' => 'TKCA 1ZZ',
        'country_code' => 'TC', ], "George Brown Post Office\nAirport road\nDOWNTOWN, PROVIDENCIALES\nTKCA 1ZZ\nTurks and Caicos Islands"],

    // USMinorOutlyingIslands
    [USMinorOutlyingIslandsAddressFormatter::class, ['line1' => 'Wake Island Airfield',
        'city' => 'Wake Island',
        'country_code' => 'UM', ], "Wake Island Airfield\nWake Island\nUnited States Minor Outlying Islands"],

    // Uganda
    [UgandaAddressFormatter::class, ['line1' => '22 Siad Barre Avenue',
        'city' => 'KAMPALA',
        'postcode' => '10000',
        'country_code' => 'UG', ], "22 Siad Barre Avenue\n10000 KAMPALA\nUganda"],

    // UnitedArabEmirates
    [UnitedArabEmiratesAddressFormatter::class, ['line1' => 'PO BOX 111',
        'state' => 'Dubai',
        'country_code' => 'AE', ], "PO BOX 111\nDubai\nUnited Arab Emirates"],

    // Uruguay
    [UruguayAddressFormatter::class, ['line1' => 'Chaná 1215, apto. 152',
        'city' => 'ROSARIO',
        'state' => 'COLONIA',
        'postcode' => '70200',
        'country_code' => 'UY', ], "Chaná 1215, apto. 152\n70200 – ROSARIO\nCOLONIA\nUruguay"],

    // Vanuatu
    [VanuatuAddressFormatter::class, ['line1' => 'PO Box 1',
        'city' => 'Port Vila',
        'postcode' => '99999',
        'country_code' => 'VU', ], "PO Box 1\nPort Vila\n99999\nVanuatu"],

    // WallisAndFutuna
    [WallisAndFutunaAddressFormatter::class, ['line1' => 'Route de Mata-Utu',
        'city' => 'MATA-UTU',
        'postcode' => '98600',
        'country_code' => 'WF', ], "Route de Mata-Utu\n98600 MATA-UTU\nWallis and Futuna Islands"],

    // Zambia
    [ZambiaAddressFormatter::class, ['line1' => '21 Independence Avenue',
        'city' => 'KITWE',
        'postcode' => '23456',
        'country_code' => 'ZM', ], "21 Independence Avenue\nKITWE 23456\nZambia"],
]);

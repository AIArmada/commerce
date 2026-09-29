<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Afghanistan\AfghanistanAddressFormatter;
use AIArmada\Addressing\Geography\Albania\AlbaniaAddressFormatter;
use AIArmada\Addressing\Geography\Andorra\AndorraAddressFormatter;
use AIArmada\Addressing\Geography\Anguilla\AnguillaAddressFormatter;
use AIArmada\Addressing\Geography\Armenia\ArmeniaAddressFormatter;
use AIArmada\Addressing\Geography\Australia\AustraliaAddressFormatter;
use AIArmada\Addressing\Geography\Azerbaijan\AzerbaijanAddressFormatter;
use AIArmada\Addressing\Geography\Bangladesh\BangladeshAddressFormatter;
use AIArmada\Addressing\Geography\Belarus\BelarusAddressFormatter;
use AIArmada\Addressing\Geography\Belize\BelizeAddressFormatter;
use AIArmada\Addressing\Geography\Bermuda\BermudaAddressFormatter;
use AIArmada\Addressing\Geography\Bolivia\BoliviaAddressFormatter;
use AIArmada\Addressing\Geography\Botswana\BotswanaAddressFormatter;
use AIArmada\Addressing\Geography\Bulgaria\BulgariaAddressFormatter;
use AIArmada\Addressing\Geography\Burundi\BurundiAddressFormatter;
use AIArmada\Addressing\Geography\Canada\CanadaAddressFormatter;
use AIArmada\Addressing\Geography\CaribbeanNetherlands\CaribbeanNetherlandsAddressFormatter;
use AIArmada\Addressing\Geography\CentralAfricanRepublic\CentralAfricanRepublicAddressFormatter;
use AIArmada\Addressing\Geography\Chile\ChileAddressFormatter;
use AIArmada\Addressing\Geography\Comoros\ComorosAddressFormatter;
use AIArmada\Addressing\Geography\CostaRica\CostaRicaAddressFormatter;
use AIArmada\Addressing\Geography\Cuba\CubaAddressFormatter;
use AIArmada\Addressing\Geography\CzechRepublic\CzechRepublicAddressFormatter;
use AIArmada\Addressing\Geography\Denmark\DenmarkAddressFormatter;
use AIArmada\Addressing\Geography\Dominica\DominicaAddressFormatter;
use AIArmada\Addressing\Geography\Ecuador\EcuadorAddressFormatter;
use AIArmada\Addressing\Geography\EquatorialGuinea\EquatorialGuineaAddressFormatter;
use AIArmada\Addressing\Geography\Estonia\EstoniaAddressFormatter;
use AIArmada\Addressing\Geography\Ethiopia\EthiopiaAddressFormatter;
use AIArmada\Addressing\Geography\Fiji\FijiAddressFormatter;
use AIArmada\Addressing\Geography\FrenchGuiana\FrenchGuianaAddressFormatter;
use AIArmada\Addressing\Geography\FrenchSouthernTerritories\FrenchSouthernTerritoriesAddressFormatter;
use AIArmada\Addressing\Geography\Gambia\GambiaAddressFormatter;
use AIArmada\Addressing\Geography\Germany\GermanyAddressFormatter;
use AIArmada\Addressing\Geography\Greenland\GreenlandAddressFormatter;
use AIArmada\Addressing\Geography\Guadeloupe\GuadeloupeAddressFormatter;
use AIArmada\Addressing\Geography\Guatemala\GuatemalaAddressFormatter;
use AIArmada\Addressing\Geography\GuineaBissau\GuineaBissauAddressFormatter;
use AIArmada\Addressing\Geography\Guyana\GuyanaAddressFormatter;
use AIArmada\Addressing\Geography\Honduras\HondurasAddressFormatter;
use AIArmada\Addressing\Geography\Hungary\HungaryAddressFormatter;
use AIArmada\Addressing\Geography\India\IndiaAddressFormatter;
use AIArmada\Addressing\Geography\Ireland\IrelandAddressFormatter;
use AIArmada\Addressing\Geography\Italy\ItalyAddressFormatter;
use AIArmada\Addressing\Geography\Jamaica\JamaicaAddressFormatter;
use AIArmada\Addressing\Geography\Jersey\JerseyAddressFormatter;
use AIArmada\Addressing\Geography\Kenya\KenyaAddressFormatter;
use AIArmada\Addressing\Geography\Kosovo\KosovoAddressFormatter;
use AIArmada\Addressing\Geography\Laos\LaosAddressFormatter;
use AIArmada\Addressing\Geography\Lebanon\LebanonAddressFormatter;
use AIArmada\Addressing\Geography\Liberia\LiberiaAddressFormatter;
use AIArmada\Addressing\Geography\Liechtenstein\LiechtensteinAddressFormatter;
use AIArmada\Addressing\Geography\Luxembourg\LuxembourgAddressFormatter;
use AIArmada\Addressing\Geography\Malawi\MalawiAddressFormatter;
use AIArmada\Addressing\Geography\Mali\MaliAddressFormatter;
use AIArmada\Addressing\Geography\MarshallIslands\MarshallIslandsAddressFormatter;
use AIArmada\Addressing\Geography\Mauritania\MauritaniaAddressFormatter;
use AIArmada\Addressing\Geography\Mayotte\MayotteAddressFormatter;
use AIArmada\Addressing\Geography\Moldova\MoldovaAddressFormatter;
use AIArmada\Addressing\Geography\Mongolia\MongoliaAddressFormatter;
use AIArmada\Addressing\Geography\Montserrat\MontserratAddressFormatter;
use AIArmada\Addressing\Geography\Myanmar\MyanmarAddressFormatter;
use AIArmada\Addressing\Geography\Nauru\NauruAddressFormatter;
use AIArmada\Addressing\Geography\NewCaledonia\NewCaledoniaAddressFormatter;
use AIArmada\Addressing\Geography\Nicaragua\NicaraguaAddressFormatter;
use AIArmada\Addressing\Geography\Nigeria\NigeriaAddressFormatter;
use AIArmada\Addressing\Geography\NorthKorea\NorthKoreaAddressFormatter;
use AIArmada\Addressing\Geography\Norway\NorwayAddressFormatter;
use AIArmada\Addressing\Geography\Palau\PalauAddressFormatter;
use AIArmada\Addressing\Geography\Panama\PanamaAddressFormatter;
use AIArmada\Addressing\Geography\Paraguay\ParaguayAddressFormatter;
use AIArmada\Addressing\Geography\Philippines\PhilippinesAddressFormatter;
use AIArmada\Addressing\Geography\Portugal\PortugalAddressFormatter;
use AIArmada\Addressing\Geography\Qatar\QatarAddressFormatter;
use AIArmada\Addressing\Geography\Romania\RomaniaAddressFormatter;
use AIArmada\Addressing\Geography\SaintBarthelemy\SaintBarthelemyAddressFormatter;
use AIArmada\Addressing\Geography\SaintKittsAndNevis\SaintKittsAndNevisAddressFormatter;
use AIArmada\Addressing\Geography\SaintMartin\SaintMartinAddressFormatter;
use AIArmada\Addressing\Geography\SaintVincentAndTheGrenadines\SaintVincentAndTheGrenadinesAddressFormatter;
use AIArmada\Addressing\Geography\SanMarino\SanMarinoAddressFormatter;
use AIArmada\Addressing\Geography\SaudiArabia\SaudiArabiaAddressFormatter;
use AIArmada\Addressing\Geography\Serbia\SerbiaAddressFormatter;
use AIArmada\Addressing\Geography\SierraLeone\SierraLeoneAddressFormatter;
use AIArmada\Addressing\Geography\Slovenia\SloveniaAddressFormatter;
use AIArmada\Addressing\Geography\Somalia\SomaliaAddressFormatter;
use AIArmada\Addressing\Geography\SouthKorea\SouthKoreaAddressFormatter;
use AIArmada\Addressing\Geography\SouthSudan\SouthSudanAddressFormatter;
use AIArmada\Addressing\Geography\SriLanka\SriLankaAddressFormatter;
use AIArmada\Addressing\Geography\Sweden\SwedenAddressFormatter;
use AIArmada\Addressing\Geography\Syria\SyriaAddressFormatter;
use AIArmada\Addressing\Geography\Tajikistan\TajikistanAddressFormatter;
use AIArmada\Addressing\Geography\TimorLeste\TimorLesteAddressFormatter;
use AIArmada\Addressing\Geography\Tonga\TongaAddressFormatter;
use AIArmada\Addressing\Geography\Tunisia\TunisiaAddressFormatter;
use AIArmada\Addressing\Geography\Turkmenistan\TurkmenistanAddressFormatter;
use AIArmada\Addressing\Geography\Tuvalu\TuvaluAddressFormatter;
use AIArmada\Addressing\Geography\UnitedArabEmirates\UnitedArabEmiratesAddressFormatter;
use AIArmada\Addressing\Geography\UnitedStates\UnitedStatesAddressFormatter;
use AIArmada\Addressing\Geography\USVirginIslands\USVirginIslandsAddressFormatter;
use AIArmada\Addressing\Geography\Vanuatu\VanuatuAddressFormatter;
use AIArmada\Addressing\Geography\Vietnam\VietnamAddressFormatter;
use AIArmada\Addressing\Geography\Yemen\YemenAddressFormatter;
use AIArmada\Addressing\Geography\Zimbabwe\ZimbabweAddressFormatter;

/**
 * Every country's address formatter, one dataset row each (shard 0 of 4).
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
    // Afghanistan
    [AfghanistanAddressFormatter::class, ['line1' => 'House No 123, Street 5',
        'city' => 'HESARAK',
        'state' => 'NANGARHAR',
        'postcode' => '265101',
        'country_code' => 'AF', ], "House No 123, Street 5\nHESARAK\n265101 NANGARHAR\nAfghanistan"],

    // Albania
    [AlbaniaAddressFormatter::class, ['line1' => 'Ruga Myslym Shyri',
        'city' => 'Tirana',
        'state' => 'Tirana',
        'postcode' => '1001',
        'country_code' => 'AL', ], "Ruga Myslym Shyri\n1001\nTirana\nAlbania"],

    // Andorra
    [AndorraAddressFormatter::class, ['line1' => '12 AVINGUDA TORRENT PREGO',
        'line2' => 'BP 15',
        'city' => 'ANDORRA LA VELLA',
        'postcode' => 'AD501',
        'country_code' => 'AD', ], "12 AVINGUDA TORRENT PREGO\nBP 15\nAD501 ANDORRA LA VELLA\nAndorra"],

    // Anguilla
    [AnguillaAddressFormatter::class, ['line1' => 'P.O. Box 60',
        'city' => 'The Valley',
        'country_code' => 'AI', ], "P.O. Box 60\nThe Valley\nAnguilla"],

    // Armenia
    [ArmeniaAddressFormatter::class, ['line1' => 'Saryan str 22 apt 25',
        'city' => 'YEREVAN',
        'postcode' => '0002',
        'country_code' => 'AM', ], "Saryan str 22 apt 25\n0002 YEREVAN\nArmenia"],

    // Australia
    [AustraliaAddressFormatter::class, ['line1' => '113 BOND ST',
        'city' => 'MELBOURNE',
        'state' => 'Victoria',
        'postcode' => '3000',
        'country_code' => 'AU', ], "113 BOND ST\nMELBOURNE  VIC  3000\nAustralia"],

    // Azerbaijan
    [AzerbaijanAddressFormatter::class, ['line1' => 'H. Aliyev küç. 3',
        'city' => 'Nehrəm',
        'state' => 'Nakhchivan',
        'postcode' => 'AZ6715',
        'country_code' => 'AZ', ], "H. Aliyev küç. 3\nAZ6715 Nehrəm\nNakhchivan\nAzerbaijan"],

    // Bangladesh
    [BangladeshAddressFormatter::class, ['line1' => 'Vil Genda',
        'components' => ['thana' => 'Savar'],
        'city' => 'DHAKA',
        'postcode' => '1340',
        'country_code' => 'BD', ], "Vil Genda\nSavar\nDHAKA - 1340\nBangladesh"],

    // Belarus
    [BelarusAddressFormatter::class, ['line1' => 'ul. Lenina, d.3',
        'city' => 'Brest',
        'state' => 'Brest',
        'postcode' => '224000',
        'country_code' => 'BY', ], "ul. Lenina, d.3\n224000, Brest\nBelarus"],

    // Belize
    [BelizeAddressFormatter::class, ['line1' => 'C Street  Apt 2',
        'city' => 'BELIZE CITY',
        'postcode' => '99999',
        'country_code' => 'BZ', ], "C Street  Apt 2\nBELIZE CITY\n99999\nBelize"],

    // Bermuda
    [BermudaAddressFormatter::class, ['line1' => 'PO Box HM 2469',
        'city' => 'HAMILTON',
        'postcode' => 'HM GX',
        'country_code' => 'BM', ], "PO Box HM 2469\nHAMILTON HM GX\nBermuda"],

    // Bolivia
    [BoliviaAddressFormatter::class, ['line1' => 'Casilla Postal 1234',
        'city' => 'LA PAZ',
        'country_code' => 'BO', ], "Casilla Postal 1234\nLA PAZ\nBolivia"],

    // Botswana
    [BotswanaAddressFormatter::class, ['line1' => 'P/Bag 1061',
        'city' => 'GABORONE',
        'country_code' => 'BW', ], "P/Bag 1061\nGABORONE\nBotswana"],

    // Bulgaria
    [BulgariaAddressFormatter::class, ['line1' => 'ul. Trifon Georgiev 1',
        'city' => 'Mechka',
        'state' => 'Ruse',
        'postcode' => '3264',
        'country_code' => 'BG', ], "ul. Trifon Georgiev 1\n3264 Mechka\nRuse\nBulgaria"],

    // Burundi
    [BurundiAddressFormatter::class, ['line1' => 'BP 1915',
        'city' => 'MUKAZA',
        'postcode' => '99999',
        'country_code' => 'BI', ], "BP 1915\nMUKAZA\n99999\nBurundi"],

    // Canada
    [CanadaAddressFormatter::class, ['line1' => '8450 Newman Blvd.',
        'city' => 'MONTREAL',
        'state' => 'Quebec',
        'postcode' => 'h3z 2y7',
        'country_code' => 'CA', ], "8450 Newman Blvd.\nMONTREAL QC H3Z 2Y7\nCanada"],

    // CaribbeanNetherlands
    [CaribbeanNetherlandsAddressFormatter::class, ['line1' => 'Kaya Grandi 5',
        'city' => 'KRALENDIJK',
        'postcode' => '0000 AA',
        'country_code' => 'BQ', ], "Kaya Grandi 5\nKRALENDIJK\n0000 AA\nBonaire, Sint Eustatius and Saba"],

    // CentralAfricanRepublic
    [CentralAfricanRepublicAddressFormatter::class, ['line1' => 'BP 729',
        'city' => 'BANGUI',
        'postcode' => '99999',
        'country_code' => 'CF', ], "BP 729\nBANGUI\n99999\nCentral African Republic"],

    // Chile
    [ChileAddressFormatter::class, ['line1' => 'Av. Ossa 10',
        'city' => 'QUILICURA',
        'postcode' => '8720019',
        'country_code' => 'CL', ], "Av. Ossa 10\n8720019 QUILICURA\nChile"],

    // Comoros
    [ComorosAddressFormatter::class, ['line1' => 'BP 350',
        'city' => 'MORONI',
        'country_code' => 'KM', ], "BP 350\nMORONI\nComoros"],

    // CostaRica
    [CostaRicaAddressFormatter::class, ['line1' => 'Ca 15 Av 37 # 55',
        'city' => 'Heredia, San Rafael, San Rafael',
        'postcode' => '40501',
        'country_code' => 'CR', ], "Ca 15 Av 37 # 55\nHeredia, San Rafael, San Rafael\n40501\nCosta Rica"],

    // Cuba
    [CubaAddressFormatter::class, ['line1' => 'Ave Independencia s/n',
        'line2' => '19 de Mayo y Aranguren',
        'line3' => 'Habana 6',
        'city' => 'CIUDAD HABANA',
        'postcode' => 'CP 10600',
        'country_code' => 'CU', ], "Ave Independencia s/n\n19 de Mayo y Aranguren\nHabana 6\nCP 10600 CIUDAD HABANA\nCuba"],

    // CzechRepublic
    [CzechRepublicAddressFormatter::class, ['line1' => 'Hrušovská 455/10',
        'city' => 'Praha 102',
        'postcode' => '102 00',
        'country_code' => 'CZ', ], "Hrušovská 455/10\n102 00 Praha 102\nCzech Republic"],

    // Denmark
    [DenmarkAddressFormatter::class, ['line1' => 'Postboks 321',
        'city' => 'SKANDERBORG',
        'postcode' => '8660',
        'country_code' => 'DK', ], "Postboks 321\n8660 SKANDERBORG\nDenmark"],

    // Dominica
    [DominicaAddressFormatter::class, ['line1' => 'Bay Front',
        'city' => 'ROSEAU',
        'postcode' => '99999',
        'country_code' => 'DM', ], "Bay Front\nROSEAU\n99999\nDominica"],

    // Ecuador
    [EcuadorAddressFormatter::class, ['line1' => 'Av. 9 de Octubre 100',
        'city' => 'GUAYAQUIL',
        'postcode' => '090306',
        'country_code' => 'EC', ], "Av. 9 de Octubre 100\n090306 - GUAYAQUIL\nEcuador"],

    // EquatorialGuinea
    [EquatorialGuineaAddressFormatter::class, ['line1' => 'Viviendas sociales vicatana',
        'line2' => 'Portal 5, puerta 29',
        'city' => 'MALABO',
        'state' => 'Bioko Norte',
        'country_code' => 'GQ', ], "Viviendas sociales vicatana\nPortal 5, puerta 29\nMALABO\nBioko Norte\nEquatorial Guinea"],

    // Estonia
    [EstoniaAddressFormatter::class, ['line1' => 'Astri 6–1',
        'city' => 'TALLINN',
        'postcode' => '11212',
        'country_code' => 'EE', ], "Astri 6–1\n11212 TALLINN\nEstonia"],

    // Ethiopia
    [EthiopiaAddressFormatter::class, ['line1' => 'P.O. Box 1519',
        'city' => 'ADDIS ABABA',
        'postcode' => '1000',
        'country_code' => 'ET', ], "P.O. Box 1519\n1000 ADDIS ABABA\nEthiopia"],

    // Fiji
    [FijiAddressFormatter::class, ['line1' => 'PO Box 123',
        'city' => 'SUVA',
        'postcode' => '9999',
        'country_code' => 'FJ', ], "PO Box 123\nSUVA\n9999\nFiji Islands"],

    // FrenchGuiana
    [FrenchGuianaAddressFormatter::class, ['line1' => '3 AVENUE HENRI AGARANDE',
        'city' => 'CAYENNE',
        'postcode' => '97300',
        'country_code' => 'GF', ], "3 AVENUE HENRI AGARANDE\n97300 CAYENNE\nFrench Guiana"],

    // FrenchSouthernTerritories
    [FrenchSouthernTerritoriesAddressFormatter::class, ['line1' => 'Base Alfred Faure',
        'city' => 'Port-aux-Français',
        'country_code' => 'TF', ], "Base Alfred Faure\nPort-aux-Français\nFrench Southern Territories"],

    // Gambia
    [GambiaAddressFormatter::class, ['line1' => '21 Liberation Avenue',
        'city' => 'BANJUL',
        'country_code' => 'GM', ], "21 Liberation Avenue\nBANJUL\nThe Gambia"],

    // Germany
    [GermanyAddressFormatter::class, ['line1' => 'Wacholderweg 52a',
        'city' => 'OLDENBURG',
        'postcode' => '26133',
        'country_code' => 'DE', ], "Wacholderweg 52a\n26133 OLDENBURG\nGermany"],

    // Greenland
    [GreenlandAddressFormatter::class, ['line1' => 'Aqqusinersuaq 10',
        'city' => 'Nuuk',
        'postcode' => '3900',
        'country_code' => 'GL', ], "Aqqusinersuaq 10\n3900 Nuuk\nGreenland"],

    // Guadeloupe
    [GuadeloupeAddressFormatter::class, ['line1' => '3 ALLEE DES ACACIAS',
        'city' => 'BASSE TERRE',
        'postcode' => '97100',
        'country_code' => 'GP', ], "3 ALLEE DES ACACIAS\n97100 BASSE TERRE\nGuadeloupe"],

    // Guatemala
    [GuatemalaAddressFormatter::class, ['line1' => '6a. Avenida "A" 10-13, zona 1',
        'line2' => 'Centro Vivo, torre 2, Apartamento 608',
        'city' => 'Guatemala',
        'postcode' => '01001',
        'country_code' => 'GT', ], "6a. Avenida \"A\" 10-13, zona 1\nCentro Vivo, torre 2, Apartamento 608\n01001 - Guatemala\nGuatemala"],

    // GuineaBissau
    [GuineaBissauAddressFormatter::class, ['line1' => 'Rua Justino Lopes 12C',
        'city' => 'BISSAU',
        'postcode' => '1000',
        'country_code' => 'GW', ], "Rua Justino Lopes 12C\n1000 BISSAU\nGuinea-Bissau"],

    // Guyana
    [GuyanaAddressFormatter::class, ['line1' => 'Room 15',
        'line2' => '183/185 Marja Bulding',
        'line3' => 'Lacytown',
        'city' => 'Georgetown',
        'postcode' => '4130106',
        'country_code' => 'GY', ], "Room 15\n183/185 Marja Bulding\nLacytown\nGeorgetown\n4130106\nGuyana"],

    // Honduras
    [HondurasAddressFormatter::class, ['line1' => 'Barrio El Centro, 3era Avenida',
        'city' => 'Tegucigalpa',
        'state' => 'Francisco Morazán',
        'postcode' => '11101',
        'country_code' => 'HN', ], "Barrio El Centro, 3era Avenida\n11101 Tegucigalpa\nFrancisco Morazán\nHonduras"],

    // Hungary
    [HungaryAddressFormatter::class, ['line1' => 'VIRÁG TÉR 3. IV. 61',
        'city' => 'BUDAPEST',
        'postcode' => '1037',
        'country_code' => 'HU', ], "VIRÁG TÉR 3. IV. 61\n1037 BUDAPEST\nHungary"],

    // India
    [IndiaAddressFormatter::class, ['line1' => '4, Amrita Shergill Road',
        'city' => 'New Delhi',
        'state' => 'Delhi',
        'postcode' => '110003',
        'country_code' => 'IN', ], "4, Amrita Shergill Road\nNew Delhi\nDelhi\n110003\nIndia"],

    // Ireland
    [IrelandAddressFormatter::class, ['line1' => '56 Broomfield',
        'city' => 'MACROOM',
        'state' => 'CO. CORK',
        'postcode' => 'T37 F8HK',
        'country_code' => 'IE', ], "56 Broomfield\nMACROOM\nCO. CORK\nT37 F8HK\nIreland"],

    // Italy
    [ItalyAddressFormatter::class, ['line1' => 'VIALE EUROPA 22',
        'components' => ['province_code' => 'rm'],
        'city' => 'ROMA',
        'postcode' => '00122',
        'country_code' => 'IT', ], "VIALE EUROPA 22\n00122 ROMA RM\nItaly"],

    // Jamaica
    [JamaicaAddressFormatter::class, ['line1' => '15 Molynes Road',
        'city' => 'Kingston 10',
        'country_code' => 'JM', ], "15 Molynes Road\nKingston 10\nJamaica"],

    // Jersey
    [JerseyAddressFormatter::class, ['line1' => '5 Esplanade',
        'city' => 'St Helier',
        'postcode' => 'JE1 1AA',
        'country_code' => 'JE', ], "5 Esplanade\nSt Helier\nJE1 1AA\nJersey"],

    // Kenya
    [KenyaAddressFormatter::class, ['line1' => 'P O BOX 2784 – NAKURU GPO',
        'city' => 'NAKURU',
        'state' => 'Nakuru',
        'postcode' => '20100',
        'country_code' => 'KE', ], "P O BOX 2784 – NAKURU GPO\n20100\nNAKURU\nKenya"],

    // Kosovo
    [KosovoAddressFormatter::class, ['line1' => 'Rruga Adem Jashari 1',
        'city' => 'Prizren',
        'state' => 'Prizren',
        'postcode' => '20000',
        'country_code' => 'XK', ], "Rruga Adem Jashari 1\n20000 Prizren\nKosovo"],

    // Laos
    [LaosAddressFormatter::class, ['line1' => '14, rue That Louang',
        'city' => 'XAYSETHA',
        'postcode' => '01160',
        'country_code' => 'LA', ], "14, rue That Louang\n01160 XAYSETHA\nLaos"],

    // Lebanon
    [LebanonAddressFormatter::class, ['line1' => 'Building Al Amal, 2nd floor',
        'line2' => 'Australia Street',
        'city' => 'Raoucheh',
        'state' => 'Beirut',
        'postcode' => '1107 2080',
        'country_code' => 'LB', ], "Building Al Amal, 2nd floor\nAustralia Street\nRaoucheh 1107 2080\nBeirut\nLebanon"],

    // Liberia
    [LiberiaAddressFormatter::class, ['line1' => 'Water Street',
        'city' => 'Buchanan',
        'postcode' => '4000',
        'country_code' => 'LR', ], "Water Street\n4000 Buchanan\nLiberia"],

    // Liechtenstein
    [LiechtensteinAddressFormatter::class, ['line1' => 'Städtle 37',
        'city' => 'Vaduz',
        'postcode' => '9490',
        'country_code' => 'LI', ], "Städtle 37\n9490 Vaduz\nLiechtenstein"],

    // Luxembourg
    [LuxembourgAddressFormatter::class, ['line1' => '71, route de Berlin',
        'city' => 'DUDELANGE',
        'postcode' => 'L-1234',
        'country_code' => 'LU', ], "71, route de Berlin\nL-1234 DUDELANGE\nLuxembourg"],

    // Malawi
    [MalawiAddressFormatter::class, ['line1' => 'Chipembere Highway',
        'city' => 'Blantyre',
        'state' => 'Southern',
        'postcode' => '309070',
        'country_code' => 'MW', ], "Chipembere Highway\n309070 Blantyre\nSouthern\nMalawi"],

    // Mali
    [MaliAddressFormatter::class, ['line1' => 'Rue 10',
        'city' => 'Sikasso',
        'state' => 'Sikasso',
        'country_code' => 'ML', ], "Rue 10\nSikasso\nMali"],

    // MarshallIslands
    [MarshallIslandsAddressFormatter::class, ['line1' => 'P.O. Box 7',
        'city' => 'Ebeye',
        'postcode' => '96970',
        'country_code' => 'MH', ], "P.O. Box 7\nEbeye MH 96970\nMarshall Islands"],

    // Mauritania
    [MauritaniaAddressFormatter::class, ['line1' => 'B.P. 35',
        'city' => 'NOUAKCHOTT',
        'postcode' => '99999',
        'country_code' => 'MR', ], "B.P. 35\nNOUAKCHOTT\n99999\nMauritania"],

    // Mayotte
    [MayotteAddressFormatter::class, ['line1' => 'BP 21',
        'city' => 'CHIRONGUI',
        'postcode' => '97620',
        'country_code' => 'YT', ], "BP 21\n97620 CHIRONGUI\nMayotte"],

    // Moldova
    [MoldovaAddressFormatter::class, ['line1' => 'Str. Eminescu, nr. 25/1, ap. 14',
        'city' => 'CHISINAU',
        'postcode' => 'MD-2012',
        'country_code' => 'MD', ], "Str. Eminescu, nr. 25/1, ap. 14\nMD-2012, CHISINAU\nMoldova"],

    // Mongolia
    [MongoliaAddressFormatter::class, ['line1' => 'Jigjidjav street 9-11 toot',
        'line2' => '15th khoroo, Bayanzurkh Duureg',
        'state' => 'ULAANBAATAR',
        'postcode' => '14560',
        'country_code' => 'MN', ], "Jigjidjav street 9-11 toot\n15th khoroo, Bayanzurkh Duureg\nULAANBAATAR 14560\nMongolia"],

    // Montserrat
    [MontserratAddressFormatter::class, ['line1' => 'PO Box 140',
        'city' => 'Brades',
        'postcode' => 'MSR1110',
        'country_code' => 'MS', ], "PO Box 140\nBrades, MSR1110\nMontserrat"],

    // Myanmar
    [MyanmarAddressFormatter::class, ['line1' => 'No. 7(A) 58 Street, Between 40 x 41',
        'city' => 'Pyigyitagon Township',
        'state' => 'Mandalay',
        'postcode' => '0505001',
        'country_code' => 'MM', ], "No. 7(A) 58 Street, Between 40 x 41\nPyigyitagon Township, 0505001\nMandalay\nMyanmar"],

    // Nauru
    [NauruAddressFormatter::class, ['line1' => 'Civic Centre',
        'city' => 'YAREN DISTRICT',
        'postcode' => 'NRU68',
        'country_code' => 'NR', ], "Civic Centre\nYAREN DISTRICT\nNRU68\nNauru"],

    // NewCaledonia
    [NewCaledoniaAddressFormatter::class, ['line1' => '24 RUE DES PALMIERS',
        'city' => 'NOUMEA',
        'postcode' => '98800',
        'country_code' => 'NC', ], "24 RUE DES PALMIERS\n98800 NOUMEA\nNew Caledonia"],

    // Nicaragua
    [NicaraguaAddressFormatter::class, ['line1' => 'Portón Cementerio General 1c Este, 1/2c Norte. Barrio Santa Ana Sur.',
        'city' => 'Managua',
        'state' => 'Managua',
        'postcode' => '12005',
        'country_code' => 'NI', ], "Portón Cementerio General 1c Este, 1/2c Norte. Barrio Santa Ana Sur.\n12005\nManagua\nNicaragua"],

    // Nigeria
    [NigeriaAddressFormatter::class, ['line1' => '34 Alayande Cl',
        'city' => 'Mokola',
        'state' => 'OYO STATE',
        'postcode' => '200212',
        'country_code' => 'NG', ], "34 Alayande Cl\nMokola 200212\nOYO STATE\nNigeria"],

    // NorthKorea
    [NorthKoreaAddressFormatter::class, ['line1' => 'Quartier Bottongang',
        'city' => 'PYONGYANG',
        'postcode' => '999999',
        'country_code' => 'KP', ], "Quartier Bottongang\nPYONGYANG\n999999\nNorth Korea"],

    // Norway
    [NorwayAddressFormatter::class, ['line1' => 'Ølvevegen 44',
        'city' => 'ØLVE',
        'postcode' => '5637',
        'country_code' => 'NO', ], "Ølvevegen 44\n5637 ØLVE\nNorway"],

    // Palau
    [PalauAddressFormatter::class, ['line1' => 'PO Box 7',
        'city' => 'Koror',
        'postcode' => '96940-0100',
        'country_code' => 'PW', ], "PO Box 7\nKoror PW 96940-0100\nPalau"],

    // Panama
    [PanamaAddressFormatter::class, ['line1' => 'APARTADO POSTAL 0832-02345',
        'city' => 'PARQUE LEFEVRE',
        'state' => 'PROVINCIA DE PANAMÁ',
        'country_code' => 'PA', ], "APARTADO POSTAL 0832-02345\nPARQUE LEFEVRE\nPROVINCIA DE PANAMÁ\nPanama"],

    // Paraguay
    [ParaguayAddressFormatter::class, ['line1' => 'Ruta 1 km 45',
        'city' => 'ALBERDI',
        'state' => 'ÑEEMBUCU',
        'postcode' => '120203',
        'country_code' => 'PY', ], "Ruta 1 km 45\n120203 ALBERDI\nÑEEMBUCU\nParaguay"],

    // Philippines
    [PhilippinesAddressFormatter::class, ['line1' => '96 Hermogenes St., Sofa Subdivision',
        'state' => 'PAMPANGA',
        'postcode' => '2000',
        'country_code' => 'PH', ], "96 Hermogenes St., Sofa Subdivision\n2000 PAMPANGA\nPhilippines"],

    // Portugal
    [PortugalAddressFormatter::class, ['line1' => 'R. LEAL DA CÂMARA 31 RC ESQ',
        'line2' => 'ALGUEIRÃO',
        'city' => 'MEM MARTINS',
        'postcode' => '2725-079',
        'country_code' => 'PT', ], "R. LEAL DA CÂMARA 31 RC ESQ\nALGUEIRÃO\n2725-079 MEM MARTINS\nPortugal"],

    // Qatar
    [QatarAddressFormatter::class, ['line1' => 'P.O. Box 3263',
        'city' => 'DOHA',
        'country_code' => 'QA', ], "P.O. Box 3263\nDOHA\nQatar"],

    // Romania
    [RomaniaAddressFormatter::class, ['line1' => 'Strada Republicii 10',
        'city' => 'Craiova',
        'state' => 'Dolj',
        'postcode' => '200716',
        'country_code' => 'RO', ], "Strada Republicii 10\n200716 Craiova\nDolj\nRomania"],

    // SaintBarthelemy
    [SaintBarthelemyAddressFormatter::class, ['line1' => '5 RUE VICTOR HUGO',
        'city' => 'SAINT-BARTHELEMY',
        'postcode' => '97133',
        'country_code' => 'BL', ], "5 RUE VICTOR HUGO\n97133 SAINT-BARTHELEMY\nSaint-Barthelemy"],

    // SaintKittsAndNevis
    [SaintKittsAndNevisAddressFormatter::class, ['line1' => '2 Fern Street',
        'line2' => 'Greenlands',
        'city' => 'Basseterre',
        'state' => 'St Kitts',
        'postcode' => 'KN0101',
        'country_code' => 'KN', ], "2 Fern Street\nGreenlands\nBasseterre\nSt Kitts\nKN0101\nSaint Kitts and Nevis"],

    // SaintMartin
    [SaintMartinAddressFormatter::class, ['line1' => '5 RUE DES ACACIAS',
        'city' => 'SAINT-MARTIN',
        'postcode' => '97150',
        'country_code' => 'MF', ], "5 RUE DES ACACIAS\n97150 SAINT-MARTIN\nSaint-Martin (French part)"],

    // SaintVincentAndTheGrenadines
    [SaintVincentAndTheGrenadinesAddressFormatter::class, ['line1' => 'HALIFAX STREET',
        'city' => 'KINGSTOWN',
        'postcode' => 'VC0120',
        'country_code' => 'VC', ], "HALIFAX STREET\nKINGSTOWN\nVC0120\nSaint Vincent and the Grenadines"],

    // SanMarino
    [SanMarinoAddressFormatter::class, ['line1' => 'Contrada Omerelli 17',
        'city' => 'SAN MARINO',
        'postcode' => '47890',
        'country_code' => 'SM', ], "Contrada Omerelli 17\n47890 SAN MARINO\nSan Marino"],

    // SaudiArabia
    [SaudiArabiaAddressFormatter::class, ['line1' => '2929 Rayhanah Bint Zaid',
        'city' => 'RIYADH',
        'postcode' => '13337',
        'country_code' => 'SA', ], "2929 Rayhanah Bint Zaid\n13337\nRIYADH\nSaudi Arabia"],

    // Serbia
    [SerbiaAddressFormatter::class, ['line1' => 'Knez Mihailova 10',
        'city' => 'Belgrade',
        'state' => 'Belgrade',
        'postcode' => '11130',
        'country_code' => 'RS', ], "Knez Mihailova 10\n11130 Belgrade\nSerbia"],

    // SierraLeone
    [SierraLeoneAddressFormatter::class, ['line1' => 'Bojon Street',
        'city' => 'Bo',
        'state' => 'Southern',
        'country_code' => 'SL', ], "Bojon Street\nBo\nSouthern\nSierra Leone"],

    // Slovenia
    [SloveniaAddressFormatter::class, ['line1' => 'Slovenska cesta 1',
        'city' => 'LJUBLJANA',
        'postcode' => 'SI-1000',
        'country_code' => 'SI', ], "Slovenska cesta 1\nSI-1000 LJUBLJANA\nSlovenia"],

    // Somalia
    [SomaliaAddressFormatter::class, ['line1' => 'P.O. Box 1001',
        'city' => 'KISMAYU',
        'postcode' => 'JH 09010',
        'country_code' => 'SO', ], "P.O. Box 1001\nKISMAYU\nJH 09010\nSomalia"],

    // SouthKorea
    [SouthKoreaAddressFormatter::class, ['line1' => '97-1 Toegye-ro',
        'city' => 'Jung-gu',
        'state' => 'Seoul',
        'postcode' => '04547',
        'country_code' => 'KR', ], "97-1 Toegye-ro\nJung-gu\nSeoul 04547\nSouth Korea"],

    // SouthSudan
    [SouthSudanAddressFormatter::class, ['line1' => 'Plot 123, Hai Malakal',
        'city' => 'JUBA',
        'state' => 'Central Equatoria',
        'country_code' => 'SS', ], "Plot 123, Hai Malakal\nJUBA\nCentral Equatoria\nSouth Sudan"],

    // SriLanka
    [SriLankaAddressFormatter::class, ['line1' => '201 Shanti Villa',
        'city' => 'KANDY',
        'state' => 'Central',
        'postcode' => '20000',
        'country_code' => 'LK', ], "201 Shanti Villa\nKANDY\nCentral\n20000\nSri Lanka"],

    // Sweden
    [SwedenAddressFormatter::class, ['line1' => 'NYBY 10',
        'city' => 'LILLBYN',
        'postcode' => '123 45',
        'country_code' => 'SE', ], "NYBY 10\n123 45 LILLBYN\nSweden"],

    // Syria
    [SyriaAddressFormatter::class, ['line1' => 'Rue Youssef Al Azamah, no 25',
        'city' => 'DAMASCUS',
        'state' => 'Damascus',
        'country_code' => 'SY', ], "Rue Youssef Al Azamah, no 25\nDAMASCUS\nSyria"],

    // Tajikistan
    [TajikistanAddressFormatter::class, ['line1' => 'Rudaki Avenue 1',
        'city' => 'DUSHANBE',
        'state' => 'Dushanbe',
        'postcode' => '734012',
        'country_code' => 'TJ', ], "Rudaki Avenue 1\n734012 DUSHANBE\nTajikistan"],

    // TimorLeste
    [TimorLesteAddressFormatter::class, ['line1' => 'TRAVESSA LAVANDARIA NO.12',
        'city' => 'Bairo Pite',
        'state' => 'DILI',
        'postcode' => 'TL11212',
        'country_code' => 'TL', ], "TRAVESSA LAVANDARIA NO.12\nBairo Pite - DILI TL11212\nTimor-Leste"],

    // Tonga
    [TongaAddressFormatter::class, ['line1' => 'PO Box 1',
        'city' => 'Nuku’alofa',
        'country_code' => 'TO', ], "PO Box 1\nNuku’alofa\nTonga"],

    // Tunisia
    [TunisiaAddressFormatter::class, ['line1' => '15 AVENUE BOURGUIBA',
        'city' => 'BOU SALEM',
        'postcode' => '8170',
        'country_code' => 'TN', ], "15 AVENUE BOURGUIBA\n8170 BOU SALEM\nTunisia"],

    // Turkmenistan
    [TurkmenistanAddressFormatter::class, ['line1' => 'Galkynysh Street 1',
        'city' => 'ASHGABAT',
        'state' => 'Ashgabat',
        'postcode' => '744000',
        'country_code' => 'TM', ], "Galkynysh Street 1\nASHGABAT\n744000\nTurkmenistan"],

    // Tuvalu
    [TuvaluAddressFormatter::class, ['line1' => 'PO Box 1',
        'city' => 'Funafuti',
        'postcode' => '99999',
        'country_code' => 'TV', ], "PO Box 1\nFunafuti\n99999\nTuvalu"],

    // USVirginIslands
    [USVirginIslandsAddressFormatter::class, ['line1' => 'RR 1 BOX 6601',
        'city' => 'KINGSHILL',
        'postcode' => '00850-9802',
        'country_code' => 'VI', ], "RR 1 BOX 6601\nKINGSHILL VI 00850-9802\nVirgin Islands (US)"],

    // UnitedArabEmirates
    [UnitedArabEmiratesAddressFormatter::class, ['line1' => 'PO BOX 111',
        'city' => 'Dubai',
        'state' => 'Dubai',
        'country_code' => 'AE', ], "PO BOX 111\nDubai\nUnited Arab Emirates"],

    // UnitedStates
    [UnitedStatesAddressFormatter::class, ['line1' => '123 MAGNOLIA ST',
        'city' => 'HEMPSTEAD',
        'state' => 'New York',
        'postcode' => '11550-1234',
        'country_code' => 'US', ], "123 MAGNOLIA ST\nHEMPSTEAD NY 11550-1234\nUnited States"],

    // Vanuatu
    [VanuatuAddressFormatter::class, ['line1' => 'PO Box 1',
        'city' => 'Port Vila',
        'country_code' => 'VU', ], "PO Box 1\nPort Vila\nVanuatu"],

    // Vietnam
    [VietnamAddressFormatter::class, ['line1' => 'No 5, Pham Hung Road',
        'city' => 'My Dinh 2 Ward',
        'state' => 'HANOI',
        'postcode' => '11517',
        'country_code' => 'VN', ], "No 5, Pham Hung Road\nMy Dinh 2 Ward\nHANOI 11517\nVietnam"],

    // Yemen
    [YemenAddressFormatter::class, ['line1' => 'Al Wahda Street 2',
        'city' => 'Ibb',
        'state' => 'Ibb',
        'country_code' => 'YE', ], "Al Wahda Street 2\nIbb\nYemen"],

    // Zimbabwe
    [ZimbabweAddressFormatter::class, ['line1' => '12 Josiah Tongogara Street',
        'city' => 'Bulawayo',
        'state' => 'Bulawayo',
        'country_code' => 'ZW', ], "12 Josiah Tongogara Street\nBulawayo\nZimbabwe"],
]);

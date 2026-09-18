<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Afghanistan\AfghanistanAddressFormatter;
use AIArmada\Addressing\Geography\Algeria\AlgeriaAddressFormatter;
use AIArmada\Addressing\Geography\Angola\AngolaAddressFormatter;
use AIArmada\Addressing\Geography\Argentina\ArgentinaAddressFormatter;
use AIArmada\Addressing\Geography\Armenia\ArmeniaAddressFormatter;
use AIArmada\Addressing\Geography\Australia\AustraliaAddressFormatter;
use AIArmada\Addressing\Geography\Azerbaijan\AzerbaijanAddressFormatter;
use AIArmada\Addressing\Geography\Bahrain\BahrainAddressFormatter;
use AIArmada\Addressing\Geography\Bangladesh\BangladeshAddressFormatter;
use AIArmada\Addressing\Geography\Benin\BeninAddressFormatter;
use AIArmada\Addressing\Geography\Bhutan\BhutanAddressFormatter;
use AIArmada\Addressing\Geography\Botswana\BotswanaAddressFormatter;
use AIArmada\Addressing\Geography\Brazil\BrazilAddressFormatter;
use AIArmada\Addressing\Geography\BurkinaFaso\BurkinaFasoAddressFormatter;
use AIArmada\Addressing\Geography\Burundi\BurundiAddressFormatter;
use AIArmada\Addressing\Geography\Cambodia\CambodiaAddressFormatter;
use AIArmada\Addressing\Geography\Cameroon\CameroonAddressFormatter;
use AIArmada\Addressing\Geography\Canada\CanadaAddressFormatter;
use AIArmada\Addressing\Geography\CapeVerde\CapeVerdeAddressFormatter;
use AIArmada\Addressing\Geography\CentralAfricanRepublic\CentralAfricanRepublicAddressFormatter;
use AIArmada\Addressing\Geography\Chad\ChadAddressFormatter;
use AIArmada\Addressing\Geography\China\ChinaAddressFormatter;
use AIArmada\Addressing\Geography\Colombia\ColombiaAddressFormatter;
use AIArmada\Addressing\Geography\Comoros\ComorosAddressFormatter;
use AIArmada\Addressing\Geography\Congo\CongoAddressFormatter;
use AIArmada\Addressing\Geography\Cyprus\CyprusAddressFormatter;
use AIArmada\Addressing\Geography\DemocraticRepublicOfCongo\DemocraticRepublicOfCongoAddressFormatter;
use AIArmada\Addressing\Geography\Djibouti\DjiboutiAddressFormatter;
use AIArmada\Addressing\Geography\Egypt\EgyptAddressFormatter;
use AIArmada\Addressing\Geography\EquatorialGuinea\EquatorialGuineaAddressFormatter;
use AIArmada\Addressing\Geography\Eritrea\EritreaAddressFormatter;
use AIArmada\Addressing\Geography\Eswatini\EswatiniAddressFormatter;
use AIArmada\Addressing\Geography\Ethiopia\EthiopiaAddressFormatter;
use AIArmada\Addressing\Geography\France\FranceAddressFormatter;
use AIArmada\Addressing\Geography\Gabon\GabonAddressFormatter;
use AIArmada\Addressing\Geography\Gambia\GambiaAddressFormatter;
use AIArmada\Addressing\Geography\Georgia\GeorgiaAddressFormatter;
use AIArmada\Addressing\Geography\Germany\GermanyAddressFormatter;
use AIArmada\Addressing\Geography\Ghana\GhanaAddressFormatter;
use AIArmada\Addressing\Geography\Guinea\GuineaAddressFormatter;
use AIArmada\Addressing\Geography\GuineaBissau\GuineaBissauAddressFormatter;
use AIArmada\Addressing\Geography\HongKong\HongKongAddressFormatter;
use AIArmada\Addressing\Geography\India\IndiaAddressFormatter;
use AIArmada\Addressing\Geography\Iran\IranAddressFormatter;
use AIArmada\Addressing\Geography\Iraq\IraqAddressFormatter;
use AIArmada\Addressing\Geography\Israel\IsraelAddressFormatter;
use AIArmada\Addressing\Geography\Italy\ItalyAddressFormatter;
use AIArmada\Addressing\Geography\IvoryCoast\IvoryCoastAddressFormatter;
use AIArmada\Addressing\Geography\Japan\JapanAddressFormatter;
use AIArmada\Addressing\Geography\Jordan\JordanAddressFormatter;
use AIArmada\Addressing\Geography\Kazakhstan\KazakhstanAddressFormatter;
use AIArmada\Addressing\Geography\Kenya\KenyaAddressFormatter;
use AIArmada\Addressing\Geography\Kuwait\KuwaitAddressFormatter;
use AIArmada\Addressing\Geography\Kyrgyzstan\KyrgyzstanAddressFormatter;
use AIArmada\Addressing\Geography\Laos\LaosAddressFormatter;
use AIArmada\Addressing\Geography\Lebanon\LebanonAddressFormatter;
use AIArmada\Addressing\Geography\Lesotho\LesothoAddressFormatter;
use AIArmada\Addressing\Geography\Liberia\LiberiaAddressFormatter;
use AIArmada\Addressing\Geography\Libya\LibyaAddressFormatter;
use AIArmada\Addressing\Geography\Madagascar\MadagascarAddressFormatter;
use AIArmada\Addressing\Geography\Malawi\MalawiAddressFormatter;
use AIArmada\Addressing\Geography\Maldives\MaldivesAddressFormatter;
use AIArmada\Addressing\Geography\Mali\MaliAddressFormatter;
use AIArmada\Addressing\Geography\Mauritania\MauritaniaAddressFormatter;
use AIArmada\Addressing\Geography\Mauritius\MauritiusAddressFormatter;
use AIArmada\Addressing\Geography\Mexico\MexicoAddressFormatter;
use AIArmada\Addressing\Geography\Mongolia\MongoliaAddressFormatter;
use AIArmada\Addressing\Geography\Morocco\MoroccoAddressFormatter;
use AIArmada\Addressing\Geography\Mozambique\MozambiqueAddressFormatter;
use AIArmada\Addressing\Geography\Myanmar\MyanmarAddressFormatter;
use AIArmada\Addressing\Geography\Namibia\NamibiaAddressFormatter;
use AIArmada\Addressing\Geography\Nepal\NepalAddressFormatter;
use AIArmada\Addressing\Geography\Netherlands\NetherlandsAddressFormatter;
use AIArmada\Addressing\Geography\Niger\NigerAddressFormatter;
use AIArmada\Addressing\Geography\Nigeria\NigeriaAddressFormatter;
use AIArmada\Addressing\Geography\NorthKorea\NorthKoreaAddressFormatter;
use AIArmada\Addressing\Geography\Oman\OmanAddressFormatter;
use AIArmada\Addressing\Geography\Pakistan\PakistanAddressFormatter;
use AIArmada\Addressing\Geography\Palestine\PalestineAddressFormatter;
use AIArmada\Addressing\Geography\Peru\PeruAddressFormatter;
use AIArmada\Addressing\Geography\Philippines\PhilippinesAddressFormatter;
use AIArmada\Addressing\Geography\Poland\PolandAddressFormatter;
use AIArmada\Addressing\Geography\Qatar\QatarAddressFormatter;
use AIArmada\Addressing\Geography\Russia\RussiaAddressFormatter;
use AIArmada\Addressing\Geography\Rwanda\RwandaAddressFormatter;
use AIArmada\Addressing\Geography\SaoTomeAndPrincipe\SaoTomeAndPrincipeAddressFormatter;
use AIArmada\Addressing\Geography\SaudiArabia\SaudiArabiaAddressFormatter;
use AIArmada\Addressing\Geography\Senegal\SenegalAddressFormatter;
use AIArmada\Addressing\Geography\Seychelles\SeychellesAddressFormatter;
use AIArmada\Addressing\Geography\SierraLeone\SierraLeoneAddressFormatter;
use AIArmada\Addressing\Geography\Somalia\SomaliaAddressFormatter;
use AIArmada\Addressing\Geography\SouthAfrica\SouthAfricaAddressFormatter;
use AIArmada\Addressing\Geography\SouthKorea\SouthKoreaAddressFormatter;
use AIArmada\Addressing\Geography\SouthSudan\SouthSudanAddressFormatter;
use AIArmada\Addressing\Geography\Spain\SpainAddressFormatter;
use AIArmada\Addressing\Geography\SriLanka\SriLankaAddressFormatter;
use AIArmada\Addressing\Geography\Sudan\SudanAddressFormatter;
use AIArmada\Addressing\Geography\Syria\SyriaAddressFormatter;
use AIArmada\Addressing\Geography\Taiwan\TaiwanAddressFormatter;
use AIArmada\Addressing\Geography\Tajikistan\TajikistanAddressFormatter;
use AIArmada\Addressing\Geography\Tanzania\TanzaniaAddressFormatter;
use AIArmada\Addressing\Geography\Thailand\ThailandAddressFormatter;
use AIArmada\Addressing\Geography\TimorLeste\TimorLesteAddressFormatter;
use AIArmada\Addressing\Geography\Togo\TogoAddressFormatter;
use AIArmada\Addressing\Geography\Tunisia\TunisiaAddressFormatter;
use AIArmada\Addressing\Geography\Turkiye\TurkiyeAddressFormatter;
use AIArmada\Addressing\Geography\Turkmenistan\TurkmenistanAddressFormatter;
use AIArmada\Addressing\Geography\Uganda\UgandaAddressFormatter;
use AIArmada\Addressing\Geography\Ukraine\UkraineAddressFormatter;
use AIArmada\Addressing\Geography\UnitedArabEmirates\UnitedArabEmiratesAddressFormatter;
use AIArmada\Addressing\Geography\UnitedKingdom\UnitedKingdomAddressFormatter;
use AIArmada\Addressing\Geography\UnitedStates\UnitedStatesAddressFormatter;
use AIArmada\Addressing\Geography\Uzbekistan\UzbekistanAddressFormatter;
use AIArmada\Addressing\Geography\Vietnam\VietnamAddressFormatter;
use AIArmada\Addressing\Geography\Yemen\YemenAddressFormatter;
use AIArmada\Addressing\Geography\Zambia\ZambiaAddressFormatter;
use AIArmada\Addressing\Geography\Zimbabwe\ZimbabweAddressFormatter;

it('formats Pakistani addresses with dash-separated postcodes', function (): void {
    $formatted = app(PakistanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'House No 17-B',
        'city' => 'ISLAMABAD',
        'postcode' => '44000',
        'country_code' => 'PK',
    ]));

    expect($formatted)->toBe("House No 17-B\nISLAMABAD-44000\nPakistan");
});

it('formats Bangladeshi addresses with spaced dash postcodes and thana', function (): void {
    $formatted = app(BangladeshAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Vil Genda',
        'components' => ['thana' => 'Savar'],
        'city' => 'DHAKA',
        'postcode' => '1340',
        'country_code' => 'BD',
    ]));

    expect($formatted)->toBe("Vil Genda\nSavar\nDHAKA - 1340\nBangladesh");
});

it('formats Egyptian addresses with locality, province and postcode lines', function (): void {
    $formatted = app(EgyptAddressFormatter::class)->format(AddressData::from([
        'line1' => '30 Moussa Galal street',
        'city' => 'Al-Mohandessine',
        'state' => 'Giza',
        'postcode' => '3759914',
        'country_code' => 'EG',
    ]));

    expect($formatted)->toBe("30 Moussa Galal street\nAl-Mohandessine\nGiza\n3759914\nEgypt");
});

it('formats Turkish addresses with the postcode left of locality and province', function (): void {
    $formatted = app(TurkiyeAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Doğanbey Mah.',
        'city' => 'ULUS',
        'state' => 'ANKARA',
        'postcode' => '06101',
        'country_code' => 'TR',
    ]));

    expect($formatted)->toBe("Doğanbey Mah.\n06101 ULUS/ANKARA\nTürkiye");
});

it('formats Indian addresses with locality, state and postcode lines', function (): void {
    $formatted = app(IndiaAddressFormatter::class)->format(AddressData::from([
        'line1' => '4, Amrita Shergill Road',
        'city' => 'New Delhi',
        'state' => 'Delhi',
        'postcode' => '110003',
        'country_code' => 'IN',
    ]));

    expect($formatted)->toBe("4, Amrita Shergill Road\nNew Delhi\nDelhi\n110003\nIndia");
});

it('formats Moroccan addresses with the postcode left of the locality', function (): void {
    $formatted = app(MoroccoAddressFormatter::class)->format(AddressData::from([
        'line1' => '23 BOULEVARD TAROUDANT',
        'city' => 'ERRACHIDIA',
        'postcode' => '52000',
        'country_code' => 'MA',
    ]));

    expect($formatted)->toBe("23 BOULEVARD TAROUDANT\n52000 ERRACHIDIA\nMorocco");
});

it('formats Jordanian addresses with the postcode right of the locality', function (): void {
    $formatted = app(JordanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Al Mohazab Al Halabi',
        'city' => 'AMMAN',
        'postcode' => '11937',
        'country_code' => 'JO',
    ]));

    expect($formatted)->toBe("Al Mohazab Al Halabi\nAMMAN 11937\nJordan");
});

it('formats Saudi addresses with the postcode above the locality', function (): void {
    $formatted = app(SaudiArabiaAddressFormatter::class)->format(AddressData::from([
        'line1' => '2929 Rayhanah Bint Zaid',
        'city' => 'RIYADH',
        'postcode' => '13337',
        'country_code' => 'SA',
    ]));

    expect($formatted)->toBe("2929 Rayhanah Bint Zaid\n13337\nRIYADH\nSaudi Arabia");
});

it('formats Emirati addresses without a postcode line', function (): void {
    $formatted = app(UnitedArabEmiratesAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO BOX 111',
        'city' => 'DUBAI',
        'country_code' => 'AE',
    ]));

    expect($formatted)->toBe("PO BOX 111\nDUBAI\nUnited Arab Emirates");
});

it('prints Emirati city-states once when city and emirate match', function (): void {
    $formatted = app(UnitedArabEmiratesAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO BOX 111',
        'city' => 'Dubai',
        'state' => 'Dubai',
        'country_code' => 'AE',
    ]));

    expect($formatted)->toBe("PO BOX 111\nDubai\nUnited Arab Emirates");
});

it('formats Emirati addresses with emirate only', function (): void {
    $formatted = app(UnitedArabEmiratesAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO BOX 111',
        'state' => 'Dubai',
        'country_code' => 'AE',
    ]));

    expect($formatted)->toBe("PO BOX 111\nDubai\nUnited Arab Emirates");
});

it('keeps distinct Emirati city and emirate lines', function (): void {
    $formatted = app(UnitedArabEmiratesAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO BOX 111',
        'city' => 'Al Ain',
        'state' => 'Abu Dhabi',
        'country_code' => 'AE',
    ]));

    expect($formatted)->toBe("PO BOX 111\nAl Ain\nAbu Dhabi\nUnited Arab Emirates");
});

it('formats Qatari addresses without a postcode line', function (): void {
    $formatted = app(QatarAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 3263',
        'city' => 'DOHA',
        'country_code' => 'QA',
    ]));

    expect($formatted)->toBe("P.O. Box 3263\nDOHA\nQatar");
});

it('formats Kuwaiti addresses with the postcode left of the locality', function (): void {
    $formatted = app(KuwaitAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Al-Sabbahiya',
        'city' => 'KUWAIT',
        'postcode' => '54551',
        'country_code' => 'KW',
    ]));

    expect($formatted)->toBe("Al-Sabbahiya\n54551 KUWAIT\nKuwait");
});

it('formats Bahraini addresses with the postcode right of the locality', function (): void {
    $formatted = app(BahrainAddressFormatter::class)->format(AddressData::from([
        'line1' => 'House no. 888',
        'city' => 'AL-MANAMAH',
        'postcode' => '317',
        'country_code' => 'BH',
    ]));

    expect($formatted)->toBe("House no. 888\nAL-MANAMAH 317\nBahrain");
});

it('formats Omani addresses with the postcode above the locality', function (): void {
    $formatted = app(OmanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 15',
        'city' => 'AL-KHOER',
        'postcode' => '133',
        'country_code' => 'OM',
    ]));

    expect($formatted)->toBe("P.O. Box 15\n133\nAL-KHOER\nOman");
});

it('formats British addresses with the post town and uppercased postcode', function (): void {
    $formatted = app(UnitedKingdomAddressFormatter::class)->format(AddressData::from([
        'line1' => '49 Featherstone Street',
        'city' => 'LONDON',
        'state' => 'Greater London',
        'postcode' => 'ec1y 8sy',
        'country_code' => 'GB',
    ]));

    expect($formatted)->toBe("49 Featherstone Street\nLONDON\nEC1Y 8SY\nUnited Kingdom");
});

it('formats South African addresses with the locality and postcode below', function (): void {
    $formatted = app(SouthAfricaAddressFormatter::class)->format(AddressData::from([
        'line1' => '442 Thirteenth Avenue',
        'city' => 'FISH HOEK',
        'state' => 'Western Cape',
        'postcode' => '7975',
        'country_code' => 'ZA',
    ]));

    expect($formatted)->toBe("442 Thirteenth Avenue\nFISH HOEK\n7975\nSouth Africa");
});

it('formats Chinese addresses with the postcode left of the province', function (): void {
    $formatted = app(ChinaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'No.1 Jianguomenwai Avenue',
        'state' => 'BEIJING',
        'postcode' => '100004',
        'country_code' => 'CN',
    ]));

    expect($formatted)->toBe("No.1 Jianguomenwai Avenue\n100004 BEIJING\nChina");
});

it('formats Chinese addresses with the sub-province above the postcode province line', function (): void {
    $formatted = app(ChinaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'No.12 Zhichun Road',
        'city' => 'Haidian District',
        'state' => 'BEIJING',
        'postcode' => '100191',
        'country_code' => 'CN',
    ]));

    expect($formatted)->toBe("No.12 Zhichun Road\nHaidian District\n100191 BEIJING\nChina");
});

it('formats Russian addresses with the postcode below, country last', function (): void {
    $formatted = app(RussiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'ul. Lesnaya d. 5, kv.176',
        'city' => 'MOSKVA',
        'postcode' => '123456',
        'country_code' => 'RU',
    ]));

    expect($formatted)->toBe("ul. Lesnaya d. 5, kv.176\nMOSKVA\n123456\nRussia");
});

it('formats German addresses with the postcode left of the locality', function (): void {
    $formatted = app(GermanyAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Wacholderweg 52a',
        'city' => 'OLDENBURG',
        'postcode' => '26133',
        'country_code' => 'DE',
    ]));

    expect($formatted)->toBe("Wacholderweg 52a\n26133 OLDENBURG\nGermany");
});

it('formats French addresses with the postcode left of the locality', function (): void {
    $formatted = app(FranceAddressFormatter::class)->format(AddressData::from([
        'line1' => '25 RUE DES FLEURS',
        'city' => 'LIBOURNE',
        'postcode' => '33500',
        'country_code' => 'FR',
    ]));

    expect($formatted)->toBe("25 RUE DES FLEURS\n33500 LIBOURNE\nFrance");
});

it('formats Italian addresses with the province abbreviation', function (): void {
    $formatted = app(ItalyAddressFormatter::class)->format(AddressData::from([
        'line1' => 'VIALE EUROPA 22',
        'components' => ['province_code' => 'rm'],
        'city' => 'ROMA',
        'postcode' => '00122',
        'country_code' => 'IT',
    ]));

    expect($formatted)->toBe("VIALE EUROPA 22\n00122 ROMA RM\nItaly");
});

it('formats Japanese addresses with city, prefecture and postcode below', function (): void {
    $formatted = app(JapanAddressFormatter::class)->format(AddressData::from([
        'line1' => '10-23, Mitsugi 1-chome',
        'city' => 'Musashi-Murayama-shi',
        'state' => 'TOKYO',
        'postcode' => '231-0012',
        'country_code' => 'JP',
    ]));

    expect($formatted)->toBe("10-23, Mitsugi 1-chome\nMusashi-Murayama-shi, TOKYO\n231-0012\nJapan");
});

it('formats American addresses with the state abbreviation and ZIP', function (): void {
    $formatted = app(UnitedStatesAddressFormatter::class)->format(AddressData::from([
        'line1' => '123 MAGNOLIA ST',
        'city' => 'HEMPSTEAD',
        'state' => 'New York',
        'postcode' => '11550-1234',
        'country_code' => 'US',
    ]));

    expect($formatted)->toBe("123 MAGNOLIA ST\nHEMPSTEAD NY 11550-1234\nUnited States");
});

it('formats Spanish addresses with the province on its own line', function (): void {
    $formatted = app(SpainAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Calle Huertas 18, 4º, C',
        'city' => 'MARBELLA',
        'state' => 'MÁLAGA',
        'postcode' => '29400',
        'country_code' => 'ES',
    ]));

    expect($formatted)->toBe("Calle Huertas 18, 4º, C\n29400 MARBELLA\nMÁLAGA\nSpain");
});

it('formats Polish addresses with the dashed postcode left of the locality', function (): void {
    $formatted = app(PolandAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Ul. Kręta 15m 10',
        'city' => 'WARSZAWA',
        'postcode' => '00-950',
        'country_code' => 'PL',
    ]));

    expect($formatted)->toBe("Ul. Kręta 15m 10\n00-950 WARSZAWA\nPoland");
});

it('formats Dutch addresses with two spaces after the postcode', function (): void {
    $formatted = app(NetherlandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Drieslag 5-1',
        'city' => 'ARNHEM',
        'postcode' => '6832 am',
        'country_code' => 'NL',
    ]));

    expect($formatted)->toBe("Drieslag 5-1\n6832 AM  ARNHEM\nNetherlands");
});

it('formats Nigerian addresses with the postcode right and state below', function (): void {
    $formatted = app(NigeriaAddressFormatter::class)->format(AddressData::from([
        'line1' => '34 Alayande Cl',
        'city' => 'Mokola',
        'state' => 'OYO STATE',
        'postcode' => '200212',
        'country_code' => 'NG',
    ]));

    expect($formatted)->toBe("34 Alayande Cl\nMokola 200212\nOYO STATE\nNigeria");
});

it('formats Ethiopian addresses with the postcode left of the locality', function (): void {
    $formatted = app(EthiopiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 1519',
        'city' => 'ADDIS ABABA',
        'postcode' => '1000',
        'country_code' => 'ET',
    ]));

    expect($formatted)->toBe("P.O. Box 1519\n1000 ADDIS ABABA\nEthiopia");
});

it('formats Congolese addresses with the postcode left of the province', function (): void {
    $formatted = app(DemocraticRepublicOfCongoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Avenue de la Poste N°1',
        'city' => 'LIMETE',
        'state' => 'KINSHASA',
        'postcode' => '1004131',
        'country_code' => 'CD',
    ]));

    expect($formatted)->toBe("Avenue de la Poste N°1\nLIMETE\n1004131 KINSHASA\nDemocratic Republic of the Congo");
});

it('formats Tanzanian addresses with the region on its own line', function (): void {
    $formatted = app(TanzaniaAddressFormatter::class)->format(AddressData::from([
        'line1' => '22 Ally Hassan Mwinyi',
        'city' => 'MSASANI',
        'state' => 'DAR ES SALAM',
        'postcode' => '14111',
        'country_code' => 'TZ',
    ]));

    expect($formatted)->toBe("22 Ally Hassan Mwinyi\n14111 MSASANI\nDAR ES SALAM\nTanzania");
});

it('formats Kenyan addresses with the postcode below, then town', function (): void {
    $formatted = app(KenyaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P O BOX 2784 – NAKURU GPO',
        'city' => 'NAKURU',
        'state' => 'Nakuru',
        'postcode' => '20100',
        'country_code' => 'KE',
    ]));

    expect($formatted)->toBe("P O BOX 2784 – NAKURU GPO\n20100\nNAKURU\nKenya");
});

it('formats Sudanese addresses with the postcode above the locality', function (): void {
    $formatted = app(SudanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'B.P. 211',
        'city' => 'KHARTOUM',
        'postcode' => '11111',
        'country_code' => 'SD',
    ]));

    expect($formatted)->toBe("B.P. 211\n11111\nKHARTOUM\nSudan");
});

it('formats Ugandan addresses with the postcode left of the locality', function (): void {
    $formatted = app(UgandaAddressFormatter::class)->format(AddressData::from([
        'line1' => '22 Siad Barre Avenue',
        'city' => 'KAMPALA',
        'postcode' => '10000',
        'country_code' => 'UG',
    ]));

    expect($formatted)->toBe("22 Siad Barre Avenue\n10000 KAMPALA\nUganda");
});

it('formats Algerian addresses with the postcode left of the locality', function (): void {
    $formatted = app(AlgeriaAddressFormatter::class)->format(AddressData::from([
        'line1' => "2, rue de l'Indépendance",
        'city' => 'ALGIERS',
        'postcode' => '16027',
        'country_code' => 'DZ',
    ]));

    expect($formatted)->toBe("2, rue de l'Indépendance\n16027 ALGIERS\nAlgeria");
});

it('formats Brazilian addresses with the state abbreviation and postcode below', function (): void {
    $formatted = app(BrazilAddressFormatter::class)->format(AddressData::from([
        'line1' => 'RUA XV DE NOVEMBRO, 1751',
        'city' => 'GUARAPUAVA',
        'state' => 'Paraná',
        'postcode' => '85070-200',
        'country_code' => 'BR',
    ]));

    expect($formatted)->toBe("RUA XV DE NOVEMBRO, 1751\nGUARAPUAVA - PR\n85070-200\nBrazil");
});

it('formats Mexican addresses with the postcode left and abbreviation after', function (): void {
    $formatted = app(MexicoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Insurgentes Sur 1602',
        'city' => 'MEXICO',
        'state' => 'Ciudad de México',
        'postcode' => '02860',
        'country_code' => 'MX',
    ]));

    expect($formatted)->toBe("Insurgentes Sur 1602\n02860 MEXICO, CDMX\nMexico");
});

it('formats Canadian addresses with the province abbreviation and postcode', function (): void {
    $formatted = app(CanadaAddressFormatter::class)->format(AddressData::from([
        'line1' => '8450 Newman Blvd.',
        'city' => 'MONTREAL',
        'state' => 'Quebec',
        'postcode' => 'h3z 2y7',
        'country_code' => 'CA',
    ]));

    expect($formatted)->toBe("8450 Newman Blvd.\nMONTREAL QC H3Z 2Y7\nCanada");
});

it('formats Australian addresses with double-spaced parts', function (): void {
    $formatted = app(AustraliaAddressFormatter::class)->format(AddressData::from([
        'line1' => '113 BOND ST',
        'city' => 'MELBOURNE',
        'state' => 'Victoria',
        'postcode' => '3000',
        'country_code' => 'AU',
    ]));

    expect($formatted)->toBe("113 BOND ST\nMELBOURNE  VIC  3000\nAustralia");
});

it('formats Argentine addresses with the CPA postcode left of the locality', function (): void {
    $formatted = app(ArgentinaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'TUCUMAN 1560',
        'city' => 'VILLA MARIA',
        'postcode' => 'Y5900FNF',
        'country_code' => 'AR',
    ]));

    expect($formatted)->toBe("TUCUMAN 1560\nY5900FNF VILLA MARIA\nArgentina");
});

it('formats Colombian addresses with the postcode right and department below', function (): void {
    $formatted = app(ColombiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'CARRERA 7 NO. 27-18',
        'city' => 'PLANETA RICA',
        'state' => 'CORDOBA',
        'postcode' => '233057',
        'country_code' => 'CO',
    ]));

    expect($formatted)->toBe("CARRERA 7 NO. 27-18\nPLANETA RICA 233057\nCORDOBA\nColombia");
});

it('formats Peruvian addresses with the postcode above the province', function (): void {
    $formatted = app(PeruAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Jr. Jorge Salazar Araoz. N° 171',
        'state' => 'LIMA',
        'postcode' => '15074',
        'country_code' => 'PE',
    ]));

    expect($formatted)->toBe("Jr. Jorge Salazar Araoz. N° 171\n15074\nLIMA\nPeru");
});

it('formats Vietnamese addresses with the postcode right of the province', function (): void {
    $formatted = app(VietnamAddressFormatter::class)->format(AddressData::from([
        'line1' => 'No 5, Pham Hung Road',
        'city' => 'My Dinh 2 Ward',
        'state' => 'HANOI',
        'postcode' => '11517',
        'country_code' => 'VN',
    ]));

    expect($formatted)->toBe("No 5, Pham Hung Road\nMy Dinh 2 Ward\nHANOI 11517\nVietnam");
});

it('formats Thai addresses with district, province and postcode below', function (): void {
    $formatted = app(ThailandAddressFormatter::class)->format(AddressData::from([
        'line1' => '199/63 Moo 1, Tumbol Bangtalad',
        'city' => 'Amphoe Pak Kret',
        'state' => 'Nonthaburi',
        'postcode' => '11120',
        'country_code' => 'TH',
    ]));

    expect($formatted)->toBe("199/63 Moo 1, Tumbol Bangtalad\nAmphoe Pak Kret, Nonthaburi\n11120\nThailand");
});

it('formats Filipino addresses with the postcode left of the locality', function (): void {
    $formatted = app(PhilippinesAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rm 602 FUBC Bldg, Escolta',
        'city' => 'MANILA',
        'postcode' => '1008',
        'country_code' => 'PH',
    ]));

    expect($formatted)->toBe("Rm 602 FUBC Bldg, Escolta\n1008 MANILA\nPhilippines");
});

it('formats Filipino provincial addresses with the postcode on the province line', function (): void {
    $formatted = app(PhilippinesAddressFormatter::class)->format(AddressData::from([
        'line1' => '96 Hermogenes St., Sofa Subdivision',
        'city' => 'San Fernando',
        'state' => 'PAMPANGA',
        'postcode' => '2000',
        'country_code' => 'PH',
    ]));

    expect($formatted)->toBe("96 Hermogenes St., Sofa Subdivision\nSan Fernando\n2000 PAMPANGA\nPhilippines");
});

it('formats Filipino provincial addresses with province only', function (): void {
    $formatted = app(PhilippinesAddressFormatter::class)->format(AddressData::from([
        'line1' => '96 Hermogenes St., Sofa Subdivision',
        'state' => 'PAMPANGA',
        'postcode' => '2000',
        'country_code' => 'PH',
    ]));

    expect($formatted)->toBe("96 Hermogenes St., Sofa Subdivision\n2000 PAMPANGA\nPhilippines");
});

it('formats Filipino Metro Manila addresses on a single postcode line', function (): void {
    $formatted = app(PhilippinesAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 1121, Araneta Center P.O.',
        'city' => 'Quezon City',
        'state' => 'METRO MANILA',
        'postcode' => '1135',
        'country_code' => 'PH',
    ]));

    expect($formatted)->toBe("P.O. Box 1121, Araneta Center P.O.\n1135 Quezon City, METRO MANILA\nPhilippines");
});

it('prints Filipino city-states once when city and state match', function (): void {
    $formatted = app(PhilippinesAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rm 602 FUBC Bldg, Escolta',
        'city' => 'manila',
        'state' => 'MANILA',
        'postcode' => '1008',
        'country_code' => 'PH',
    ]));

    expect($formatted)->toBe("Rm 602 FUBC Bldg, Escolta\n1008 MANILA\nPhilippines");
});

it('formats South Korean addresses with the postcode right of the city', function (): void {
    $formatted = app(SouthKoreaAddressFormatter::class)->format(AddressData::from([
        'line1' => '97-1 Toegye-ro, Jung-gu',
        'state' => 'DAEGU',
        'postcode' => '42007',
        'country_code' => 'KR',
    ]));

    expect($formatted)->toBe("97-1 Toegye-ro, Jung-gu\nDAEGU 42007\nSouth Korea");
});

it('prints South Korean city-states once when city and state match', function (): void {
    $formatted = app(SouthKoreaAddressFormatter::class)->format(AddressData::from([
        'line1' => '97-1 Toegye-ro, Jung-gu',
        'city' => 'Seoul',
        'state' => 'Seoul',
        'postcode' => '03187',
        'country_code' => 'KR',
    ]));

    expect($formatted)->toBe("97-1 Toegye-ro, Jung-gu\nSeoul 03187\nSouth Korea");
});

it('keeps distinct South Korean district and city lines', function (): void {
    $formatted = app(SouthKoreaAddressFormatter::class)->format(AddressData::from([
        'line1' => '97-1 Toegye-ro',
        'city' => 'Jung-gu',
        'state' => 'Seoul',
        'postcode' => '04547',
        'country_code' => 'KR',
    ]));

    expect($formatted)->toBe("97-1 Toegye-ro\nJung-gu\nSeoul 04547\nSouth Korea");
});

it('keeps distinct South Korean district and city lines without a postcode', function (): void {
    $formatted = app(SouthKoreaAddressFormatter::class)->format(AddressData::from([
        'line1' => '97-1 Toegye-ro',
        'city' => 'Jung-gu',
        'state' => 'Seoul',
        'country_code' => 'KR',
    ]));

    expect($formatted)->toBe("97-1 Toegye-ro\nJung-gu\nSeoul\nSouth Korea");
});

it('formats South Korean addresses with state only and no postcode', function (): void {
    $formatted = app(SouthKoreaAddressFormatter::class)->format(AddressData::from([
        'line1' => '97-1 Toegye-ro, Jung-gu',
        'state' => 'Seoul',
        'country_code' => 'KR',
    ]));

    expect($formatted)->toBe("97-1 Toegye-ro, Jung-gu\nSeoul\nSouth Korea");
});

it('prints South Korean city-states once without a postcode', function (): void {
    $formatted = app(SouthKoreaAddressFormatter::class)->format(AddressData::from([
        'line1' => '97-1 Toegye-ro, Jung-gu',
        'city' => 'Seoul',
        'state' => 'Seoul',
        'country_code' => 'KR',
    ]));

    expect($formatted)->toBe("97-1 Toegye-ro, Jung-gu\nSeoul\nSouth Korea");
});

it('formats Taiwanese addresses with the 3+3 postcode right of the locality', function (): void {
    $formatted = app(TaiwanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'No. 55, Sec. 2, Jinshan S. Rd.',
        'city' => 'Taipei City',
        'postcode' => '106409',
        'country_code' => 'TW',
    ]));

    expect($formatted)->toBe("No. 55, Sec. 2, Jinshan S. Rd.\nTaipei City 106409\nTaiwan");
});

it('formats Ukrainian addresses with locality, oblast and postcode lines', function (): void {
    $formatted = app(UkraineAddressFormatter::class)->format(AddressData::from([
        'line1' => 'vul. Khreshchatyk, 22',
        'city' => 'KYIV',
        'postcode' => '01055',
        'country_code' => 'UA',
    ]));

    expect($formatted)->toBe("vul. Khreshchatyk, 22\nKYIV\n01055\nUkraine");
});

it('formats Iraqi addresses with city, governorate and postcode below', function (): void {
    $formatted = app(IraqAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Hay AL Asmaee, Zukak 2',
        'city' => 'AL ASMAEE',
        'state' => 'AL BASRAH',
        'postcode' => '61002',
        'country_code' => 'IQ',
    ]));

    expect($formatted)->toBe("Hay AL Asmaee, Zukak 2\nAL ASMAEE, AL BASRAH\n61002\nIraq");
});

it('formats Ghanaian addresses with the postcode right and region below', function (): void {
    $formatted = app(GhanaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P. O. BOX GP 224',
        'city' => 'Accra-Central',
        'state' => 'GREATER ACCRA',
        'postcode' => 'GA-183-8164',
        'country_code' => 'GH',
    ]));

    expect($formatted)->toBe("P. O. BOX GP 224\nAccra-Central GA-183-8164\nGREATER ACCRA\nGhana");
});

it('formats Angolan addresses without a postcode line', function (): void {
    $formatted = app(AngolaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rua Ndunduma 51',
        'city' => 'LUANDA',
        'country_code' => 'AO',
    ]));

    expect($formatted)->toBe("Rua Ndunduma 51\nLUANDA\nAngola");
});

it('formats Cameroonian addresses without a postcode line', function (): void {
    $formatted = app(CameroonAddressFormatter::class)->format(AddressData::from([
        'line1' => 'B.P. 8035',
        'city' => 'YAOUNDE',
        'country_code' => 'CM',
    ]));

    expect($formatted)->toBe("B.P. 8035\nYAOUNDE\nCameroon");
});

it('formats Malagasy addresses with the postcode left of the town', function (): void {
    $formatted = app(MadagascarAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Lot II M 85 D Antsahameva',
        'city' => 'TOAMASINA',
        'postcode' => '501',
        'country_code' => 'MG',
    ]));

    expect($formatted)->toBe("Lot II M 85 D Antsahameva\n501 TOAMASINA\nMadagascar");
});

it('formats Afghan addresses with the postcode left and province below', function (): void {
    $formatted = app(AfghanistanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'House No 123, Street 5',
        'city' => 'HESARAK',
        'state' => 'NANGARHAR',
        'postcode' => '265101',
        'country_code' => 'AF',
    ]));

    expect($formatted)->toBe("House No 123, Street 5\n265101 HESARAK\nNANGARHAR\nAfghanistan");
});

it('formats Mozambican addresses with the postcode left and province below', function (): void {
    $formatted = app(MozambiqueAddressFormatter::class)->format(AddressData::from([
        'line1' => 'AV. Julius Nyerere 3412',
        'city' => 'MAPUTO',
        'state' => 'MAPUTO',
        'postcode' => '1100',
        'country_code' => 'MZ',
    ]));

    expect($formatted)->toBe("AV. Julius Nyerere 3412\n1100 MAPUTO\nMAPUTO\nMozambique");
});

it('formats Uzbek addresses with the postcode and comma left of the locality', function (): void {
    $formatted = app(UzbekistanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'pr-t Mustakillik, d. 5, kv. 12',
        'city' => 'g. Tashkent 123',
        'postcode' => '100123',
        'country_code' => 'UZ',
    ]));

    expect($formatted)->toBe("pr-t Mustakillik, d. 5, kv. 12\n100123, g. Tashkent 123\nUzbekistan");
});

it('formats Myanmar addresses with the locality, postcode and region', function (): void {
    $formatted = app(MyanmarAddressFormatter::class)->format(AddressData::from([
        'line1' => 'No. 7(A) 58 Street, Between 40 x 41',
        'city' => 'Pyigyitagon Township',
        'state' => 'Mandalay',
        'postcode' => '0505001',
        'country_code' => 'MM',
    ]));

    expect($formatted)->toBe("No. 7(A) 58 Street, Between 40 x 41\nPyigyitagon Township, 0505001\nMandalay\nMyanmar");
});

it('formats Cambodian addresses with the postcode right of the province', function (): void {
    $formatted = app(CambodiaAddressFormatter::class)->format(AddressData::from([
        'line1' => '100E0 Street 118',
        'state' => 'PHNOM PENH',
        'postcode' => '120209',
        'country_code' => 'KH',
    ]));

    expect($formatted)->toBe("100E0 Street 118\nPHNOM PENH 120209\nCambodia");
});

it('formats Cambodian addresses with the city above the postcode province line', function (): void {
    $formatted = app(CambodiaAddressFormatter::class)->format(AddressData::from([
        'line1' => '100E0 Street 118',
        'city' => 'Krong Siem Reap',
        'state' => 'Siem Reap',
        'postcode' => '17000',
        'country_code' => 'KH',
    ]));

    expect($formatted)->toBe("100E0 Street 118\nKrong Siem Reap\nSiem Reap 17000\nCambodia");
});

it('formats Laotian addresses with the postcode left of the locality', function (): void {
    $formatted = app(LaosAddressFormatter::class)->format(AddressData::from([
        'line1' => '14, rue That Louang',
        'city' => 'XAYSETHA',
        'postcode' => '01160',
        'country_code' => 'LA',
    ]));

    expect($formatted)->toBe("14, rue That Louang\n01160 XAYSETHA\nLaos");
});

it('formats Laotian addresses with the province below the postcode locality line', function (): void {
    $formatted = app(LaosAddressFormatter::class)->format(AddressData::from([
        'line1' => '14, rue That Louang',
        'city' => 'Xaysetha',
        'state' => 'Vientiane',
        'postcode' => '01160',
        'country_code' => 'LA',
    ]));

    expect($formatted)->toBe("14, rue That Louang\n01160 Xaysetha\nVientiane\nLaos");
});

it('formats Timorese addresses with the postcode right of the municipality', function (): void {
    $formatted = app(TimorLesteAddressFormatter::class)->format(AddressData::from([
        'line1' => 'AVENIDA CAPITA SINMAU',
        'state' => 'AINARO',
        'postcode' => 'TL42000',
        'country_code' => 'TL',
    ]));

    expect($formatted)->toBe("AVENIDA CAPITA SINMAU\nAINARO TL42000\nTimor-Leste");
});

it('formats Timorese addresses joining distinct city and municipality', function (): void {
    $formatted = app(TimorLesteAddressFormatter::class)->format(AddressData::from([
        'line1' => 'TRAVESSA LAVANDARIA NO.12',
        'city' => 'Bairo Pite',
        'state' => 'DILI',
        'postcode' => 'TL11212',
        'country_code' => 'TL',
    ]));

    expect($formatted)->toBe("TRAVESSA LAVANDARIA NO.12\nBairo Pite - DILI TL11212\nTimor-Leste");
});

it('prints Timorese city-municipalities once when city and state match', function (): void {
    $formatted = app(TimorLesteAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Avenida Presidente Nicolau Lobato',
        'city' => 'DILI',
        'state' => 'DILI',
        'postcode' => 'TL10901',
        'country_code' => 'TL',
    ]));

    expect($formatted)->toBe("Avenida Presidente Nicolau Lobato\nDILI TL10901\nTimor-Leste");
});

it('formats Iranian addresses with the postcode below the province', function (): void {
    $formatted = app(IranAddressFormatter::class)->format(AddressData::from([
        'line1' => 'West 196 street',
        'line2' => 'No. 12 third floor',
        'city' => 'Tehranpars',
        'state' => 'Tehran Province',
        'postcode' => '1619614153',
        'country_code' => 'IR',
    ]));

    expect($formatted)->toBe("West 196 street\nNo. 12 third floor\nTehranpars\nTehran Province\n1619614153\nIran");
});

it('formats Iranian addresses without a postcode line when missing', function (): void {
    $formatted = app(IranAddressFormatter::class)->format(AddressData::from([
        'line1' => 'West 196 street',
        'city' => 'Tehranpars',
        'state' => 'Tehran Province',
        'country_code' => 'IR',
    ]));

    expect($formatted)->toBe("West 196 street\nTehranpars\nTehran Province\nIran");
});

it('formats Kyrgyz addresses with the postcode left of the locality', function (): void {
    $formatted = app(KyrgyzstanAddressFormatter::class)->format(AddressData::from([
        'line1' => '193, Avenue Chuy, apt. 28',
        'city' => 'BISHKEK',
        'postcode' => '720001',
        'country_code' => 'KG',
    ]));

    expect($formatted)->toBe("193, Avenue Chuy, apt. 28\n720001 BISHKEK\nKyrgyzstan");
});

it('formats rural Kyrgyz addresses with the region below the postcode line', function (): void {
    $formatted = app(KyrgyzstanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Lenin Street 12',
        'city' => 'KARAKOL',
        'state' => 'Issyk-Kul',
        'postcode' => '721600',
        'country_code' => 'KG',
    ]));

    expect($formatted)->toBe("Lenin Street 12\n721600 KARAKOL\nIssyk-Kul\nKyrgyzstan");
});

it('formats North Korean addresses without a postcode system', function (): void {
    $formatted = app(NorthKoreaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Quartier Bottongang',
        'city' => 'PYONGYANG',
        'country_code' => 'KP',
    ]));

    expect($formatted)->toBe("Quartier Bottongang\nPYONGYANG\nNorth Korea");
});

it('prints any supplied North Korean code on its own line', function (): void {
    $formatted = app(NorthKoreaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Quartier Bottongang',
        'city' => 'PYONGYANG',
        'postcode' => '999999',
        'country_code' => 'KP',
    ]));

    expect($formatted)->toBe("Quartier Bottongang\nPYONGYANG\n999999\nNorth Korea");
});

it('formats Kazakh addresses with the postcode and comma left of the locality', function (): void {
    $formatted = app(KazakhstanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'ul. Ryskulbekov, dom16, kv 224',
        'city' => 'ASTANA',
        'postcode' => 'Z00Y5M7',
        'country_code' => 'KZ',
    ]));

    expect($formatted)->toBe("ul. Ryskulbekov, dom16, kv 224\nZ00Y5M7, ASTANA\nKazakhstan");
});

it('formats Kazakh addresses with legacy 6-digit postcodes and region', function (): void {
    $formatted = app(KazakhstanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Abay Street 1',
        'city' => 'Taldykorgan',
        'state' => 'Jetisu',
        'postcode' => '040000',
        'country_code' => 'KZ',
    ]));

    expect($formatted)->toBe("Abay Street 1\n040000, Taldykorgan\nJetisu\nKazakhstan");
});

it('formats Lebanese addresses with the postcode right of the locality', function (): void {
    $formatted = app(LebanonAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Building Al Amal, 2nd floor',
        'line2' => 'Australia Street',
        'city' => 'Raoucheh',
        'state' => 'Beirut',
        'postcode' => '1107 2080',
        'country_code' => 'LB',
    ]));

    expect($formatted)->toBe("Building Al Amal, 2nd floor\nAustralia Street\nRaoucheh 1107 2080\nBeirut\nLebanon");
});

it('formats Lebanese addresses without a postcode when missing', function (): void {
    $formatted = app(LebanonAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Building Al Amal, 2nd floor',
        'city' => 'Raoucheh',
        'state' => 'Beirut',
        'country_code' => 'LB',
    ]));

    expect($formatted)->toBe("Building Al Amal, 2nd floor\nRaoucheh\nBeirut\nLebanon");
});

it('formats Sri Lankan addresses with the postcode below the locality', function (): void {
    $formatted = app(SriLankaAddressFormatter::class)->format(AddressData::from([
        'line1' => '201 Shanti Villa',
        'line2' => 'Silkhouse Street',
        'city' => 'KANDY',
        'postcode' => '20000',
        'country_code' => 'LK',
    ]));

    expect($formatted)->toBe("201 Shanti Villa\nSilkhouse Street\nKANDY\n20000\nSri Lanka");
});

it('formats Sri Lankan addresses keeping the province above the postcode line', function (): void {
    $formatted = app(SriLankaAddressFormatter::class)->format(AddressData::from([
        'line1' => '201 Shanti Villa',
        'city' => 'KANDY',
        'state' => 'Central',
        'postcode' => '20000',
        'country_code' => 'LK',
    ]));

    expect($formatted)->toBe("201 Shanti Villa\nKANDY\nCentral\n20000\nSri Lanka");
});

it('formats Mongolian addresses with the postcode right of the province', function (): void {
    $formatted = app(MongoliaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Jigjidjav street 9-11 toot',
        'line2' => '15th khoroo, Bayanzurkh Duureg',
        'state' => 'ULAANBAATAR',
        'postcode' => '14560',
        'country_code' => 'MN',
    ]));

    expect($formatted)->toBe("Jigjidjav street 9-11 toot\n15th khoroo, Bayanzurkh Duureg\nULAANBAATAR 14560\nMongolia");
});

it('formats Mongolian addresses with the district above the postcode province line', function (): void {
    $formatted = app(MongoliaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Jigjidjav street 9-11 toot',
        'city' => 'Bayanzurkh',
        'state' => 'Ulaanbaatar',
        'postcode' => '14560',
        'country_code' => 'MN',
    ]));

    expect($formatted)->toBe("Jigjidjav street 9-11 toot\nBayanzurkh\nUlaanbaatar 14560\nMongolia");
});

it('formats Armenian addresses with the postcode left of the locality', function (): void {
    $formatted = app(ArmeniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Saryan str 22 apt 25',
        'city' => 'YEREVAN',
        'postcode' => '0002',
        'country_code' => 'AM',
    ]));

    expect($formatted)->toBe("Saryan str 22 apt 25\n0002 YEREVAN\nArmenia");
});

it('formats rural Armenian addresses with the region below the postcode line', function (): void {
    $formatted = app(ArmeniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Mashtots Street 1',
        'city' => 'Vedi',
        'state' => 'Ararat',
        'postcode' => '0601',
        'country_code' => 'AM',
    ]));

    expect($formatted)->toBe("Mashtots Street 1\n0601 Vedi\nArarat\nArmenia");
});

it('formats Azerbaijani addresses with the AZ postcode left of the locality', function (): void {
    $formatted = app(AzerbaijanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Zrifliyeva küç., ev 9',
        'city' => 'Bakı',
        'postcode' => 'AZ1010',
        'country_code' => 'AZ',
    ]));

    expect($formatted)->toBe("Zrifliyeva küç., ev 9\nAZ1010 Bakı\nAzerbaijan");
});

it('formats rural Azerbaijani addresses with the region below the postcode line', function (): void {
    $formatted = app(AzerbaijanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'H. Aliyev küç. 3',
        'city' => 'Nehrəm',
        'state' => 'Nakhchivan',
        'postcode' => 'AZ6715',
        'country_code' => 'AZ',
    ]));

    expect($formatted)->toBe("H. Aliyev küç. 3\nAZ6715 Nehrəm\nNakhchivan\nAzerbaijan");
});

it('formats Bhutanese addresses with the postcode right of the locality', function (): void {
    $formatted = app(BhutanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Chang Lam, JD House',
        'line2' => 'Flat No. 2B',
        'city' => 'Thimphu',
        'state' => 'Thimphu',
        'postcode' => '11001',
        'country_code' => 'BT',
    ]));

    expect($formatted)->toBe("Chang Lam, JD House\nFlat No. 2B\nThimphu 11001\nBhutan");
});

it('prints matching Bhutanese city and dzongkhag once when the postcode is missing', function (): void {
    $formatted = app(BhutanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Chang Lam, JD House',
        'city' => 'Thimphu',
        'state' => 'Thimphu',
        'country_code' => 'BT',
    ]));

    expect($formatted)->toBe("Chang Lam, JD House\nThimphu\nBhutan");
});

it('formats Cypriot inbound addresses with the CY postcode prefix', function (): void {
    $formatted = app(CyprusAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Sofochleous 26',
        'city' => 'Strovolos',
        'postcode' => 'CY-2008',
        'country_code' => 'CY',
    ]));

    expect($formatted)->toBe("Sofochleous 26\nCY-2008 Strovolos\nCyprus");
});

it('formats Cypriot domestic addresses with a bare postcode', function (): void {
    $formatted = app(CyprusAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Griva Digeni 10',
        'city' => 'Larnaka',
        'postcode' => '6036',
        'country_code' => 'CY',
    ]));

    expect($formatted)->toBe("Griva Digeni 10\n6036 Larnaka\nCyprus");
});

it('formats Georgian addresses with the postcode left of the locality', function (): void {
    $formatted = app(GeorgiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Pekinia ave. #39, flat 66',
        'city' => 'TBILISI',
        'postcode' => '0160',
        'country_code' => 'GE',
    ]));

    expect($formatted)->toBe("Pekinia ave. #39, flat 66\n0160 TBILISI\nGeorgia");
});

it('formats rural Georgian addresses with the region below the postcode line', function (): void {
    $formatted = app(GeorgiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Tavisupleba Street 5',
        'city' => 'Telavi',
        'state' => 'Kakheti',
        'postcode' => '2200',
        'country_code' => 'GE',
    ]));

    expect($formatted)->toBe("Tavisupleba Street 5\n2200 Telavi\nKakheti\nGeorgia");
});

it('formats Hong Kong addresses without a postcode system', function (): void {
    $formatted = app(HongKongAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Flat 25, 12/F',
        'line2' => 'Acacia Building',
        'line3' => '150 Kennedy Road',
        'city' => 'WAN CHAI',
        'country_code' => 'HK',
    ]));

    expect($formatted)->toBe("Flat 25, 12/F\nAcacia Building\n150 Kennedy Road\nWAN CHAI\nHong Kong");
});

it('prints any forced Hong Kong code on its own line', function (): void {
    $formatted = app(HongKongAddressFormatter::class)->format(AddressData::from([
        'line1' => '150 Kennedy Road',
        'city' => 'WAN CHAI',
        'postcode' => '000',
        'country_code' => 'HK',
    ]));

    expect($formatted)->toBe("150 Kennedy Road\nWAN CHAI\n000\nHong Kong");
});

it('formats Israeli addresses with the 7-digit postcode left of the locality', function (): void {
    $formatted = app(IsraelAddressFormatter::class)->format(AddressData::from([
        'line1' => '16 Yafo Street',
        'city' => 'JERUSALEM',
        'postcode' => '9414219',
        'country_code' => 'IL',
    ]));

    expect($formatted)->toBe("16 Yafo Street\n9414219 JERUSALEM\nIsrael");
});

it('formats Israeli addresses passing legacy 5-digit codes through', function (): void {
    $formatted = app(IsraelAddressFormatter::class)->format(AddressData::from([
        'line1' => '5 Rothschild Blvd',
        'city' => 'TEL AVIV',
        'postcode' => '61201',
        'country_code' => 'IL',
    ]));

    expect($formatted)->toBe("5 Rothschild Blvd\n61201 TEL AVIV\nIsrael");
});

it('formats Maldivian addresses with the postcode right of the locality', function (): void {
    $formatted = app(MaldivesAddressFormatter::class)->format(AddressData::from([
        'line1' => '26, BODUTHAKURUFAANU MAGU',
        'city' => 'MALÉ',
        'postcode' => '20026',
        'country_code' => 'MV',
    ]));

    expect($formatted)->toBe("26, BODUTHAKURUFAANU MAGU\nMALÉ 20026\nMaldives");
});

it('formats Maldivian island addresses with the atoll below the postcode line', function (): void {
    $formatted = app(MaldivesAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Nirolhu Magu 8',
        'city' => 'Hulhumale',
        'state' => 'Kaafu',
        'postcode' => '23000',
        'country_code' => 'MV',
    ]));

    expect($formatted)->toBe("Nirolhu Magu 8\nHulhumale 23000\nKaafu\nMaldives");
});

it('formats Nepali addresses with the postcode right of the locality', function (): void {
    $formatted = app(NepalAddressFormatter::class)->format(AddressData::from([
        'line1' => '102, Mitery Marg',
        'line2' => 'Baneshwore',
        'city' => 'KATHMANDU',
        'state' => 'Bagmati',
        'postcode' => '44601',
        'country_code' => 'NP',
    ]));

    expect($formatted)->toBe("102, Mitery Marg\nBaneshwore\nKATHMANDU 44601\nBagmati\nNepal");
});

it('formats Nepali addresses without a postcode when missing', function (): void {
    $formatted = app(NepalAddressFormatter::class)->format(AddressData::from([
        'line1' => '102, Mitery Marg',
        'city' => 'KATHMANDU',
        'state' => 'Bagmati',
        'country_code' => 'NP',
    ]));

    expect($formatted)->toBe("102, Mitery Marg\nKATHMANDU\nBagmati\nNepal");
});

it('formats Palestinian addresses with the P postcode right of the locality', function (): void {
    $formatted = app(PalestineAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Irsal Street 10',
        'city' => 'RAMALLAH AND AL-BIREH',
        'postcode' => 'P6100154',
        'country_code' => 'PS',
    ]));

    expect($formatted)->toBe("Irsal Street 10\nRAMALLAH AND AL-BIREH P6100154\nPalestine");
});

it('formats Palestinian addresses passing short P codes through', function (): void {
    $formatted = app(PalestineAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Dahiyat al Bareed',
        'city' => 'JERUSALEM',
        'postcode' => 'P126',
        'country_code' => 'PS',
    ]));

    expect($formatted)->toBe("Dahiyat al Bareed\nJERUSALEM P126\nPalestine");
});

it('formats Syrian addresses without a postcode system', function (): void {
    $formatted = app(SyriaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue Youssef Al Azamah, no 25',
        'city' => 'DAMASCUS',
        'state' => 'Damascus',
        'country_code' => 'SY',
    ]));

    expect($formatted)->toBe("Rue Youssef Al Azamah, no 25\nDAMASCUS\nSyria");
});

it('prints any supplied Syrian code on its own line', function (): void {
    $formatted = app(SyriaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue Youssef Al Azamah, no 25',
        'city' => 'DAMASCUS',
        'postcode' => '0100',
        'country_code' => 'SY',
    ]));

    expect($formatted)->toBe("Rue Youssef Al Azamah, no 25\nDAMASCUS\n0100\nSyria");
});

it('formats Tajik addresses with the postcode left of the locality', function (): void {
    $formatted = app(TajikistanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Khoutchandi 7',
        'city' => 'GARM',
        'postcode' => '735450',
        'country_code' => 'TJ',
    ]));

    expect($formatted)->toBe("Khoutchandi 7\n735450 GARM\nTajikistan");
});

it('prints matching Tajik city and capital region once', function (): void {
    $formatted = app(TajikistanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rudaki Avenue 1',
        'city' => 'DUSHANBE',
        'state' => 'Dushanbe',
        'postcode' => '734012',
        'country_code' => 'TJ',
    ]));

    expect($formatted)->toBe("Rudaki Avenue 1\n734012 DUSHANBE\nTajikistan");
});

it('formats Turkmen addresses with the postcode below the locality', function (): void {
    $formatted = app(TurkmenistanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'j. 19 otag 1',
        'line2' => 'kv. 122',
        'city' => 'BALKANABAT',
        'state' => 'Balkan',
        'postcode' => '745100',
        'country_code' => 'TM',
    ]));

    expect($formatted)->toBe("j. 19 otag 1\nkv. 122\nBALKANABAT\nBalkan\n745100\nTurkmenistan");
});

it('prints matching Turkmen city and capital once above the postcode', function (): void {
    $formatted = app(TurkmenistanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Galkynysh Street 1',
        'city' => 'ASHGABAT',
        'state' => 'Ashgabat',
        'postcode' => '744000',
        'country_code' => 'TM',
    ]));

    expect($formatted)->toBe("Galkynysh Street 1\nASHGABAT\n744000\nTurkmenistan");
});

it('formats Yemeni addresses without a postcode system', function (): void {
    $formatted = app(YemenAddressFormatter::class)->format(AddressData::from([
        'line1' => 'B.P. 1993',
        'city' => "SANA'A",
        'country_code' => 'YE',
    ]));

    expect($formatted)->toBe("B.P. 1993\nSANA'A\nYemen");
});

it('prints matching Yemeni city and governorate once', function (): void {
    $formatted = app(YemenAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Al Wahda Street 2',
        'city' => 'Ibb',
        'state' => 'Ibb',
        'country_code' => 'YE',
    ]));

    expect($formatted)->toBe("Al Wahda Street 2\nIbb\nYemen");
});

it('formats Beninese addresses without a postcode system', function (): void {
    $formatted = app(BeninAddressFormatter::class)->format(AddressData::from([
        'line1' => '10 BP 648',
        'city' => 'COTONOU',
        'country_code' => 'BJ',
    ]));

    expect($formatted)->toBe("10 BP 648\nCOTONOU\nBenin");
});

it('prints any supplied Beninese code on its own line', function (): void {
    $formatted = app(BeninAddressFormatter::class)->format(AddressData::from([
        'line1' => '10 BP 648',
        'city' => 'COTONOU',
        'postcode' => '99999',
        'country_code' => 'BJ',
    ]));

    expect($formatted)->toBe("10 BP 648\nCOTONOU\n99999\nBenin");
});

it('formats Botswanan addresses without a postcode system', function (): void {
    $formatted = app(BotswanaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 231',
        'city' => 'HUKUNTSI',
        'country_code' => 'BW',
    ]));

    expect($formatted)->toBe("P.O. Box 231\nHUKUNTSI\nBotswana");
});

it('formats Botswanan private bag addresses with the town only', function (): void {
    $formatted = app(BotswanaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P/Bag 1061',
        'city' => 'GABORONE',
        'country_code' => 'BW',
    ]));

    expect($formatted)->toBe("P/Bag 1061\nGABORONE\nBotswana");
});

it('formats Burkinabe addresses with the postcode left of the locality', function (): void {
    $formatted = app(BurkinaFasoAddressFormatter::class)->format(AddressData::from([
        'line1' => '566 Avenue de la Nation',
        'city' => 'OUAGADOUGOU',
        'postcode' => '10010',
        'country_code' => 'BF',
    ]));

    expect($formatted)->toBe("566 Avenue de la Nation\n10010 OUAGADOUGOU\nBurkina Faso");
});

it('formats rural Burkinabe addresses with the region below the postcode line', function (): void {
    $formatted = app(BurkinaFasoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Secteur 3',
        'city' => 'TENKODOGO',
        'state' => 'Centre-Est',
        'postcode' => '70000',
        'country_code' => 'BF',
    ]));

    expect($formatted)->toBe("Secteur 3\n70000 TENKODOGO\nCentre-Est\nBurkina Faso");
});

it('formats Burundian addresses without a postcode system', function (): void {
    $formatted = app(BurundiAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 1915',
        'city' => 'MUKAZA',
        'state' => 'Bujumbura',
        'country_code' => 'BI',
    ]));

    expect($formatted)->toBe("BP 1915\nMUKAZA\nBujumbura\nBurundi");
});

it('prints any supplied Burundian code on its own line', function (): void {
    $formatted = app(BurundiAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 1915',
        'city' => 'MUKAZA',
        'postcode' => '99999',
        'country_code' => 'BI',
    ]));

    expect($formatted)->toBe("BP 1915\nMUKAZA\n99999\nBurundi");
});

it('formats Cape Verdean addresses with the postcode left of the locality', function (): void {
    $formatted = app(CapeVerdeAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rua 5 de Julho 138/Platô',
        'line2' => 'C.P. 38',
        'city' => 'PRAIA',
        'postcode' => '7600',
        'country_code' => 'CV',
    ]));

    expect($formatted)->toBe("Rua 5 de Julho 138/Platô\nC.P. 38\n7600 PRAIA\nCape Verde");
});

it('formats Cape Verdean addresses passing 7-digit codes through', function (): void {
    $formatted = app(CapeVerdeAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rua 5 de Julho 138/Platô',
        'city' => 'PRAIA',
        'postcode' => '7600-120',
        'country_code' => 'CV',
    ]));

    expect($formatted)->toBe("Rua 5 de Julho 138/Platô\n7600-120 PRAIA\nCape Verde");
});

it('formats Central African addresses without a postcode system', function (): void {
    $formatted = app(CentralAfricanRepublicAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 729',
        'city' => 'BANGUI',
        'country_code' => 'CF',
    ]));

    expect($formatted)->toBe("BP 729\nBANGUI\nCentral African Republic");
});

it('prints any supplied Central African code on its own line', function (): void {
    $formatted = app(CentralAfricanRepublicAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 729',
        'city' => 'BANGUI',
        'postcode' => '99999',
        'country_code' => 'CF',
    ]));

    expect($formatted)->toBe("BP 729\nBANGUI\n99999\nCentral African Republic");
});

it('formats Chadian addresses without a postcode system', function (): void {
    $formatted = app(ChadAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 4148',
        'city' => 'NDJAMENA',
        'country_code' => 'TD',
    ]));

    expect($formatted)->toBe("BP 4148\nNDJAMENA\nChad");
});

it('formats Chadian addresses with the province below the locality', function (): void {
    $formatted = app(ChadAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Avenue des Martyrs',
        'city' => 'Moundou',
        'state' => 'Logone Occidental',
        'country_code' => 'TD',
    ]));

    expect($formatted)->toBe("Avenue des Martyrs\nMoundou\nLogone Occidental\nChad");
});

it('formats Comorian addresses without a postcode system', function (): void {
    $formatted = app(ComorosAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 350',
        'city' => 'MORONI',
        'country_code' => 'KM',
    ]));

    expect($formatted)->toBe("BP 350\nMORONI\nComoros");
});

it('formats Comorian addresses with the island below the locality', function (): void {
    $formatted = app(ComorosAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue de la Corniche',
        'city' => 'Moroni',
        'state' => 'Grande Comore',
        'country_code' => 'KM',
    ]));

    expect($formatted)->toBe("Rue de la Corniche\nMoroni\nGrande Comore\nComoros");
});

it('formats Congolese addresses without a postcode system', function (): void {
    $formatted = app(CongoAddressFormatter::class)->format(AddressData::from([
        'line1' => '12, rue Kakamoueka',
        'city' => 'BRAZZAVILLE',
        'country_code' => 'CG',
    ]));

    expect($formatted)->toBe("12, rue Kakamoueka\nBRAZZAVILLE\nCongo");
});

it('prints any supplied Congolese code on its own line', function (): void {
    $formatted = app(CongoAddressFormatter::class)->format(AddressData::from([
        'line1' => '12, rue Kakamoueka',
        'city' => 'BRAZZAVILLE',
        'postcode' => '99999',
        'country_code' => 'CG',
    ]));

    expect($formatted)->toBe("12, rue Kakamoueka\nBRAZZAVILLE\n99999\nCongo");
});

it('formats Ivorian addresses without a postcode system', function (): void {
    $formatted = app(IvoryCoastAddressFormatter::class)->format(AddressData::from([
        'line1' => '06 B.P. 37',
        'city' => 'ABIDJAN',
        'country_code' => 'CI',
    ]));

    expect($formatted)->toBe("06 B.P. 37\nABIDJAN\nIvory Coast");
});

it('prints any supplied Ivorian code on its own line', function (): void {
    $formatted = app(IvoryCoastAddressFormatter::class)->format(AddressData::from([
        'line1' => '06 B.P. 37',
        'city' => 'ABIDJAN',
        'postcode' => '99999',
        'country_code' => 'CI',
    ]));

    expect($formatted)->toBe("06 B.P. 37\nABIDJAN\n99999\nIvory Coast");
});

it('formats Djiboutian addresses with the postcode left of the locality', function (): void {
    $formatted = app(DjiboutiAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 1663',
        'city' => 'DJIBOUTI VILLE',
        'postcode' => '77101',
        'country_code' => 'DJ',
    ]));

    expect($formatted)->toBe("BP 1663\n77101 DJIBOUTI VILLE\nDjibouti");
});

it('prints matching Djiboutian city and region once', function (): void {
    $formatted = app(DjiboutiAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 12',
        'city' => 'Arta',
        'state' => 'Arta',
        'postcode' => '77201',
        'country_code' => 'DJ',
    ]));

    expect($formatted)->toBe("BP 12\n77201 Arta\nDjibouti");
});

it('formats Equatoguinean addresses without a postcode system', function (): void {
    $formatted = app(EquatorialGuineaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Viviendas sociales vicatana',
        'line2' => 'Portal 5, puerta 29',
        'city' => 'MALABO',
        'state' => 'Bioko Norte',
        'country_code' => 'GQ',
    ]));

    expect($formatted)->toBe("Viviendas sociales vicatana\nPortal 5, puerta 29\nMALABO\nBioko Norte\nEquatorial Guinea");
});

it('prints any supplied Equatoguinean code on its own line', function (): void {
    $formatted = app(EquatorialGuineaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Apartado postal 7',
        'city' => 'MALABO',
        'postcode' => '99999',
        'country_code' => 'GQ',
    ]));

    expect($formatted)->toBe("Apartado postal 7\nMALABO\n99999\nEquatorial Guinea");
});

it('formats Eritrean addresses without a postcode system', function (): void {
    $formatted = app(EritreaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Awet Street 4',
        'city' => 'ASMARA',
        'country_code' => 'ER',
    ]));

    expect($formatted)->toBe("Awet Street 4\nASMARA\nEritrea");
});

it('prints any supplied Eritrean code on its own line', function (): void {
    $formatted = app(EritreaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Awet Street 4',
        'city' => 'ASMARA',
        'postcode' => '99999',
        'country_code' => 'ER',
    ]));

    expect($formatted)->toBe("Awet Street 4\nASMARA\n99999\nEritrea");
});

it('formats Gabonese addresses with the zone left of the locality', function (): void {
    $formatted = app(GabonAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 13210',
        'city' => 'LIBREVILLE',
        'postcode' => '01',
        'country_code' => 'GA',
    ]));

    expect($formatted)->toBe("BP 13210\n01 LIBREVILLE\nGabon");
});

it('formats Gabonese addresses with the province below the postcode line', function (): void {
    $formatted = app(GabonAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 45',
        'city' => 'TCHIBANGA',
        'state' => 'Nyanga',
        'postcode' => '05',
        'country_code' => 'GA',
    ]));

    expect($formatted)->toBe("BP 45\n05 TCHIBANGA\nNyanga\nGabon");
});

it('formats Gambian addresses without a postcode system', function (): void {
    $formatted = app(GambiaAddressFormatter::class)->format(AddressData::from([
        'line1' => '21 Liberation Avenue',
        'city' => 'BANJUL',
        'country_code' => 'GM',
    ]));

    expect($formatted)->toBe("21 Liberation Avenue\nBANJUL\nThe Gambia");
});

it('formats Gambian addresses with the division below the locality', function (): void {
    $formatted = app(GambiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Main Street',
        'city' => 'Basse',
        'state' => 'Upper River',
        'country_code' => 'GM',
    ]));

    expect($formatted)->toBe("Main Street\nBasse\nUpper River\nThe Gambia");
});

it('formats Guinean addresses with the radical left of the locality', function (): void {
    $formatted = app(GuineaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 457',
        'city' => 'CONAKRY',
        'postcode' => '001',
        'country_code' => 'GN',
    ]));

    expect($formatted)->toBe("BP 457\n001 CONAKRY\nGuinea");
});

it('prints matching Guinean city and region once', function (): void {
    $formatted = app(GuineaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 12',
        'city' => 'Labé',
        'state' => 'Labé',
        'postcode' => '201',
        'country_code' => 'GN',
    ]));

    expect($formatted)->toBe("BP 12\n201 Labé\nGuinea");
});

it('formats Bissau-Guinean addresses with the postcode left of the locality', function (): void {
    $formatted = app(GuineaBissauAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rua Justino Lopes 12C',
        'city' => 'BISSAU',
        'postcode' => '1000',
        'country_code' => 'GW',
    ]));

    expect($formatted)->toBe("Rua Justino Lopes 12C\n1000 BISSAU\nGuinea-Bissau");
});

it('formats Bissau-Guinean addresses without a postcode when missing', function (): void {
    $formatted = app(GuineaBissauAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rua Justino Lopes 12C',
        'city' => 'BISSAU',
        'country_code' => 'GW',
    ]));

    expect($formatted)->toBe("Rua Justino Lopes 12C\nBISSAU\nGuinea-Bissau");
});

it('formats Basotho addresses with the postcode right of the locality', function (): void {
    $formatted = app(LesothoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 500',
        'city' => 'MASERU',
        'postcode' => '100',
        'country_code' => 'LS',
    ]));

    expect($formatted)->toBe("P.O. Box 500\nMASERU 100\nLesotho");
});

it('prints matching Basotho city and district once when the postcode is missing', function (): void {
    $formatted = app(LesothoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 500',
        'city' => 'Maseru',
        'state' => 'Maseru',
        'country_code' => 'LS',
    ]));

    expect($formatted)->toBe("P.O. Box 500\nMaseru\nLesotho");
});

it('formats Liberian addresses with the postcode left of the locality', function (): void {
    $formatted = app(LiberiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Water Street',
        'city' => 'Buchanan',
        'postcode' => '4000',
        'country_code' => 'LR',
    ]));

    expect($formatted)->toBe("Water Street\n4000 Buchanan\nLiberia");
});

it('formats Liberian addresses without a postcode when missing', function (): void {
    $formatted = app(LiberiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Water Street',
        'city' => 'Monrovia',
        'state' => 'Montserrado',
        'country_code' => 'LR',
    ]));

    expect($formatted)->toBe("Water Street\nMonrovia\nMontserrado\nLiberia");
});

it('formats Libyan addresses without a postcode system', function (): void {
    $formatted = app(LibyaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Av. Al Ghazaly 12',
        'city' => 'TRIPOLI',
        'country_code' => 'LY',
    ]));

    expect($formatted)->toBe("Av. Al Ghazaly 12\nTRIPOLI\nLibya");
});

it('prints any supplied Libyan code on its own line', function (): void {
    $formatted = app(LibyaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Av. Al Ghazaly 12',
        'city' => 'TRIPOLI',
        'postcode' => '99999',
        'country_code' => 'LY',
    ]));

    expect($formatted)->toBe("Av. Al Ghazaly 12\nTRIPOLI\n99999\nLibya");
});

it('formats Malawian addresses with the postcode left of the locality', function (): void {
    $formatted = app(MalawiAddressFormatter::class)->format(AddressData::from([
        'line1' => '21 Dunduzu Avenue',
        'city' => 'KASUNGU',
        'postcode' => '102010',
        'country_code' => 'MW',
    ]));

    expect($formatted)->toBe("21 Dunduzu Avenue\n102010 KASUNGU\nMalawi");
});

it('formats Malawian addresses with the region below the postcode line', function (): void {
    $formatted = app(MalawiAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Chipembere Highway',
        'city' => 'Blantyre',
        'state' => 'Southern',
        'postcode' => '309070',
        'country_code' => 'MW',
    ]));

    expect($formatted)->toBe("Chipembere Highway\n309070 Blantyre\nSouthern\nMalawi");
});

it('formats Malian addresses without a postcode system', function (): void {
    $formatted = app(MaliAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue 406 – porte 39',
        'line2' => 'Magnabougou',
        'city' => 'BAMAKO',
        'country_code' => 'ML',
    ]));

    expect($formatted)->toBe("Rue 406 – porte 39\nMagnabougou\nBAMAKO\nMali");
});

it('prints matching Malian city and region once', function (): void {
    $formatted = app(MaliAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue 10',
        'city' => 'Sikasso',
        'state' => 'Sikasso',
        'country_code' => 'ML',
    ]));

    expect($formatted)->toBe("Rue 10\nSikasso\nMali");
});

it('formats Mauritanian addresses without a postcode system', function (): void {
    $formatted = app(MauritaniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'B.P. 35',
        'city' => 'NOUAKCHOTT',
        'country_code' => 'MR',
    ]));

    expect($formatted)->toBe("B.P. 35\nNOUAKCHOTT\nMauritania");
});

it('prints any supplied Mauritanian code on its own line', function (): void {
    $formatted = app(MauritaniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'B.P. 35',
        'city' => 'NOUAKCHOTT',
        'postcode' => '99999',
        'country_code' => 'MR',
    ]));

    expect($formatted)->toBe("B.P. 35\nNOUAKCHOTT\n99999\nMauritania");
});

it('formats Mauritian addresses with the postcode right of the locality', function (): void {
    $formatted = app(MauritiusAddressFormatter::class)->format(AddressData::from([
        'line1' => '10, rue Claude Delaître',
        'line2' => 'Les Guibies',
        'city' => 'PORT LOUIS',
        'postcode' => '11213',
        'country_code' => 'MU',
    ]));

    expect($formatted)->toBe("10, rue Claude Delaître\nLes Guibies\nPORT LOUIS 11213\nMauritius");
});

it('formats Rodriguan addresses with the R postcode right of the locality', function (): void {
    $formatted = app(MauritiusAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue de la Solidarité',
        'city' => 'Port Mathurin',
        'state' => 'Rodrigues Island',
        'postcode' => 'R5135',
        'country_code' => 'MU',
    ]));

    expect($formatted)->toBe("Rue de la Solidarité\nPort Mathurin R5135\nRodrigues Island\nMauritius");
});

it('formats Namibian addresses with the postcode below the locality', function (): void {
    $formatted = app(NamibiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Private bag 13678',
        'city' => 'WINDHOEK',
        'postcode' => '10005',
        'country_code' => 'NA',
    ]));

    expect($formatted)->toBe("Private bag 13678\nWINDHOEK\n10005\nNamibia");
});

it('formats Namibian post box addresses with the postcode below the office', function (): void {
    $formatted = app(NamibiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 999',
        'city' => 'OKAHANDJA',
        'postcode' => '12004',
        'country_code' => 'NA',
    ]));

    expect($formatted)->toBe("PO Box 999\nOKAHANDJA\n12004\nNamibia");
});

it('formats Nigerien addresses with the postcode left of the locality', function (): void {
    $formatted = app(NigerAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 502',
        'city' => 'NIAMEY',
        'postcode' => '8001',
        'country_code' => 'NE',
    ]));

    expect($formatted)->toBe("BP 502\n8001 NIAMEY\nNiger");
});

it('formats Nigerien addresses keeping the abbreviated capital above the region', function (): void {
    $formatted = app(NigerAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 502',
        'city' => 'NY',
        'state' => 'Niamey',
        'postcode' => '8000',
        'country_code' => 'NE',
    ]));

    expect($formatted)->toBe("BP 502\n8000 NY\nNiamey\nNiger");
});

it('formats Rwandan addresses without a postcode system', function (): void {
    $formatted = app(RwandaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'B.P. 3425',
        'city' => 'KIGALI',
        'country_code' => 'RW',
    ]));

    expect($formatted)->toBe("B.P. 3425\nKIGALI\nRwanda");
});

it('formats Rwandan addresses with the province below the locality', function (): void {
    $formatted = app(RwandaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'KN 3 Road',
        'city' => 'Butare',
        'state' => 'Southern',
        'country_code' => 'RW',
    ]));

    expect($formatted)->toBe("KN 3 Road\nButare\nSouthern\nRwanda");
});

it('formats Santomean addresses without a postcode system', function (): void {
    $formatted = app(SaoTomeAndPrincipeAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rua 3 de Fevereiro',
        'city' => 'São Tomé',
        'country_code' => 'ST',
    ]));

    expect($formatted)->toBe("Rua 3 de Fevereiro\nSão Tomé\nSao Tome and Principe");
});

it('prints any supplied Santomean code on its own line', function (): void {
    $formatted = app(SaoTomeAndPrincipeAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rua 3 de Fevereiro',
        'city' => 'São Tomé',
        'postcode' => '99999',
        'country_code' => 'ST',
    ]));

    expect($formatted)->toBe("Rua 3 de Fevereiro\nSão Tomé\n99999\nSao Tome and Principe");
});

it('formats Senegalese addresses with the postcode left of the office', function (): void {
    $formatted = app(SenegalAddressFormatter::class)->format(AddressData::from([
        'line1' => '12 AVENUE CHEIKH ANTA DIOP',
        'city' => 'DAKAR',
        'postcode' => '12500',
        'country_code' => 'SN',
    ]));

    expect($formatted)->toBe("12 AVENUE CHEIKH ANTA DIOP\n12500 DAKAR\nSenegal");
});

it('prints matching Senegalese city and region once', function (): void {
    $formatted = app(SenegalAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 1534',
        'city' => 'Ziguinchor',
        'state' => 'Ziguinchor',
        'postcode' => '27000',
        'country_code' => 'SN',
    ]));

    expect($formatted)->toBe("BP 1534\n27000 Ziguinchor\nSenegal");
});

it('formats Seychellois addresses without a postcode system', function (): void {
    $formatted = app(SeychellesAddressFormatter::class)->format(AddressData::from([
        'line1' => 'S19 - E36',
        'city' => 'Victoria',
        'state' => 'Mahé',
        'country_code' => 'SC',
    ]));

    expect($formatted)->toBe("S19 - E36\nVictoria\nMahé\nSeychelles");
});

it('prints any supplied Seychellois code on its own line', function (): void {
    $formatted = app(SeychellesAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 1538',
        'city' => 'Victoria',
        'postcode' => '99999',
        'country_code' => 'SC',
    ]));

    expect($formatted)->toBe("P.O. Box 1538\nVictoria\n99999\nSeychelles");
});

it('formats Sierra Leonean addresses without a postcode system', function (): void {
    $formatted = app(SierraLeoneAddressFormatter::class)->format(AddressData::from([
        'line1' => '7A Ross Road Cline',
        'city' => 'FREETOWN',
        'country_code' => 'SL',
    ]));

    expect($formatted)->toBe("7A Ross Road Cline\nFREETOWN\nSierra Leone");
});

it('formats Sierra Leonean addresses with the province below the locality', function (): void {
    $formatted = app(SierraLeoneAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Bojon Street',
        'city' => 'Bo',
        'state' => 'Southern',
        'country_code' => 'SL',
    ]));

    expect($formatted)->toBe("Bojon Street\nBo\nSouthern\nSierra Leone");
});

it('formats Somali addresses without an operational postcode', function (): void {
    $formatted = app(SomaliaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 1001',
        'city' => 'KISMAYU',
        'country_code' => 'SO',
    ]));

    expect($formatted)->toBe("P.O. Box 1001\nKISMAYU\nSomalia");
});

it('prints any supplied Somali paper code on its own line', function (): void {
    $formatted = app(SomaliaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 1001',
        'city' => 'KISMAYU',
        'postcode' => 'JH 09010',
        'country_code' => 'SO',
    ]));

    expect($formatted)->toBe("P.O. Box 1001\nKISMAYU\nJH 09010\nSomalia");
});

it('formats South Sudanese addresses without a postcode system', function (): void {
    $formatted = app(SouthSudanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Plot 123, Hai Malakal',
        'city' => 'JUBA',
        'state' => 'Central Equatoria',
        'country_code' => 'SS',
    ]));

    expect($formatted)->toBe("Plot 123, Hai Malakal\nJUBA\nCentral Equatoria\nSouth Sudan");
});

it('prints any supplied South Sudanese code on its own line', function (): void {
    $formatted = app(SouthSudanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O Box 449',
        'city' => 'JUBA',
        'postcode' => '99999',
        'country_code' => 'SS',
    ]));

    expect($formatted)->toBe("P.O Box 449\nJUBA\n99999\nSouth Sudan");
});

it('formats Eswatini addresses with the postcode below the locality', function (): void {
    $formatted = app(EswatiniAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 125',
        'city' => 'MBABANE',
        'postcode' => 'H100',
        'country_code' => 'SZ',
    ]));

    expect($formatted)->toBe("P.O. Box 125\nMBABANE\nH100\nEswatini");
});

it('prints matching Eswatini city and region once above the postcode', function (): void {
    $formatted = app(EswatiniAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 200',
        'city' => 'Manzini',
        'state' => 'Manzini',
        'postcode' => 'M200',
        'country_code' => 'SZ',
    ]));

    expect($formatted)->toBe("P.O. Box 200\nManzini\nM200\nEswatini");
});

it('formats Togolese addresses without a postcode system', function (): void {
    $formatted = app(TogoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'B.P. 526',
        'city' => 'LOME',
        'country_code' => 'TG',
    ]));

    expect($formatted)->toBe("B.P. 526\nLOME\nTogo");
});

it('formats Togolese addresses with the region below the locality', function (): void {
    $formatted = app(TogoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue des Jasmins',
        'city' => 'Kpalimé',
        'state' => 'Plateaux',
        'country_code' => 'TG',
    ]));

    expect($formatted)->toBe("Rue des Jasmins\nKpalimé\nPlateaux\nTogo");
});

it('formats Tunisian addresses with the postcode left of the locality', function (): void {
    $formatted = app(TunisiaAddressFormatter::class)->format(AddressData::from([
        'line1' => '15 AVENUE BOURGUIBA',
        'city' => 'BOU SALEM',
        'postcode' => '8170',
        'country_code' => 'TN',
    ]));

    expect($formatted)->toBe("15 AVENUE BOURGUIBA\n8170 BOU SALEM\nTunisia");
});

it('prints matching Tunisian city and governorate once', function (): void {
    $formatted = app(TunisiaAddressFormatter::class)->format(AddressData::from([
        'line1' => '15 AVENUE BOURGUIBA',
        'city' => 'TUNIS',
        'state' => 'Tunis',
        'postcode' => '1002',
        'country_code' => 'TN',
    ]));

    expect($formatted)->toBe("15 AVENUE BOURGUIBA\n1002 TUNIS\nTunisia");
});

it('formats Zambian addresses with the postcode right of the locality', function (): void {
    $formatted = app(ZambiaAddressFormatter::class)->format(AddressData::from([
        'line1' => '21 Independence Avenue',
        'city' => 'KITWE',
        'postcode' => '23456',
        'country_code' => 'ZM',
    ]));

    expect($formatted)->toBe("21 Independence Avenue\nKITWE 23456\nZambia");
});

it('formats Zambian addresses omitting the routinely skipped postcode', function (): void {
    $formatted = app(ZambiaAddressFormatter::class)->format(AddressData::from([
        'line1' => '21 Independence Avenue',
        'city' => 'LUSAKA',
        'state' => 'Lusaka',
        'country_code' => 'ZM',
    ]));

    expect($formatted)->toBe("21 Independence Avenue\nLUSAKA\nZambia");
});

it('formats Zimbabwean addresses without a postcode system', function (): void {
    $formatted = app(ZimbabweAddressFormatter::class)->format(AddressData::from([
        'line1' => '34–6th Crescent',
        'line2' => 'Warren Park 1',
        'city' => 'HARARE',
        'country_code' => 'ZW',
    ]));

    expect($formatted)->toBe("34–6th Crescent\nWarren Park 1\nHARARE\nZimbabwe");
});

it('prints matching Zimbabwean city and province once', function (): void {
    $formatted = app(ZimbabweAddressFormatter::class)->format(AddressData::from([
        'line1' => '12 Josiah Tongogara Street',
        'city' => 'Bulawayo',
        'state' => 'Bulawayo',
        'country_code' => 'ZW',
    ]));

    expect($formatted)->toBe("12 Josiah Tongogara Street\nBulawayo\nZimbabwe");
});

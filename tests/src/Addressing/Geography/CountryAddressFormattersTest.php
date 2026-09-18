<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Algeria\AlgeriaAddressFormatter;
use AIArmada\Addressing\Geography\Bahrain\BahrainAddressFormatter;
use AIArmada\Addressing\Geography\Bangladesh\BangladeshAddressFormatter;
use AIArmada\Addressing\Geography\China\ChinaAddressFormatter;
use AIArmada\Addressing\Geography\DemocraticRepublicOfCongo\DemocraticRepublicOfCongoAddressFormatter;
use AIArmada\Addressing\Geography\Egypt\EgyptAddressFormatter;
use AIArmada\Addressing\Geography\Ethiopia\EthiopiaAddressFormatter;
use AIArmada\Addressing\Geography\France\FranceAddressFormatter;
use AIArmada\Addressing\Geography\Germany\GermanyAddressFormatter;
use AIArmada\Addressing\Geography\India\IndiaAddressFormatter;
use AIArmada\Addressing\Geography\Italy\ItalyAddressFormatter;
use AIArmada\Addressing\Geography\Japan\JapanAddressFormatter;
use AIArmada\Addressing\Geography\Jordan\JordanAddressFormatter;
use AIArmada\Addressing\Geography\Kenya\KenyaAddressFormatter;
use AIArmada\Addressing\Geography\Kuwait\KuwaitAddressFormatter;
use AIArmada\Addressing\Geography\Morocco\MoroccoAddressFormatter;
use AIArmada\Addressing\Geography\Netherlands\NetherlandsAddressFormatter;
use AIArmada\Addressing\Geography\Nigeria\NigeriaAddressFormatter;
use AIArmada\Addressing\Geography\Oman\OmanAddressFormatter;
use AIArmada\Addressing\Geography\Pakistan\PakistanAddressFormatter;
use AIArmada\Addressing\Geography\Philippines\PhilippinesAddressFormatter;
use AIArmada\Addressing\Geography\Poland\PolandAddressFormatter;
use AIArmada\Addressing\Geography\Qatar\QatarAddressFormatter;
use AIArmada\Addressing\Geography\Russia\RussiaAddressFormatter;
use AIArmada\Addressing\Geography\SaudiArabia\SaudiArabiaAddressFormatter;
use AIArmada\Addressing\Geography\SouthAfrica\SouthAfricaAddressFormatter;
use AIArmada\Addressing\Geography\SouthKorea\SouthKoreaAddressFormatter;
use AIArmada\Addressing\Geography\Spain\SpainAddressFormatter;
use AIArmada\Addressing\Geography\Sudan\SudanAddressFormatter;
use AIArmada\Addressing\Geography\Tanzania\TanzaniaAddressFormatter;
use AIArmada\Addressing\Geography\Turkiye\TurkiyeAddressFormatter;
use AIArmada\Addressing\Geography\Uganda\UgandaAddressFormatter;
use AIArmada\Addressing\Geography\UnitedArabEmirates\UnitedArabEmiratesAddressFormatter;
use AIArmada\Addressing\Geography\UnitedKingdom\UnitedKingdomAddressFormatter;
use AIArmada\Addressing\Geography\UnitedStates\UnitedStatesAddressFormatter;

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

---
title: Country Data
---

# Country Data

For per-provider depth and dataset status at a glance, see the
[Provider Coverage Registry](./14-provider-coverage.md).

## Bundled Dataset

The package always bundles ISO 3166-1 country/territory data.

File location: `resources/data/countries.json`

The bundled `MalaysiaGeographyProvider` supplies Malaysia's State/Federal Territory catalog, two explicit address hierarchies, the AddressArea hierarchy, and State↔AddressArea mappings. The postal/address hierarchy is `region → locality / precinct / kampung`; the administrative/land hierarchy is `region → district / division / jajahan → mukim / subdistrict / bandar / pekan`. It is selected with `SeedCountryGeographiesAction::execute('MY')` after countries are seeded.

The dataset contains **250 records** — these are ISO 3166-1 address entities, not 250 sovereign countries. Records include:

- ISO2, ISO3, numeric codes
- Names (common, native)
- Phone codes
- Capital
- Country centroid coordinates
- Region and subregion
- Currency code
- Timezones
- Top-level domain
- Translated country names

Currency, language, and timezone reference data is owned and seeded by `commerce-support`:

- `currencies` from the shared currency catalogue
- `languages` from the shared ISO 639-1 catalogue
- `timezones` from the shared IANA timezone catalogue

Countries are linked to shared currencies and timezones through UUID pivot tables. The country table does not store currency or timezone JSON/scalar columns. Country-language relationships are intentionally not modelled until a trusted mapping dataset is available.

## What is NOT Bundled by Default

Without selecting a country provider, the following must be supplied by users through `State`/`City` models, `AddressAreaSource`, array imports, or CSV imports:

- States, federal territories, provinces, prefectures, emirates
- Districts, cities, towns, villages, mukim, neighbourhoods
- Postcodes and worldwide area hierarchies

## Bundled States and Cities

`states.json` and `cities.json` are bundled from the same nnjeim/world source. Seed the global files first; country providers then complement those rows using stable country-scoped identities. The Malaysia provider updates matching states in place and adds Malaysia-specific rows not present in the global file, such as Putrajaya. It does not seed shared commerce-support reference data. The bundled area source contains no canonical city mapping data.

## Malaysia area roles

Kuala Lumpur, Putrajaya and Labuan do not receive duplicate postal-town
records. Their Federal Territory is the administrative area, while localities,
precincts and settlements are address subdivisions.

- postal_locality: Kuala Lumpur localities, Labuan settlements and Putrajaya precincts
- administrative_district: districts
- administrative_subdivision: mukim and subdistricts
- region: states and Federal Territories

## Singapore

The bundled `SingaporeGeographyProvider` supplies the five ISO 3166-2 CDC
districts as `State` rows, the URA planning tree (5 regions, 55 planning
areas), and the postal tree (28 districts, 81 sectors; sector `74` is
unallocated). The postal hierarchy is `postal district → postal sector`; the
administrative hierarchy is `planning region → planning area`. It is selected
with `SeedCountryGeographiesAction::execute('SG')` after countries are seeded.

CDC districts, URA regions, and postal districts are three independent
first-level partitions: a CDC district is never the parent of a planning area
or a postal sector. `State` rows link to matching district areas without a
hierarchy type so the bridge resolves them for any hierarchy.

Individual six-digit postcodes are intentionally not bundled. Every building
in Singapore has its own postcode, so the dataset is SingPost-scale. Resolve
operational postcodes on demand with `ResolveSingaporePostalCodesAction`,
which queries SLA's OneMap API and caches the results in `postal_codes` with
sector links. Use the bundled postal sectors (the first two postcode digits)
for area-level grouping. OneMap data requires attribution to OneMap/SLA in
applications that display it.

Singapore addresses are formatted as street lines followed by
`Singapore NNNNNN`. State and city lines are omitted when they are empty or
just repeat `Singapore`; a distinctive town value is kept on its own line.

## Indonesia

The bundled `IndonesiaGeographyProvider` supplies the 38 ISO 3166-2
provinces as `State` rows and one administrative hierarchy: 38 provinces →
514 regencies/cities → 7,285 districts. Area codes are the official
Kemendagri codes (2/4/6 digits) and `source_id` values embed them
(`id:province:11`, `id:regency:1101`, `id:district:110101`). It is selected
with `SeedCountryGeographiesAction::execute('ID')` after countries are seeded.

The area tree is derived from
[lokabisa-oss/region-id v1.0.1](https://github.com/lokabisa-oss/region-id/releases/tag/v1.0.1)
(MIT), keeping official names, codes, and parent links. Two province names
differ from that source so areas match the seeded states: `DKI Jakarta`
(source: `Daerah Khusus Ibukota Jakarta`, the pre-2024 legal name) and `DI
Yogyakarta` (source: `Daerah Istimewa Yogyakarta`, kept as an alias). The
current legal name `Daerah Khusus Jakarta` is also aliased.

ISO 3166-2 defines seven Indonesian geographical units (island groups such
as `ID-JW` Jawa) alongside the 38 provinces. Those units are not provinces
and were removed from the bundled state data; `seed()` also deletes any
stragglers from databases seeded before that fix, so `Papua` always resolves
to the province. Nusantara/IKN is a separate capital authority, not a 39th
province.

Villages (83,762) and individual five-digit postcodes are intentionally not
bundled. Import operational villages through `AddressAreaSource` and
postcodes through `ImportPostalCodesAction`.

Indonesian addresses are formatted as street lines, `kelurahan`/`desa` and
`kecamatan` components, `{kota} {postcode}`, province, and country, per the
UPU addressing layout.

## Brunei

The bundled `BruneiGeographyProvider` supplies the four ISO 3166-2
districts as `State` rows and one administrative hierarchy: 4 districts →
39 mukims (18 in Brunei-Muara, 8 each in Belait and Tutong, 5 in
Temburong). It is selected with
`SeedCountryGeographiesAction::execute('BN')` after countries are seeded.

Mukim names follow Brunei government spellings (`Pengkalan Batu`, with
`Pangkalan Batu` aliased). Bruneian postcodes are six characters — two
uppercase letters (district, then mukim) followed by four digits — but no
complete public dataset exists, so postcodes are intentionally not bundled.
Import operational villages and postcodes through `AddressAreaSource` and
`ImportPostalCodesAction`.

Brunei addresses are formatted per the UPU layout: street lines, kampung
component, `{town or district} {postcode}` with the town preferred, and
country.

## Bahrain

The bundled `BahrainGeographyProvider` supplies the four ISO 3166-2
governorates as `State` rows (codes `13`, `14`, `15`, `17` — there is no
`16` since the Central Governorate was abolished in 2014) and a
single-level administrative hierarchy. It is selected with
`SeedCountryGeographiesAction::execute('BH')` after countries are seeded.

Bahraini addresses are formatted per the UPU layout: street lines,
`{municipality} {postcode}` with a 3–4 digit postcode, and country.

## Qatar

The bundled `QatarGeographyProvider` supplies the eight municipalities
as `State` rows and a single-level administrative hierarchy. It is
selected with `SeedCountryGeographiesAction::execute('QA')` after
countries are seeded.

Municipality names use official English spellings (`Al Sheehaniya`,
`Al Shamal`, `Doha`); the ISO names `Ad Dawhah` and `Madinat ash
Shamal` are kept as aliases. Qatar has no postcode system — delivery
is by P.O. Box or zone/street — so the formatter stacks street lines,
city, and country with no postcode line.

## Kuwait

The bundled `KuwaitGeographyProvider` supplies the six ISO 3166-2
governorates as `State` rows and a single-level administrative
hierarchy. It is selected with
`SeedCountryGeographiesAction::execute('KW')` after countries are seeded.

Kuwaiti addresses are formatted per the UPU layout: street lines,
`{postcode} {locality}` with a 5-digit postcode left of the locality,
and country.

## Jordan

The bundled `JordanGeographyProvider` supplies the twelve ISO 3166-2
governorates as `State` rows and a single-level administrative
hierarchy. It is selected with
`SeedCountryGeographiesAction::execute('JO')` after countries are seeded.

Jordanian addresses are formatted per the UPU layout: street lines,
`{locality} {postcode}` with a 5-digit postcode, and country.

## Oman

The bundled `OmanGeographyProvider` supplies the eleven ISO 3166-2
governorates as `State` rows and a two-level administrative hierarchy
(governorate → 63 wilayats). It is selected with
`SeedCountryGeographiesAction::execute('OM')` after countries are seeded.

Omani addresses are formatted per the UPU layout: street lines, a
3-digit postcode on its own line above the locality, and country.

## United Arab Emirates

The bundled `UnitedArabEmiratesGeographyProvider` supplies the seven
emirates as `State` rows and a single-level administrative hierarchy.
It is selected with `SeedCountryGeographiesAction::execute('AE')`
after countries are seeded.

The UAE has no postcode system — delivery is to P.O. Boxes only — so
the formatter stacks street lines, city, and country with no postcode
line.

## Saudi Arabia

The bundled `SaudiArabiaGeographyProvider` supplies the thirteen ISO
3166-2 regions as `State` rows (codes `01`–`12` and `14`; there is no
region `13`) and a single-level administrative hierarchy. It is
selected with `SeedCountryGeographiesAction::execute('SA')` after
countries are seeded.

Saudi addresses are formatted per the UPU home-delivery layout:
street lines, a 5-digit postcode on its own line above the locality,
and country. Short addresses (`RAGI2929` style) and the separate P.O.
Box layout are not generated.

## Egypt

The bundled `EgyptGeographyProvider` supplies the 27 ISO 3166-2
governorates as `State` rows and a single-level administrative
hierarchy. It is selected with
`SeedCountryGeographiesAction::execute('EG')` after countries are seeded.

Egyptian addresses are formatted per the UPU layout: street lines,
locality, governorate, a 7-digit postcode on its own line, and country.

## South Africa

The bundled `SouthAfricaGeographyProvider` supplies the nine ISO
3166-2 provinces as `State` rows and a single-level administrative
hierarchy. It is selected with
`SeedCountryGeographiesAction::execute('ZA')` after countries are seeded.

South African addresses are formatted per the UPU layout: street
lines, locality, a 4-digit postcode below it, and country. The
province line is omitted when a postcode is present, per the UPU rule.

## Türkiye

The bundled `TurkiyeGeographyProvider` supplies the 81 ISO 3166-2
provinces as `State` rows and a single-level administrative hierarchy.
It is selected with `SeedCountryGeographiesAction::execute('TR')`
after countries are seeded.

Turkish addresses are formatted per the UPU layout: street lines,
`{postcode} {locality}/{province}` with a 5-digit postcode, and
country. Districts (ilçe, 900+) are intentionally not bundled.
Sub-locality postcode suffixes (`06050-01` style) are not generated.

## Pakistan

The bundled `PakistanGeographyProvider` supplies the seven ISO 3166-2
subdivisions as `State` rows — four provinces plus the Islamabad
Capital Territory, Gilgit-Baltistan, and Azad Jammu and Kashmir
(`Azad Kashmir` is aliased) — and a two-level administrative hierarchy
(province/territory → 174 districts). It is selected with
`SeedCountryGeographiesAction::execute('PK')` after countries are seeded.

Districts follow the late-2025 reorganization state: Punjab counts 42
(`Jampur` and `Taunsa` included), Khyber Pakhtunkhwa counts 40
(Chitral split into Lower/Upper plus `Central Dir`, `Paharpur`, and
`Upper Swat` from the October 2025 batch), and Balochistan includes
`Hub`, `Karezat`, and `Surab`. The January/May 2026 Balochistan batch
(`Tump`, Upper Dera Bugti, `Taftan`, `Wadh`, `Barshor`, Quetta
East/West) is intentionally excluded: unlike the clean October 2025
Khyber Pakhtunkhwa adds, its January Quetta City/Saddar and May Quetta
East/West notifications contradict each other. Tehsils are
intentionally not bundled.

Pakistani addresses are formatted per the UPU layout: street lines,
`{locality}-{postcode}` with a 5-digit dash-separated postcode, and
country.

## India

The bundled `IndiaGeographyProvider` supplies the 36 ISO 3166-2 states
and union territories as `State` rows (28 states, 8 union territories
including Ladakh and the merged Dadra and Nagar Haveli and Daman and
Diu) and a single-level administrative hierarchy. It is selected with
`SeedCountryGeographiesAction::execute('IN')` after countries are seeded.

Indian addresses are formatted per the UPU layout: street lines,
locality, state, a 6-digit postcode on its own line, and country.
Districts (700+) and secondary postcodes (`834001-34` style) are
intentionally not bundled.

## United Kingdom

The bundled `UnitedKingdomGeographyProvider` supplies the four nations
(England, Scotland, Wales, Northern Ireland) as areas in a
single-level administrative hierarchy. The 221 ISO 3166-2 subdivisions
remain global `State` rows only; they are not imported as areas. It is
selected with `SeedCountryGeographiesAction::execute('GB')` after
countries are seeded.

British addresses are formatted per the UPU layout: street lines, post
town, the uppercased postcode on its own line, and country. The county
line is omitted when a postcode is present, per the UPU rule.

## Bangladesh

The bundled `BangladeshGeographyProvider` supplies the eight ISO
3166-2 divisions as `State` rows plus all 64 districts, with one
administrative hierarchy: 8 divisions → 64 districts (Dhaka 13,
Chattogram 11, Khulna 10, Rajshahi 8, Rangpur 8, Barishal 6, Sylhet 4,
Mymensingh 4). It is selected with
`SeedCountryGeographiesAction::execute('BD')` after countries are seeded.

Names follow the post-2018 official English spellings (`Barishal`,
`Chattogram`, `Bogura`, `Jashore`, `Cumilla`, `Jhalokati`,
`Netrokona`); seeding also corrects the matching global state rows.
Only divisions link to states; districts are assignable through the
`district` role with their division selected first.

Bangladeshi addresses are formatted per the UPU layout: street lines,
an optional `thana` component, `{locality} - {postcode}` with a
4-digit postcode, and country.

## Morocco

The bundled `MoroccoGeographyProvider` supplies the twelve ISO 3166-2
regions as `State` rows plus all 75 second-level divisions, with one
administrative hierarchy: 12 regions → 62 provinces + 13 prefectures.
It is selected with `SeedCountryGeographiesAction::execute('MA')`
after countries are seeded.

Region names follow ISO (`Marrakech-Safi`, `Fès-Meknès`,
`Tanger-Tétouan-Al Hoceïma`, `L'Oriental`); the `(EH)` Western Sahara
annotations from the global source are stripped, and seeding corrects
the matching global state rows. Only regions link to states;
provinces and prefectures are assignable through the `province` role
with their region selected first.

Moroccan addresses are formatted per the UPU layout: street lines,
`{postcode} {locality}` with a 5-digit postcode left of the locality,
and country.

## China

The bundled `ChinaGeographyProvider` supplies 33 provincial-level
divisions as `State` rows (22 provinces, 5 autonomous regions, 4
municipalities, plus Hong Kong and Macao) and a single-level
administrative hierarchy. It is selected with
`SeedCountryGeographiesAction::execute('CN')` after countries are seeded.

Taiwan carries its own country code (`TW`) with its own postal
system, so it is intentionally not a CN area. Prefecture-level cities,
counties, and districts are intentionally not bundled.

Chinese addresses are formatted per the UPU layout: street lines, an
optional city/district line, `{postcode} {province}` with a 6-digit
postcode, and country.

## Russia

The bundled `RussiaGeographyProvider` supplies the 83 ISO 3166-2
federal subjects as `State` rows (46 oblasts, 21 republics, 9 krais,
4 okrugs, Moscow and Saint Petersburg, and the Jewish Autonomous
Oblast) and a single-level administrative hierarchy. It is selected
with `SeedCountryGeographiesAction::execute('RU')` after countries are
seeded.

Crimea, Sevastopol, and the territories claimed in 2022 are not
ISO-recognized subdivisions and are intentionally absent. Nenets is
typed as an okrug (the global snapshot mistypes it), and krai/okrug
names carry their suffixes per the UPU province list.

Russian addresses are formatted as street lines, locality, subject,
a 6-digit postcode, and country — country last, per the UPU IB
recommendation. Domestic Russian convention prints the postcode after
the country instead; the formatter deliberately deviates.

## Germany

The bundled `GermanyGeographyProvider` supplies the 16 Länder as
`State` rows and a single-level administrative hierarchy. It is
selected with `SeedCountryGeographiesAction::execute('DE')` after
countries are seeded.

State names use German official forms (`Bayern`, `Sachsen`); the
seven common English exonyms (`Bavaria`, `Lower Saxony`, `North
Rhine-Westphalia`, `Rhineland-Palatinate`, `Saxony`, `Saxony-Anhalt`,
`Thuringia`) are kept as aliases.

German addresses are formatted per the UPU layout: street lines,
`{postcode} {locality}` with a 5-digit postcode, and country. No `D-`
prefix is ever added.

## France

The bundled `FranceGeographyProvider` supplies the 18 regions (13
metropolitan, 5 overseas) as `State` rows in a single-level
administrative hierarchy. The 101 departments and overseas
collectivities remain global `State` rows only; they are not imported
as areas. It is selected with
`SeedCountryGeographiesAction::execute('FR')` after countries are seeded.

French addresses are formatted per the UPU layout: street lines,
`{postcode} {locality}` with a 5-digit postcode, and country. CEDEX
suffixes are not generated.

## Italy

The bundled `ItalyGeographyProvider` supplies the 20 regions as
`State` rows and a single-level administrative hierarchy. Provinces
are intentionally not bundled: Friuli-Venezia Giulia replaced its
provinces with regional entities, Sardinia restructured twice in a
decade, and Sicily uses consortia, so no stable province layer exists
to ship. It is selected with
`SeedCountryGeographiesAction::execute('IT')` after countries are seeded.

Region names use Italian official forms (`Toscana`, `Sicilia`); the
eight common English exonyms (`Piedmont`, `Aosta Valley`, `Lombardy`,
`Trentino-South Tyrol`, `Tuscany`, `Apulia`, `Sicily`, `Sardinia`)
are kept as aliases.

Italian addresses are formatted per the UPU layout: street lines,
`{postcode} {locality} {province}` with a 5-digit postcode, and
country. The two-letter province abbreviation comes from the optional
`province_code` address component and is omitted when absent.

## Japan

The bundled `JapanGeographyProvider` supplies the 47 prefectures as
`State` rows and a single-level administrative hierarchy. It is
selected with `SeedCountryGeographiesAction::execute('JP')` after
countries are seeded.

Prefecture names use unmacroned romanization (`Hokkaido`, `Kyoto`,
`Osaka`) per the UPU prefecture list. Cities, wards, and sub-locality
divisions are intentionally not bundled.

Japanese addresses are formatted per the UPU western layout: street
lines, `{city}, {prefecture}`, the `NNN-NNNN` postcode on its own
line, and country.

## United States

The bundled `UnitedStatesGeographyProvider` supplies 56 states,
districts, and territories as `State` rows (50 states, the District
of Columbia, American Samoa, Guam, the Northern Mariana Islands,
Puerto Rico, and the U.S. Virgin Islands) and a single-level
administrative hierarchy. It is selected with
`SeedCountryGeographiesAction::execute('US')` after countries are seeded.

The military postal regions (`AA`, `AE`, `AP`) and the Minor Outlying
Islands (`UM`) are postal constructs, not addressable geography, and
are intentionally not areas.

American addresses are formatted per USPS Publication 28: street
lines, `{locality} {ST} {ZIP}` with the state abbreviation resolved
from a full-name map (already-abbreviated values pass through
uppercased), and country.

## Spain

The bundled `SpainGeographyProvider` supplies the 17 autonomous
communities plus Ceuta and Melilla as `State` rows, with all 50
provinces, and one administrative hierarchy: 19 communities/cities →
50 provinces. It is selected with
`SeedCountryGeographiesAction::execute('ES')` after countries are seeded.

Community names use short English forms (`Extremadura`, `Asturias`,
`Murcia`, `Madrid`) while provinces keep official local spellings
(`A Coruña`, `Bizkaia`, `Gipuzkoa`, `Araba`, `Ourense`, `Illes
Balears`). Only communities link to states; provinces are assignable
through the `province` role with their community selected first.

Spanish addresses are formatted per the UPU layout: street lines,
`{postcode} {locality}` with a 5-digit postcode, the province on its
own line, and country.

## Poland

The bundled `PolandGeographyProvider` supplies the 16 voivodeships as
`State` rows and a single-level administrative hierarchy. It is
selected with `SeedCountryGeographiesAction::execute('PL')` after
countries are seeded.

Voivodeship names use standard English exonyms (`Mazovia`, `Lesser
Poland`) since the official Polish forms are adjectives, not
standalone proper nouns. Powiats and gminas are intentionally not
bundled.

Polish addresses are formatted per the UPU layout: street lines,
`{postcode} {locality}` with an `NN-NNN` postcode, and country.

## Netherlands

The bundled `NetherlandsGeographyProvider` supplies the 12 provinces
as `State` rows and a single-level administrative hierarchy. It is
selected with `SeedCountryGeographiesAction::execute('NL')` after
countries are seeded.

Province names use Dutch official forms (`Noord-Brabant`,
`Noord-Holland`, `Zuid-Holland`). Municipalities are intentionally
not bundled.

Dutch addresses are formatted per the UPU layout: street lines,
`{postcode}  {locality}` with an uppercased `NNNN LL` postcode and
two spaces before the locality, and country.

## Nigeria

The bundled `NigeriaGeographyProvider` supplies the 36 states plus the
Abuja Federal Capital Territory as `State` rows and a single-level
administrative hierarchy. It is selected with
`SeedCountryGeographiesAction::execute('NG')` after countries are seeded.

Nigerian addresses are formatted per the UPU layout: street lines,
`{locality} {postcode}` with a 6-digit postcode, the state on its own
line, and country. LGAs are intentionally not bundled.

## Ethiopia

The bundled `EthiopiaGeographyProvider` supplies 14 regions and city
administrations as `State` rows and a single-level administrative
hierarchy. It is selected with
`SeedCountryGeographiesAction::execute('ET')` after countries are seeded.

The Southern Nations, Nationalities, and Peoples' Region was dissolved
in August 2023 (split into Sidama, Southwest, South, and Central
Ethiopia). Seeding deletes any `SN` straggler rows, and the bundled
state data no longer ships the code. South Ethiopia (`SE`) and Central
Ethiopia (`CE`) have no ISO codes yet; those codes are provisional
and will be updated when ISO assigns them.

Ethiopian addresses are formatted per the UPU layout: street lines,
`{postcode} {locality}` with a 4-digit postcode, and country.

## Democratic Republic of the Congo

The bundled `DemocraticRepublicOfCongoGeographyProvider` supplies the
26 provinces as `State` rows and a single-level administrative
hierarchy. It is selected with
`SeedCountryGeographiesAction::execute('CD')` after countries are seeded.

Congolese addresses are formatted per the UPU layout: street lines,
an optional commune line, `{postcode} {province}` with a 7-digit
postcode, and country.

## Tanzania

The bundled `TanzaniaGeographyProvider` supplies the 31 regions
(including Songwe, split from Mbeya in 2016) as `State` rows and a
single-level administrative hierarchy. It is selected with
`SeedCountryGeographiesAction::execute('TZ')` after countries are seeded.

Tanzanian addresses are formatted per the UPU layout: street lines,
`{postcode} {locality}` with a 5-digit postcode, the region on its
own line, and country. Districts and wards are intentionally not
bundled.

## Kenya

The bundled `KenyaGeographyProvider` supplies the 47 counties as
`State` rows and a single-level administrative hierarchy. It is
selected with `SeedCountryGeographiesAction::execute('KE')` after
countries are seeded.

Kenyan addresses are formatted per the UPU postal layout: street or
P.O. Box lines, the 5-digit postcode on its own line, then the town,
and country. The county line is omitted when a postcode is present.

## Sudan

The bundled `SudanGeographyProvider` supplies the 18 states as `State`
rows and a single-level administrative hierarchy. It is selected with
`SeedCountryGeographiesAction::execute('SD')` after countries are seeded.

Sudanese addresses are formatted per the UPU layout: street lines, a
5-digit postcode on its own line above the locality, and country.

## Uganda

The bundled `UgandaGeographyProvider` supplies the four regions as
`State` rows in a single-level administrative hierarchy. The 130+
districts change almost yearly (new ones are split off regularly), so
they are intentionally not bundled — any snapshot would be stale on
arrival. It is selected with
`SeedCountryGeographiesAction::execute('UG')` after countries are seeded.

Ugandan addresses are formatted per the UPU layout: street lines,
`{postcode} {locality}` with a 5-digit postcode, and country.

## Algeria

The bundled `AlgeriaGeographyProvider` supplies the 58 wilayas
(including codes `49`–`58`, created in 2019) as `State` rows and a
single-level administrative hierarchy. It is selected with
`SeedCountryGeographiesAction::execute('DZ')` after countries are seeded.

Wilaya names use French official forms (`Alger`, not `Algiers`).
Communes are intentionally not bundled.

Algerian addresses are formatted per the UPU layout: street lines,
`{postcode} {locality}` with a 5-digit postcode, and country.

## Brazil

The bundled `BrazilGeographyProvider` supplies the 26 states plus the
Distrito Federal as `State` rows and a single-level administrative
hierarchy. It is selected with
`SeedCountryGeographiesAction::execute('BR')` after countries are seeded.

Municipalities (5,500+) are intentionally not bundled.

Brazilian addresses are formatted per the UPU layout: street lines,
`{locality} - {ST}` with the two-letter state abbreviation resolved
from a full-name map, the `NNNNN-NNN` postcode on its own line, and
country.

## Mexico

The bundled `MexicoGeographyProvider` supplies the 32 federal
entities as `State` rows (all typed `state`, including Ciudad de
México, which has been state-equivalent since 2016) and a
single-level administrative hierarchy. It is selected with
`SeedCountryGeographiesAction::execute('MX')` after countries are seeded.

Municipalities are intentionally not bundled.

Mexican addresses are formatted per the UPU layout: street lines,
`{postcode} {locality}, {abbrev}` with the state abbreviation from
the UPU list (`CDMX`, `EDOMEX`, `Q. ROO`, `TAMPS`), and country.

## Canada

The bundled `CanadaGeographyProvider` supplies the 10 provinces plus
the 3 territories as `State` rows and a single-level administrative
hierarchy. It is selected with
`SeedCountryGeographiesAction::execute('CA')` after countries are seeded.

Canadian addresses are formatted per the UPU layout: street lines,
`{locality} {PR} {postcode}` with the two-letter province abbreviation
and uppercased `ANA NAN` postcode, and country.

## Australia

The bundled `AustraliaGeographyProvider` supplies the 6 states plus
the 2 mainland territories as `State` rows and a single-level
administrative hierarchy. It is selected with
`SeedCountryGeographiesAction::execute('AU')` after countries are seeded.

External territories (Norfolk Island, Christmas Island, Cocos
Islands) carry their own postcodes and are intentionally not areas.

Australian addresses are formatted per the UPU layout: street lines,
`{locality}  {ST}  {postcode}` with two spaces between each part, and
country.

## Argentina

The bundled `ArgentinaGeographyProvider` supplies the 23 provinces
plus the Autonomous City of Buenos Aires as `State` rows and a
single-level administrative hierarchy. It is selected with
`SeedCountryGeographiesAction::execute('AR')` after countries are seeded.

Departments and municipalities are intentionally not bundled.

Argentine addresses are formatted per the UPU layout: street lines,
`{CPA} {locality}` with the `XNNNNLLL` postcode left of the locality,
and country.

## Colombia

The bundled `ColombiaGeographyProvider` supplies the 32 departments
plus Bogotá D.C. as `State` rows and a single-level administrative
hierarchy. It is selected with
`SeedCountryGeographiesAction::execute('CO')` after countries are seeded.

Municipalities are intentionally not bundled.

Colombian addresses are formatted per the UPU layout: street lines,
`{locality} {postcode}` with a 6-digit postcode, the department on
its own line, and country.

## Peru

The bundled `PeruGeographyProvider` supplies the 25 regions plus the
Lima metropolitan municipality as `State` rows and a single-level
administrative hierarchy. It is selected with
`SeedCountryGeographiesAction::execute('PE')` after countries are seeded.

Provinces and districts are intentionally not bundled. The `Huánuco`
spelling is corrected at seed.

Peruvian addresses are formatted per the UPU layout: street lines, a
5-digit postcode on its own line, the department on its own line, and
country.

## Vietnam

The bundled `VietnamGeographyProvider` supplies the post-merger 34
provincial-level divisions (28 provinces, 6 municipalities:
Hà Nội, Hải Phòng, Huế, Đà Nẵng, Cần Thơ, Hồ Chí Minh City) as
`State` rows and a single-level administrative hierarchy. It is
selected with `SeedCountryGeographiesAction::execute('VN')` after
countries are seeded.

The June 2025 merger (63 → 34, districts eliminated) is reflected as
shipped; seeding renames `Thừa Thiên-Huế` to `Huế` and retypes Hải
Phòng, Hồ Chí Minh City, and Huế as municipalities. Communes and
wards are intentionally not bundled.

Vietnamese addresses are formatted per the UPU layout: street and
ward lines, `{province} {postcode}` with a 5-digit postcode, and
country.

## Thailand

The bundled `ThailandGeographyProvider` supplies the 76 provinces
plus Bangkok and Pattaya as `State` rows and a single-level
administrative hierarchy. It is selected with
`SeedCountryGeographiesAction::execute('TH')` after countries are seeded.

Districts (amphoe) and sub-districts are intentionally not bundled.

Thai addresses are formatted per the UPU layout: street lines,
`{district}, {province}`, the 5-digit postcode on its own line, and
country.

## Philippines

The bundled `PhilippinesGeographyProvider` supplies the 82 provinces
as areas in a single-level administrative hierarchy, including the
2022 Maguindanao split (`Maguindanao del Norte` / `Maguindanao del
Sur`) and `Davao de Oro`. It is selected with
`SeedCountryGeographiesAction::execute('PH')` after countries are seeded.

The 17 regions stay global `State` rows only (with `Bangsamoro` and
`Cordillera Administrative Region` name corrections at seed): regions
are churny (ARMM→BARMM in 2019, Negros Island Region re-created in
2024 without an ISO code), while provinces are the address-relevant
unit. Cities and barangays are intentionally not bundled.
`Samar` keeps `Western Samar` as an alias.

Filipino addresses are formatted per the UPU layout: street lines,
the municipality on its own line with `{postcode} {province}` below
it for provincial addresses (`{postcode} {municipality}, METRO MANILA`
for Metro Manila, `{postcode} {locality}` when no province is set),
and country.

## South Korea

The bundled `SouthKoreaGeographyProvider` supplies the 17
provincial-level divisions as `State` rows (8 provinces including the
special self-governing Gangwon State, Jeju, and Jeonbuk State — the
2023/2024 official renames, with `Gangwon` and `North Jeolla` aliased
— 6 metropolitan cities, Seoul, and Sejong) and a single-level
administrative hierarchy. It is selected with
`SeedCountryGeographiesAction::execute('KR')` after countries are seeded.

Cities, districts, and dongs are intentionally not bundled.

South Korean addresses are formatted per the UPU layout: street
lines, `{province or city} {postcode}` with a 5-digit postcode, and
country.

## Taiwan

The bundled `TaiwanGeographyProvider` supplies the 22 divisions (6
special municipalities, 3 cities, 13 counties) as `State` rows and a
single-level administrative hierarchy. It is selected with
`SeedCountryGeographiesAction::execute('TW')` after countries are seeded.

Townships, districts, and villages are intentionally not bundled.

Taiwanese addresses are formatted per Chunghwa Post (no UPU sheet is
published for Taiwan): street lines, `{locality} {postcode}` with the
6-digit 3+3 postcode, and country.

## Ukraine

The bundled `UkraineGeographyProvider` supplies the 24 oblasts plus
Kyiv, Sevastopol, and the Autonomous Republic of Crimea as `State`
rows and a single-level administrative hierarchy. It is selected with
`SeedCountryGeographiesAction::execute('UA')` after countries are seeded.

Raions and hromadas are intentionally not bundled. Oblast names use
the ISO adjectival forms (`Kyivska`, `Lvivska`).

Ukrainian addresses are formatted per the UPU layout: street lines,
locality, oblast, a 5-digit postcode on its own line, and country.

## Iraq

The bundled `IraqGeographyProvider` supplies the 19 governorates as
`State` rows and a single-level administrative hierarchy. It is
selected with `SeedCountryGeographiesAction::execute('IQ')` after
countries are seeded.

`Iqlim Kurdistan` is ISO-listed as a region (`IQ-KR`) overlapping
Erbil, Dohuk, Sulaymaniyah, and Halabja — not a governorate. Since
governorates are the address-relevant unit, seeding deletes any `KR`
straggler rows and the bundled state data no longer ships the code.
Halabja (governorate in Kurdistan since 2014, federally since April
2025) has no ISO code yet; `HL` follows UK government usage pending
ISO assignment. Districts are intentionally not bundled.

Iraqi addresses are formatted per the UPU layout: street lines,
`{city}, {governorate}`, the 5-digit postcode on its own line, and
country.

## Ghana

The bundled `GhanaGeographyProvider` supplies the 16 regions
(including the six created in 2019) as `State` rows and a
single-level administrative hierarchy. It is selected with
`SeedCountryGeographiesAction::execute('GH')` after countries are seeded.

Districts are intentionally not bundled.

Ghanaian addresses are formatted per the UPU layout: street or P.O.
Box lines, `{locality} {postcode}` (accepting both short and digital
`GA-183-8164` forms as given), the region on its own line, and
country.

## Angola

The bundled `AngolaGeographyProvider` supplies the 18 ISO provinces
as `State` rows and a single-level administrative hierarchy. It is
selected with `SeedCountryGeographiesAction::execute('AO')` after
countries are seeded.

The September 2024 law creating three more provinces (Icolo e Bengo,
Moxico Leste, and the Cuando/Cubango split, 21 total) is enacted but,
per official sources, not yet implemented — so the shipped 18 track
implemented reality, and the new units will be added once live.
Municipalities are intentionally not bundled.

Angola has no postcode system, so the formatter stacks street lines,
city, and country with no postcode line.

## Cameroon

The bundled `CameroonGeographyProvider` supplies the 10 regions as
`State` rows and a single-level administrative hierarchy. It is
selected with `SeedCountryGeographiesAction::execute('CM')` after
countries are seeded.

Departments and communes are intentionally not bundled.

Cameroon has no postcode system, so the formatter stacks street
lines, city, and country with no postcode line.

## Madagascar

The bundled `MadagascarGeographyProvider` supplies the 6 provinces
as `State` rows and a single-level administrative hierarchy. It is
selected with `SeedCountryGeographiesAction::execute('MG')` after
countries are seeded.

The 23 post-2009 regions have no ISO codes (ISO 3166-2:MG still lists
the 6 former faritany) and are intentionally not bundled; the 6
remain postally relevant since the postcode's first digit routes by
old province.

Malagasy addresses are formatted per the UPU layout: street lines,
`{postcode} {town}` with a 3-digit postcode, and country.

## Afghanistan

The bundled `AfghanistanGeographyProvider` supplies the 34 provinces
as `State` rows and a single-level administrative hierarchy. It is
selected with `SeedCountryGeographiesAction::execute('AF')` after
countries are seeded.

Districts are intentionally not bundled. The `Ghor` and `Kunduz`
spellings are corrected at seed.

Afghan addresses are formatted per the UPU layout: street lines,
`{postcode} {locality}` with a 6-digit postcode, the province on its
own line, and country.

## Mozambique

The bundled `MozambiqueGeographyProvider` supplies the 10 provinces
plus Maputo City as `State` rows and a single-level administrative
hierarchy. It is selected with
`SeedCountryGeographiesAction::execute('MZ')` after countries are seeded.

Districts are intentionally not bundled. `Maputo Province` and
`Maputo City` are disambiguated at seed.

Mozambican addresses are formatted per the UPU layout: street lines,
`{postcode} {locality}` with a 4-digit postcode, the province on its
own line, and country.

## Uzbekistan

The bundled `UzbekistanGeographyProvider` supplies the 12 regions
plus Karakalpakstan and Tashkent City as `State` rows and a
single-level administrative hierarchy. It is selected with
`SeedCountryGeographiesAction::execute('UZ')` after countries are seeded.

Region names use official Uzbek Latin forms (`Qashqadaryo`,
`Samarqand`, `Sirdaryo`, `Surxondaryo`, `Navoiy`, `Xorazm`).
`Tashkent Region` and `Tashkent City` are disambiguated at seed.
Districts are intentionally not bundled.

Uzbek addresses are formatted per the UPU layout: street lines,
`{postcode}, {locality}` with a 6-digit postcode, the region on its
own line (omitted when it duplicates the city), and country.

## Myanmar

The bundled `MyanmarGeographyProvider` supplies the 7 regions, 7
states, and Naypyidaw as `State` rows and a single-level
administrative hierarchy. It is selected with
`SeedCountryGeographiesAction::execute('MM')` after countries are seeded.

Townships and districts are intentionally not bundled.

Myanmar addresses are formatted per the UPU layout: street lines,
`{locality}, {postcode}` with a 7-digit postcode, the region or state
on its own line, and country.

## Cambodia

The bundled `CambodiaGeographyProvider` supplies the 24 provinces plus
Phnom Penh municipality as `State` rows and a single-level
administrative hierarchy. It is selected with
`SeedCountryGeographiesAction::execute('KH')` after countries are seeded.

Code `18` seeds the official `Preah Sihanouk` name with `Sihanoukville`
kept as an alternative area name. Districts (srok/khan) and communes
are intentionally not bundled.

Cambodian addresses are formatted per the UPU layout: street lines,
the city above `{province} {postcode}` with a 6-digit postcode, and
country.

## Laos

The bundled `LaosGeographyProvider` supplies the 17 provinces plus the
Vientiane Prefecture as `State` rows and a single-level administrative
hierarchy. It is selected with
`SeedCountryGeographiesAction::execute('LA')` after countries are seeded.

The Vientiane province (`VI`) and Vientiane Prefecture (`VT`) are
separate areas sharing a name; districts (muang) are intentionally not
bundled.

Laotian addresses are formatted per the UPU layout: street lines,
`{postcode} {locality}` with a 5-digit postcode, the province on its
own line when both are set, and country.

## Timor-Leste

The bundled `TimorLesteGeographyProvider` supplies the 13 ISO 3166-2
municipalities (Oecusse is a special administrative region) plus
Atauro — split from Dili in 2022 with provisional code `AT`, since
ISO has not assigned one yet — as `State` rows and a single-level
administrative hierarchy. It is selected with
`SeedCountryGeographiesAction::execute('TL')` after countries are seeded.

Timor-Leste joined ASEAN as the 11th member in October 2025.
Administrative posts are intentionally not bundled.

Timorese addresses are formatted per the UPU layout: street lines,
`{locality} {postcode}` with a `TL` + 5-digit postcode, and country.
Distinct city and municipality join as `{city} - {municipality}
{postcode}`; equal values print once.

## Numeric state codes

Bahrain, Italy, South Korea, Saudi Arabia, Türkiye, Morocco, France,
Japan, Poland, Kenya, Tanzania, Algeria, Thailand, Vietnam, Ukraine,
and Myanmar use numeric ISO subdivision codes
at the state-mapping level. PHP casts numeric-string array keys to
int, so
`stateAreaMappings()` returns int keys for those countries and the
contract documents `array<int|string, ...>`. `linkStateAreas()`
stringifies keys before querying, so seeding behaves identically on
every database driver.

## Seed Command

```bash
php artisan address:seed-countries
php artisan commerce:seed-currencies
php artisan commerce:seed-languages
php artisan commerce:seed-timezones
php artisan address:seed-country-references
```

This is idempotent — running it multiple times is safe.

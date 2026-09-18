---
title: Country Data
---

# Country Data

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
governorates as `State` rows and a single-level administrative
hierarchy. It is selected with
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
(`Azad Kashmir` is aliased) — and a single-level administrative
hierarchy. It is selected with
`SeedCountryGeographiesAction::execute('PK')` after countries are seeded.

Pakistani addresses are formatted per the UPU layout: street lines,
`{locality}-{postcode}` with a 5-digit dash-separated postcode, and
country. Districts (around 170 and still being split) are
intentionally not bundled.

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

## Numeric state codes

Bahrain, Italy, Saudi Arabia, Türkiye, Morocco, France, Japan,
Poland, Kenya, Tanzania, and Algeria use numeric ISO subdivision codes
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

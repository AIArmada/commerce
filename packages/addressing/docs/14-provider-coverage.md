---
title: Provider Coverage Registry
---

# Provider Coverage Registry

At-a-glance depth and dataset status for every bundled geography
provider. Update the row whenever a provider gains a level, changes
counts, or changes scope. See [Country Data](./05-country-data.md)
for per-country narrative and [Provider Authoring](./13-provider-authoring.md)
for how to add a level.

Current shape: 121 providers — 2 dual-hierarchy, 1 depth-3, 6 depth-2,
112 depth-1.

## Legend

- **States** — `State` rows the provider seeds (`stateDefinitions`).
- **Mapped** — state-to-area links (`stateAreaMappings`).
- **Rows** — data rows in the country's `*-address-areas.csv`.
- **Depth** — tiers in the administrative hierarchy (`dual a+b` lists
  each hierarchy for two-hierarchy providers).
- **Hierarchy** — hierarchy key with its level chain.
- **Roles** — assignable area roles the provider defines (`—` means the
  state *is* the area and there is nothing to assign).
- **Status** — `Complete` (at designed depth), `L1` (level-1 only),
  plus scope notes. `candidate` marks a researched next tier;
  `out of scope` marks a deliberate exclusion.

## Registry

| Country | Code | States | Mapped | Rows | Depth | Hierarchy | Roles | Status |
|---|---|---:|:---:|:---:|:---:|---|---|---|
| Afghanistan | AF | 34 | 34 | 34 | 1 | administrative: province | — | L1; districts (~400) blocked, stale data |
| Algeria | DZ | 58 | 58 | 58 | 1 | administrative: wilaya | — | L1; dairas (~550) candidate |
| Angola | AO | 18 | 18 | 18 | 1 | administrative: province | — | L1; 2024 split unimplemented |
| Argentina | AR | 24 | 24 | 24 | 1 | administrative: province | — | L1 — 23 provinces + CABA |
| Armenia | AM | 11 | 11 | 11 | 1 | administrative: region | — | L1 — 10 regions + Yerevan |
| Australia | AU | 8 | 8 | 8 | 1 | administrative: state | — | L1 — 6 states + 2 territories; externals global-only |
| Azerbaijan | AZ | 78 | 78 | 78 | 1 | administrative: district | — | L1 — 66 districts + 11 municipalities + Nakhchivan AR |
| Bahrain | BH | 4 | 4 | 4 | 1 | administrative: governorate | — | L1; no admin tier-2, blocks are postal |
| Bangladesh | BD | 72 | 8 | 72 | 2 | administrative: division > district | district | Complete to district; upazilas out of scope |
| Benin | BJ | 12 | 12 | 12 | 1 | administrative: department | — | L1 |
| Bhutan | BT | 20 | 20 | 20 | 1 | administrative: district | — | L1 — 20 dzongkhags; gewogs out of scope |
| Botswana | BW | 16 | 16 | 16 | 1 | administrative: district | — | L1 — 10 districts + 2 cities + 4 towns |
| Brazil | BR | 27 | 27 | 27 | 1 | administrative: state | — | L1 — 26 states + DF |
| Brunei | BN | 4 | 4 | 43 | 2 | administrative: district > mukim | mukim | Complete; kampungs via components |
| Burkina Faso | BF | 58 | 58 | 58 | 1 | administrative: region | — | L1 — 13 regions + 45 provinces flat; depth-2 candidate |
| Burundi | BI | 18 | 18 | 18 | 1 | administrative: province | — | L1 |
| Cambodia | KH | 25 | 25 | 25 | 1 | administrative: province | — | L1 — 24 provinces + Phnom Penh; districts/communes out of scope |
| Cameroon | CM | 10 | 10 | 10 | 1 | administrative: region | — | L1 |
| Canada | CA | 13 | 13 | 13 | 1 | administrative: province | — | L1 — 10 provinces + 3 territories |
| Cape Verde | CV | 24 | 24 | 24 | 1 | administrative: municipality | — | L1 — 22 municipalities + 2 island groups |
| Central African Republic | CF | 17 | 17 | 17 | 1 | administrative: prefecture | — | L1 — 15 prefectures + Bangui + Nana-Grébizi |
| Chad | TD | 23 | 23 | 23 | 1 | administrative: province | — | L1 |
| China | CN | 33 | 33 | 33 | 1 | administrative: province | — | L1; Taiwan separate (TW) |
| Colombia | CO | 33 | 33 | 33 | 1 | administrative: department | — | L1 — 32 departments + Bogotá |
| Comoros | KM | 3 | 3 | 3 | 1 | administrative: island | — | L1 — 3 islands |
| Congo | CG | 12 | 12 | 12 | 1 | administrative: department | — | L1 |
| Cyprus | CY | 6 | 6 | 6 | 1 | administrative: district | — | L1 |
| Djibouti | DJ | 6 | 6 | 6 | 1 | administrative: region | — | L1 — 5 regions + Djibouti City |
| DR Congo | CD | 26 | 26 | 26 | 1 | administrative: province | — | L1 |
| Egypt | EG | 27 | 27 | 27 | 1 | administrative: governorate | — | L1; markaz (~350) candidate, Arabic-first |
| Equatorial Guinea | GQ | 10 | 10 | 10 | 1 | administrative: region | — | L1 — 2 regions + 8 provinces flat |
| Eritrea | ER | 6 | 6 | 6 | 1 | administrative: region | — | L1 |
| Eswatini | SZ | 4 | 4 | 4 | 1 | administrative: region | — | L1 |
| Ethiopia | ET | 14 | 14 | 14 | 1 | administrative: region | — | L1; SN dissolved on seed |
| France | FR | 18 | 18 | 18 | 1 | administrative: region | — | L1 |
| Gabon | GA | 9 | 9 | 9 | 1 | administrative: province | — | L1 |
| Gambia | GM | 6 | 6 | 6 | 1 | administrative: division | — | L1 — 5 divisions + Banjul |
| Georgia | GE | 12 | 12 | 12 | 1 | administrative: region | — | L1 — 9 regions + 2 ARs + Tbilisi |
| Germany | DE | 16 | 16 | 16 | 1 | administrative: state | — | L1 |
| Ghana | GH | 16 | 16 | 16 | 1 | administrative: region | — | L1 |
| Guinea | GN | 41 | 41 | 41 | 1 | administrative: administrative_region | — | L1 — 7 regions + Conakry + 33 prefectures flat; depth-2 candidate |
| Guinea-Bissau | GW | 12 | 12 | 12 | 1 | administrative: province | — | L1 — 3 provinces + 8 regions + Bissau sector |
| Hong Kong | HK | 18 | 18 | 18 | 1 | administrative: district | — | L1 — 18 districts |
| India | IN | 36 | 36 | 36 | 1 | administrative: state | — | L1 — 28 states + 8 UTs; districts (~780) candidate |
| Indonesia | ID | 38 | 38 | 7837 | 3 | administrative: province > regency > district | district, regency | Complete to kecamatan; desa (~83k) out of scope |
| Iran | IR | 31 | 31 | 31 | 1 | administrative: province | — | L1 — 31 ostans; counties out of scope |
| Iraq | IQ | 19 | 19 | 19 | 1 | administrative: governorate | — | L1 incl. Halabja; KR region removed; qada (~120) candidate |
| Israel | IL | 6 | 6 | 6 | 1 | administrative: district | — | L1 — 6 districts; sub-districts out of scope |
| Italy | IT | 20 | 20 | 20 | 1 | administrative: region | — | L1 |
| Ivory Coast | CI | 14 | 14 | 14 | 1 | administrative: district | — | L1 — 12 districts + Abidjan/Yamoussoukro |
| Japan | JP | 47 | 47 | 47 | 1 | administrative: prefecture | — | L1; municipalities candidate |
| Jordan | JO | 12 | 12 | 12 | 1 | administrative: governorate | — | L1; liwa (~50) candidate |
| Kazakhstan | KZ | 20 | 20 | 20 | 1 | administrative: region | — | L1 — 17 regions + 3 cities |
| Kenya | KE | 47 | 47 | 47 | 1 | administrative: county | — | L1 |
| Kuwait | KW | 6 | 6 | 6 | 1 | administrative: governorate | — | L1; postal areas (~100) candidate |
| Kyrgyzstan | KG | 9 | 9 | 9 | 1 | administrative: region | — | L1 — 7 regions + Bishkek/Osh |
| Laos | LA | 18 | 18 | 18 | 1 | administrative: province | — | L1 — 17 provinces + Vientiane Prefecture; muang out of scope |
| Lebanon | LB | 8 | 8 | 8 | 1 | administrative: governorate | — | L1 — 8 governorates; cazas out of scope |
| Lesotho | LS | 10 | 10 | 10 | 1 | administrative: district | — | L1 |
| Liberia | LR | 15 | 15 | 15 | 1 | administrative: county | — | L1 |
| Libya | LY | 22 | 22 | 22 | 1 | administrative: popularate | — | L1 — 22 sha'biyat |
| Madagascar | MG | 6 | 6 | 6 | 1 | administrative: province | — | L1; codeless 23 regions omitted |
| Malawi | MW | 31 | 31 | 31 | 1 | administrative: region | — | L1 — 3 regions + 28 districts flat; depth-2 candidate |
| Malaysia | MY | 16 | 16 | 1842 | dual 2+4 | postal: region > locality; administrative: region > division > district > subdivision | administrative_district, administrative_division, administrative_subdivision, postal_locality | Complete; postal CSVs bundled |
| Maldives | MV | 21 | 21 | 21 | 1 | administrative: atoll | — | L1 — 20 atolls + Addu City |
| Mali | ML | 11 | 11 | 11 | 1 | administrative: region | — | L1 — 10 regions + Bamako |
| Mauritania | MR | 15 | 15 | 15 | 1 | administrative: region | — | L1 |
| Mauritius | MU | 12 | 12 | 12 | 1 | administrative: district | — | L1 — 9 districts + 3 dependencies |
| Mexico | MX | 32 | 32 | 32 | 1 | administrative: state | — | L1 |
| Mongolia | MN | 22 | 22 | 22 | 1 | administrative: province | — | L1 — 21 aimags + Ulaanbaatar |
| Morocco | MA | 87 | 12 | 87 | 2 | administrative: region > province | province | Complete to province; communes (~1,500) out of scope |
| Mozambique | MZ | 11 | 11 | 11 | 1 | administrative: province | — | L1 — 10 provinces + Maputo City |
| Myanmar | MM | 15 | 15 | 15 | 1 | administrative: region | — | L1 — 7 regions + 7 states + Naypyidaw |
| Namibia | NA | 14 | 14 | 14 | 1 | administrative: region | — | L1 |
| Nepal | NP | 7 | 7 | 7 | 1 | administrative: province | — | L1 — 7 provinces; districts out of scope |
| Netherlands | NL | 12 | 12 | 12 | 1 | administrative: province | — | L1 |
| Niger | NE | 8 | 8 | 8 | 1 | administrative: region | — | L1 — 7 regions + Niamey |
| Nigeria | NG | 37 | 37 | 37 | 1 | administrative: state | — | L1 — 36 states + FCT; LGAs (774) candidate |
| North Korea | KP | 13 | 13 | 13 | 1 | administrative: province | — | L1 — 9 provinces + 4 cities; no postcode |
| Oman | OM | 11 | 11 | 74 | 2 | administrative: governorate > wilayat | wilayat | Complete — 11 governorates + 63 wilayats |
| Pakistan | PK | 7 | 7 | 181 | 2 | administrative: province > district | district | Complete to district (174, late-2025); tehsils out; 2026 Balochistan batch excluded |
| Palestine | PS | 16 | 16 | 16 | 1 | administrative: governorate | — | L1 |
| Peru | PE | 26 | 26 | 26 | 1 | administrative: region | — | L1 — 25 regions + Lima |
| Philippines | PH | 99 | 82 | 82 | 1 | administrative: province | — | L1 — provinces; regions global-only; municipalities/barangay out of scope |
| Poland | PL | 16 | 16 | 16 | 1 | administrative: voivodeship | — | L1 |
| Qatar | QA | 8 | 8 | 8 | 1 | administrative: municipality | — | L1; zones (~98) candidate |
| Russia | RU | 83 | 83 | 83 | 1 | administrative: subject | — | L1 |
| Rwanda | RW | 5 | 5 | 5 | 1 | administrative: province | — | L1 — 4 provinces + Kigali |
| Sao Tome and Principe | ST | 7 | 7 | 7 | 1 | administrative: district | — | L1 — 6 districts + Príncipe AR |
| Saudi Arabia | SA | 13 | 13 | 13 | 1 | administrative: region | — | L1; governorates (~130) candidate, Arabic-first |
| Senegal | SN | 14 | 14 | 14 | 1 | administrative: region | — | L1 |
| Seychelles | SC | 27 | 27 | 27 | 1 | administrative: district | — | L1 — 27 districts |
| Sierra Leone | SL | 5 | 5 | 5 | 1 | administrative: province | — | L1 — 4 provinces + Western Area |
| Singapore | SG | 5 | 5 | 174 | dual 2+2 | postal: postal_district > postal_sector; administrative: region > planning_area | planning_area, postal_district, postal_sector, region | Complete; postcodes via OneMap |
| Somalia | SO | 18 | 18 | 18 | 1 | administrative: region | — | L1 |
| South Africa | ZA | 9 | 9 | 9 | 1 | administrative: province | — | L1 |
| South Korea | KR | 17 | 17 | 17 | 1 | administrative: province | — | L1 |
| South Sudan | SS | 10 | 10 | 10 | 1 | administrative: state | — | L1 — 10 states |
| Spain | ES | 69 | 19 | 69 | 2 | administrative: community > province | province | Complete — communities + 50 provinces |
| Sri Lanka | LK | 34 | 34 | 34 | 1 | administrative: province | — | L1 — 9 provinces + 25 districts flat; depth-2 candidate |
| Sudan | SD | 18 | 18 | 18 | 1 | administrative: state | — | L1 |
| Syria | SY | 14 | 14 | 14 | 1 | administrative: province | — | L1 |
| Taiwan | TW | 22 | 22 | 22 | 1 | administrative: division | — | L1 |
| Tajikistan | TJ | 5 | 5 | 5 | 1 | administrative: region | — | L1 — 2 regions + GBAR + Dushanbe + republican districts |
| Tanzania | TZ | 31 | 31 | 31 | 1 | administrative: region | — | L1 |
| Thailand | TH | 78 | 78 | 78 | 1 | administrative: province | — | L1 — 76 provinces + Bangkok/Pattaya; amphoe candidate |
| Timor-Leste | TL | 14 | 14 | 14 | 1 | administrative: municipality | — | L1 — 13 ISO + Atauro (AT provisional); admin posts out of scope |
| Togo | TG | 5 | 5 | 5 | 1 | administrative: region | — | L1 |
| Tunisia | TN | 24 | 24 | 24 | 1 | administrative: governorate | — | L1 |
| Turkmenistan | TM | 6 | 6 | 6 | 1 | administrative: region | — | L1 — 5 regions + Ashgabat |
| Türkiye | TR | 81 | 81 | 81 | 1 | administrative: province | — | L1; ilçe (~970) candidate (TÜİK) |
| Uganda | UG | 4 | 4 | 4 | 1 | administrative: region | — | L1 — regions only; districts excluded (volatile) |
| Ukraine | UA | 27 | 27 | 27 | 1 | administrative: oblast | — | L1 — 24 oblasts + Kyiv/Sevastopol/Crimea |
| United Arab Emirates | AE | 7 | 7 | 7 | 1 | administrative: emirate | — | L1; no official tier-2 |
| United Kingdom | GB | 4 | 4 | 4 | 1 | administrative: nation | — | L1 — 4 nations; 221 subdivisions global-only |
| United States | US | 56 | 56 | 56 | 1 | administrative: state | — | L1 — 50 + DC + 5 territories; AA/AE/AP/UM global-only |
| Uzbekistan | UZ | 14 | 14 | 14 | 1 | administrative: region | — | L1; tuman (~175) candidate |
| Vietnam | VN | 34 | 34 | 34 | 1 | administrative: province | — | L1 post-merger 34; communes candidate |
| Yemen | YE | 22 | 22 | 22 | 1 | administrative: governorate | — | L1 — 21 governorates + Amanat Al Asimah |
| Zambia | ZM | 10 | 10 | 10 | 1 | administrative: province | — | L1 |
| Zimbabwe | ZW | 10 | 10 | 10 | 1 | administrative: province | — | L1 |

## Maintaining This File

Each column traces to one place:

- **States** — count of `['name' =>` entries in the provider's
  `stateDefinitions()`.
- **Mapped** — entries in the provider's `stateAreaMappings()`.
- **Rows** — data rows in `resources/geography/<slug>-address-areas.csv`.
- **Depth / Hierarchy / Roles** — the provider's `addressHierarchies()`.
- **Status** — hand-curated. Keep it to one line: what is bundled,
  the next researched tier (`candidate`), or the reason deeper data
  is excluded (`out of scope`, `blocked`, or the volatility note).

When a batch adds a level, update this row, the country's section in
[Country Data](./05-country-data.md), and the CSV line in
`resources/geography/README.md` in the same pass.

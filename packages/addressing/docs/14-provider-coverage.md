---
title: Provider Coverage Registry
---

# Provider Coverage Registry

At-a-glance depth and dataset status for every bundled geography
provider. Update the row whenever a provider gains a level, changes
counts, or changes scope. See [Country Data](./05-country-data.md)
for per-country narrative and [Provider Authoring](./13-provider-authoring.md)
for how to add a level.

Current shape: 229 providers — 2 dual-hierarchy, 1 depth-3, 6 depth-2,
220 depth-1.

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
| Aland | AX | 16 | 16 | 16 | 1 | administrative: municipality | — | L1 — 16 municipalities |
| Albania | AL | 12 | 12 | 12 | 1 | administrative: county | — | L1 |
| Algeria | DZ | 58 | 58 | 58 | 1 | administrative: wilaya | — | L1; dairas (~550) candidate |
| American Samoa | AS | 5 | 5 | 5 | 1 | administrative: district | — | L1 — 3 districts + 2 atolls |
| Andorra | AD | 7 | 7 | 7 | 1 | administrative: parish | — | L1 |
| Angola | AO | 18 | 18 | 18 | 1 | administrative: province | — | L1; 2024 split unimplemented |
| Anguilla | AI | 14 | 14 | 14 | 1 | administrative: district | — | L1 |
| Antigua and Barbuda | AG | 8 | 8 | 8 | 1 | administrative: parish | — | L1 — 6 parishes + Barbuda/Redonda |
| Argentina | AR | 24 | 24 | 24 | 1 | administrative: province | — | L1 — 23 provinces + CABA |
| Armenia | AM | 11 | 11 | 11 | 1 | administrative: region | — | L1 — 10 regions + Yerevan |
| Aruba | AW | 9 | 9 | 9 | 1 | administrative: region | — | L1 — 8 regions + Oranjestad |
| Australia | AU | 8 | 8 | 8 | 1 | administrative: state | — | L1 — 6 states + 2 territories; externals global-only |
| Austria | AT | 9 | 9 | 9 | 1 | administrative: state | — | L1 — 9 states |
| Azerbaijan | AZ | 78 | 78 | 78 | 1 | administrative: district | — | L1 — 66 districts + 11 municipalities + Nakhchivan AR |
| Bahamas | BS | 32 | 32 | 32 | 1 | administrative: district | — | L1 — 31 districts + New Providence |
| Bahrain | BH | 4 | 4 | 4 | 1 | administrative: governorate | — | L1; no admin tier-2, blocks are postal |
| Bangladesh | BD | 72 | 8 | 72 | 2 | administrative: division > district | district | Complete to district; upazilas out of scope |
| Barbados | BB | 11 | 11 | 11 | 1 | administrative: parish | — | L1 |
| Belarus | BY | 7 | 7 | 7 | 1 | administrative: oblast | — | L1 — 6 oblasts + Minsk; oblast/city Minsk share a name |
| Belgium | BE | 13 | 13 | 13 | 1 | administrative: region | — | L1 — 3 regions + 10 provinces flat; depth-2 candidate |
| Belize | BZ | 6 | 6 | 6 | 1 | administrative: district | — | L1 |
| Benin | BJ | 12 | 12 | 12 | 1 | administrative: department | — | L1 |
| Bermuda | BM | 9 | 9 | 9 | 1 | administrative: municipality | — | L1 |
| Bhutan | BT | 20 | 20 | 20 | 1 | administrative: district | — | L1 — 20 dzongkhags; gewogs out of scope |
| Bolivia | BO | 9 | 9 | 9 | 1 | administrative: department | — | L1 |
| Bosnia and Herzegovina | BA | 3 | 3 | 3 | 1 | administrative: entity | — | L1 — 2 entities + Brčko District |
| Botswana | BW | 16 | 16 | 16 | 1 | administrative: district | — | L1 — 10 districts + 2 cities + 4 towns |
| Brazil | BR | 27 | 27 | 27 | 1 | administrative: state | — | L1 — 26 states + DF |
| Brunei | BN | 4 | 4 | 43 | 2 | administrative: district > mukim | mukim | Complete; kampungs via components |
| Bulgaria | BG | 28 | 28 | 28 | 1 | administrative: district | — | L1 |
| Burkina Faso | BF | 58 | 58 | 58 | 1 | administrative: region | — | L1 — 13 regions + 45 provinces flat; depth-2 candidate |
| Burundi | BI | 18 | 18 | 18 | 1 | administrative: province | — | L1 |
| Cambodia | KH | 25 | 25 | 25 | 1 | administrative: province | — | L1 — 24 provinces + Phnom Penh; districts/communes out of scope |
| Cameroon | CM | 10 | 10 | 10 | 1 | administrative: region | — | L1 |
| Canada | CA | 13 | 13 | 13 | 1 | administrative: province | — | L1 — 10 provinces + 3 territories |
| Cape Verde | CV | 24 | 24 | 24 | 1 | administrative: municipality | — | L1 — 22 municipalities + 2 island groups |
| Caribbean Netherlands | BQ | 3 | 3 | 3 | 1 | administrative: special_municipality | — | L1 — 3 special municipalities |
| Cayman Islands | KY | 3 | 3 | 3 | 1 | administrative: island | — | L1 — 3 islands |
| Central African Republic | CF | 17 | 17 | 17 | 1 | administrative: prefecture | — | L1 — 15 prefectures + Bangui + Nana-Grébizi |
| Chad | TD | 23 | 23 | 23 | 1 | administrative: province | — | L1 |
| Chile | CL | 16 | 16 | 16 | 1 | administrative: region | — | L1 |
| China | CN | 33 | 33 | 33 | 1 | administrative: province | — | L1; Taiwan separate (TW) |
| Colombia | CO | 33 | 33 | 33 | 1 | administrative: department | — | L1 — 32 departments + Bogotá |
| Comoros | KM | 3 | 3 | 3 | 1 | administrative: island | — | L1 — 3 islands |
| Congo | CG | 12 | 12 | 12 | 1 | administrative: department | — | L1 |
| Costa Rica | CR | 7 | 7 | 7 | 1 | administrative: province | — | L1 |
| Croatia | HR | 20 | 20 | 20 | 1 | administrative: county | — | L1 |
| Cuba | CU | 16 | 16 | 16 | 1 | administrative: province | — | L1 — 15 provinces + Isla de la Juventud |
| Cyprus | CY | 6 | 6 | 6 | 1 | administrative: district | — | L1 |
| Czech Republic | CZ | 90 | 90 | 90 | 1 | administrative: region | — | L1 — 13 regions + 76 districts + Prague flat; depth-2 candidate |
| Denmark | DK | 5 | 5 | 5 | 1 | administrative: region | — | L1 |
| Djibouti | DJ | 6 | 6 | 6 | 1 | administrative: region | — | L1 — 5 regions + Djibouti City |
| Dominica | DM | 10 | 10 | 10 | 1 | administrative: parish | — | L1 |
| Dominican Republic | DO | 42 | 42 | 42 | 1 | administrative: region | — | L1 — 10 regions + 31 provinces + DN flat; depth-2 candidate |
| DR Congo | CD | 26 | 26 | 26 | 1 | administrative: province | — | L1 |
| Ecuador | EC | 24 | 24 | 24 | 1 | administrative: province | — | L1 |
| Egypt | EG | 27 | 27 | 27 | 1 | administrative: governorate | — | L1; markaz (~350) candidate, Arabic-first |
| El Salvador | SV | 14 | 14 | 14 | 1 | administrative: department | — | L1 |
| Equatorial Guinea | GQ | 10 | 10 | 10 | 1 | administrative: region | — | L1 — 2 regions + 8 provinces flat |
| Eritrea | ER | 6 | 6 | 6 | 1 | administrative: region | — | L1 |
| Estonia | EE | 94 | 94 | 94 | 1 | administrative: county | — | L1 — 15 counties + 79 municipalities flat; depth-2 candidate |
| Eswatini | SZ | 4 | 4 | 4 | 1 | administrative: region | — | L1 |
| Ethiopia | ET | 14 | 14 | 14 | 1 | administrative: region | — | L1; SN dissolved on seed |
| Faroe Islands | FO | 6 | 6 | 6 | 1 | administrative: region | — | L1 — 6 regions |
| Fiji | FJ | 19 | 19 | 19 | 1 | administrative: division | — | L1 — 4 divisions + 14 provinces + Rotuma |
| Finland | FI | 18 | 18 | 18 | 1 | administrative: region | — | L1 |
| France | FR | 18 | 18 | 18 | 1 | administrative: region | — | L1 |
| French Guiana | GF | 1 | 1 | 1 | 1 | administrative: overseas_region | — | L1 — single region |
| French Polynesia | PF | 5 | 5 | 5 | 1 | administrative: division | — | L1 — 5 divisions |
| French Southern Territories | TF | 5 | 5 | 5 | 1 | administrative: district | — | L1 — 5 districts; uninhabited |
| Gabon | GA | 9 | 9 | 9 | 1 | administrative: province | — | L1 |
| Gambia | GM | 6 | 6 | 6 | 1 | administrative: division | — | L1 — 5 divisions + Banjul |
| Georgia | GE | 12 | 12 | 12 | 1 | administrative: region | — | L1 — 9 regions + 2 ARs + Tbilisi |
| Germany | DE | 16 | 16 | 16 | 1 | administrative: state | — | L1 |
| Ghana | GH | 16 | 16 | 16 | 1 | administrative: region | — | L1 |
| Greece | GR | 15 | 15 | 15 | 1 | administrative: administrative_region | — | L1 — 13 regions + 2 regional units |
| Greenland | GL | 5 | 5 | 5 | 1 | administrative: municipality | — | L1 — 5 municipalities |
| Grenada | GD | 7 | 7 | 7 | 1 | administrative: parish | — | L1 — 6 parishes + Carriacou |
| Guadeloupe | GP | 2 | 2 | 2 | 1 | administrative: district | — | L1 — 2 districts |
| Guam | GU | 19 | 19 | 19 | 1 | administrative: village | — | L1 — 19 villages |
| Guatemala | GT | 22 | 22 | 22 | 1 | administrative: department | — | L1 |
| Guernsey | GG | 12 | 12 | 12 | 1 | administrative: parish | — | L1 — 12 parishes |
| Guinea | GN | 41 | 41 | 41 | 1 | administrative: administrative_region | — | L1 — 7 regions + Conakry + 33 prefectures flat; depth-2 candidate |
| Guinea-Bissau | GW | 12 | 12 | 12 | 1 | administrative: province | — | L1 — 3 provinces + 8 regions + Bissau sector |
| Guyana | GY | 10 | 10 | 10 | 1 | administrative: region | — | L1 |
| Haiti | HT | 10 | 10 | 10 | 1 | administrative: department | — | L1 |
| Honduras | HN | 18 | 18 | 18 | 1 | administrative: department | — | L1 |
| Hong Kong | HK | 18 | 18 | 18 | 1 | administrative: district | — | L1 — 18 districts |
| Hungary | HU | 43 | 43 | 43 | 1 | administrative: county | — | L1 — 20 counties + 22 county-rights cities + Budapest |
| Iceland | IS | 72 | 72 | 72 | 1 | administrative: region | — | L1 — 8 regions + 64 municipalities flat |
| India | IN | 36 | 36 | 36 | 1 | administrative: state | — | L1 — 28 states + 8 UTs; districts (~780) candidate |
| Indonesia | ID | 38 | 38 | 7837 | 3 | administrative: province > regency > district | district, regency | Complete to kecamatan; desa (~83k) out of scope |
| Iran | IR | 31 | 31 | 31 | 1 | administrative: province | — | L1 — 31 ostans; counties out of scope |
| Iraq | IQ | 19 | 19 | 19 | 1 | administrative: governorate | — | L1 incl. Halabja; KR region removed; qada (~120) candidate |
| Ireland | IE | 30 | 30 | 30 | 1 | administrative: province | — | L1 — 4 provinces + 26 counties flat; depth-2 candidate |
| Isle of Man | IM | 6 | 6 | 6 | 1 | administrative: sheadings | — | L1 — 6 sheadings |
| Israel | IL | 6 | 6 | 6 | 1 | administrative: district | — | L1 — 6 districts; sub-districts out of scope |
| Italy | IT | 20 | 20 | 20 | 1 | administrative: region | — | L1 |
| Ivory Coast | CI | 14 | 14 | 14 | 1 | administrative: district | — | L1 — 12 districts + Abidjan/Yamoussoukro |
| Jamaica | JM | 14 | 14 | 14 | 1 | administrative: parish | — | L1 |
| Japan | JP | 47 | 47 | 47 | 1 | administrative: prefecture | — | L1; municipalities candidate |
| Jersey | JE | 12 | 12 | 12 | 1 | administrative: parish | — | L1 — 12 parishes |
| Jordan | JO | 12 | 12 | 12 | 1 | administrative: governorate | — | L1; liwa (~50) candidate |
| Kazakhstan | KZ | 20 | 20 | 20 | 1 | administrative: region | — | L1 — 17 regions + 3 cities |
| Kenya | KE | 47 | 47 | 47 | 1 | administrative: county | — | L1 |
| Kiribati | KI | 3 | 3 | 3 | 1 | administrative: island | — | L1 — 3 island groups |
| Kosovo | XK | 7 | 7 | 7 | 1 | administrative: district | — | L1 |
| Kuwait | KW | 6 | 6 | 6 | 1 | administrative: governorate | — | L1; postal areas (~100) candidate |
| Kyrgyzstan | KG | 9 | 9 | 9 | 1 | administrative: region | — | L1 — 7 regions + Bishkek/Osh |
| Laos | LA | 18 | 18 | 18 | 1 | administrative: province | — | L1 — 17 provinces + Vientiane Prefecture; muang out of scope |
| Latvia | LV | 43 | 43 | 43 | 1 | administrative: municipality | — | L1 — 36 municipalities + 7 state cities; 3 twins share names |
| Lebanon | LB | 8 | 8 | 8 | 1 | administrative: governorate | — | L1 — 8 governorates; cazas out of scope |
| Lesotho | LS | 10 | 10 | 10 | 1 | administrative: district | — | L1 |
| Liberia | LR | 15 | 15 | 15 | 1 | administrative: county | — | L1 |
| Libya | LY | 22 | 22 | 22 | 1 | administrative: popularate | — | L1 — 22 sha'biyat |
| Liechtenstein | LI | 11 | 11 | 11 | 1 | administrative: commune | — | L1 |
| Lithuania | LT | 70 | 70 | 70 | 1 | administrative: county | — | L1 — 10 counties + 60 municipalities flat; 4 city/district twins suffixed by code |
| Luxembourg | LU | 12 | 12 | 12 | 1 | administrative: canton | — | L1 |
| Madagascar | MG | 6 | 6 | 6 | 1 | administrative: province | — | L1; codeless 23 regions omitted |
| Malawi | MW | 31 | 31 | 31 | 1 | administrative: region | — | L1 — 3 regions + 28 districts flat; depth-2 candidate |
| Malaysia | MY | 16 | 16 | 1842 | dual 2+4 | postal: region > locality; administrative: region > division > district > subdivision | administrative_district, administrative_division, administrative_subdivision, postal_locality | Complete; postal CSVs bundled |
| Maldives | MV | 21 | 21 | 21 | 1 | administrative: atoll | — | L1 — 20 atolls + Addu City |
| Mali | ML | 11 | 11 | 11 | 1 | administrative: region | — | L1 — 10 regions + Bamako |
| Malta | MT | 68 | 68 | 68 | 1 | administrative: local_council | — | L1 — 68 local councils |
| Marshall Islands | MH | 26 | 26 | 26 | 1 | administrative: municipality | — | L1 — 24 municipalities + 2 chains |
| Martinique | MQ | 4 | 4 | 4 | 1 | administrative: district | — | L1 — 4 districts |
| Mauritania | MR | 15 | 15 | 15 | 1 | administrative: region | — | L1 |
| Mauritius | MU | 12 | 12 | 12 | 1 | administrative: district | — | L1 — 9 districts + 3 dependencies |
| Mayotte | YT | 17 | 17 | 17 | 1 | administrative: commune | — | L1 — 17 communes |
| Mexico | MX | 32 | 32 | 32 | 1 | administrative: state | — | L1 |
| Micronesia | FM | 4 | 4 | 4 | 1 | administrative: state | — | L1 — 4 states |
| Moldova | MD | 37 | 37 | 37 | 1 | administrative: district | — | L1 — 32 districts + 3 cities + Gagauzia/Transnistria |
| Monaco | MC | 17 | 17 | 17 | 1 | administrative: quarter | — | L1 — 17 quarters |
| Mongolia | MN | 22 | 22 | 22 | 1 | administrative: province | — | L1 — 21 aimags + Ulaanbaatar |
| Montenegro | ME | 25 | 25 | 25 | 1 | administrative: municipality | — | L1 |
| Montserrat | MS | 3 | 3 | 3 | 1 | administrative: parish | — | L1 — 3 parishes |
| Morocco | MA | 87 | 12 | 87 | 2 | administrative: region > province | province | Complete to province; communes (~1,500) out of scope |
| Mozambique | MZ | 11 | 11 | 11 | 1 | administrative: province | — | L1 — 10 provinces + Maputo City |
| Myanmar | MM | 15 | 15 | 15 | 1 | administrative: region | — | L1 — 7 regions + 7 states + Naypyidaw |
| Namibia | NA | 14 | 14 | 14 | 1 | administrative: region | — | L1 |
| Nauru | NR | 14 | 14 | 14 | 1 | administrative: district | — | L1 |
| Nepal | NP | 7 | 7 | 7 | 1 | administrative: province | — | L1 — 7 provinces; districts out of scope |
| Netherlands | NL | 12 | 12 | 12 | 1 | administrative: province | — | L1 |
| New Caledonia | NC | 3 | 3 | 3 | 1 | administrative: province | — | L1 — 3 provinces |
| New Zealand | NZ | 17 | 17 | 17 | 1 | administrative: region | — | L1 — 16 regions + Chatham Islands |
| Nicaragua | NI | 17 | 17 | 17 | 1 | administrative: department | — | L1 — 15 departments + 2 autonomous regions |
| Niger | NE | 8 | 8 | 8 | 1 | administrative: region | — | L1 — 7 regions + Niamey |
| Nigeria | NG | 37 | 37 | 37 | 1 | administrative: state | — | L1 — 36 states + FCT; LGAs (774) candidate |
| Niue | NU | 14 | 14 | 14 | 1 | administrative: village | — | L1 — 14 villages |
| North Korea | KP | 13 | 13 | 13 | 1 | administrative: province | — | L1 — 9 provinces + 4 cities; no postcode |
| North Macedonia | MK | 80 | 80 | 80 | 1 | administrative: municipality | — | L1 — 80 municipalities |
| Norway | NO | 17 | 17 | 17 | 1 | administrative: county | — | L1 — 15 counties + Svalbard/Jan Mayen |
| Oman | OM | 11 | 11 | 74 | 2 | administrative: governorate > wilayat | wilayat | Complete — 11 governorates + 63 wilayats |
| Pakistan | PK | 7 | 7 | 181 | 2 | administrative: province > district | district | Complete to district (174, late-2025); tehsils out; 2026 Balochistan batch excluded |
| Palau | PW | 16 | 16 | 16 | 1 | administrative: state | — | L1 — 16 states |
| Palestine | PS | 16 | 16 | 16 | 1 | administrative: governorate | — | L1 |
| Panama | PA | 14 | 14 | 14 | 1 | administrative: province | — | L1 — 10 provinces + 4 comarcas |
| Papua New Guinea | PG | 22 | 22 | 22 | 1 | administrative: province | — | L1 — 20 provinces + Bougainville + Port Moresby |
| Paraguay | PY | 18 | 18 | 18 | 1 | administrative: department | — | L1 — 17 departments + Asunción |
| Peru | PE | 26 | 26 | 26 | 1 | administrative: region | — | L1 — 25 regions + Lima |
| Philippines | PH | 99 | 82 | 82 | 1 | administrative: province | — | L1 — provinces; regions global-only; municipalities/barangay out of scope |
| Poland | PL | 16 | 16 | 16 | 1 | administrative: voivodeship | — | L1 |
| Portugal | PT | 20 | 20 | 20 | 1 | administrative: district | — | L1 — 18 districts + Azores/Madeira |
| Puerto Rico | PR | 78 | 78 | 78 | 1 | administrative: municipality | — | L1 — 78 municipios (10 typed region in source) |
| Qatar | QA | 8 | 8 | 8 | 1 | administrative: municipality | — | L1; zones (~98) candidate |
| Reunion | RE | 4 | 4 | 4 | 1 | administrative: district | — | L1 — 4 districts |
| Romania | RO | 42 | 42 | 42 | 1 | administrative: department | — | L1 — 41 departments + Bucharest |
| Russia | RU | 83 | 83 | 83 | 1 | administrative: subject | — | L1 |
| Rwanda | RW | 5 | 5 | 5 | 1 | administrative: province | — | L1 — 4 provinces + Kigali |
| Saint Barthelemy | BL | 1 | 1 | 1 | 1 | administrative: overseas_collectivity | — | L1 — single collectivity |
| Saint Helena | SH | 8 | 8 | 8 | 1 | administrative: district | — | L1 — 8 districts |
| Saint Kitts and Nevis | KN | 16 | 16 | 16 | 1 | administrative: state | — | L1 — 2 islands + 14 parishes flat |
| Saint Lucia | LC | 10 | 10 | 10 | 1 | administrative: district | — | L1 |
| Saint Martin | MF | 1 | 1 | 1 | 1 | administrative: overseas_collectivity | — | L1 — single collectivity |
| Saint Pierre and Miquelon | PM | 1 | 1 | 1 | 1 | administrative: overseas_collectivity | — | L1 — single collectivity |
| Saint Vincent and the Grenadines | VC | 6 | 6 | 6 | 1 | administrative: parish | — | L1 |
| Samoa | WS | 11 | 11 | 11 | 1 | administrative: district | — | L1 |
| San Marino | SM | 9 | 9 | 9 | 1 | administrative: municipality | — | L1 — 9 municipalities |
| Sao Tome and Principe | ST | 7 | 7 | 7 | 1 | administrative: district | — | L1 — 6 districts + Príncipe AR |
| Saudi Arabia | SA | 13 | 13 | 13 | 1 | administrative: region | — | L1; governorates (~130) candidate, Arabic-first |
| Senegal | SN | 14 | 14 | 14 | 1 | administrative: region | — | L1 |
| Serbia | RS | 32 | 32 | 32 | 1 | administrative: district | — | L1 — 29 districts + 2 provinces + Belgrade |
| Seychelles | SC | 27 | 27 | 27 | 1 | administrative: district | — | L1 — 27 districts |
| Sierra Leone | SL | 5 | 5 | 5 | 1 | administrative: province | — | L1 — 4 provinces + Western Area |
| Singapore | SG | 5 | 5 | 174 | dual 2+2 | postal: postal_district > postal_sector; administrative: region > planning_area | planning_area, postal_district, postal_sector, region | Complete; postcodes via OneMap |
| Slovakia | SK | 8 | 8 | 8 | 1 | administrative: region | — | L1 |
| Slovenia | SI | 212 | 212 | 212 | 1 | administrative: municipality | — | L1 — 200 municipalities + 12 urban municipalities |
| Solomon Islands | SB | 10 | 10 | 10 | 1 | administrative: province | — | L1 — 9 provinces + Honiara |
| Somalia | SO | 18 | 18 | 18 | 1 | administrative: region | — | L1 |
| South Africa | ZA | 9 | 9 | 9 | 1 | administrative: province | — | L1 |
| South Korea | KR | 17 | 17 | 17 | 1 | administrative: province | — | L1 |
| South Sudan | SS | 10 | 10 | 10 | 1 | administrative: state | — | L1 — 10 states |
| Spain | ES | 69 | 19 | 69 | 2 | administrative: community > province | province | Complete — communities + 50 provinces |
| Sri Lanka | LK | 34 | 34 | 34 | 1 | administrative: province | — | L1 — 9 provinces + 25 districts flat; depth-2 candidate |
| Sudan | SD | 18 | 18 | 18 | 1 | administrative: state | — | L1 |
| Suriname | SR | 10 | 10 | 10 | 1 | administrative: district | — | L1 |
| Sweden | SE | 21 | 21 | 21 | 1 | administrative: county | — | L1 |
| Switzerland | CH | 26 | 26 | 26 | 1 | administrative: canton | — | L1 |
| Syria | SY | 14 | 14 | 14 | 1 | administrative: province | — | L1 |
| Taiwan | TW | 22 | 22 | 22 | 1 | administrative: division | — | L1 |
| Tajikistan | TJ | 5 | 5 | 5 | 1 | administrative: region | — | L1 — 2 regions + GBAR + Dushanbe + republican districts |
| Tanzania | TZ | 31 | 31 | 31 | 1 | administrative: region | — | L1 |
| Thailand | TH | 78 | 78 | 78 | 1 | administrative: province | — | L1 — 76 provinces + Bangkok/Pattaya; amphoe candidate |
| Timor-Leste | TL | 14 | 14 | 14 | 1 | administrative: municipality | — | L1 — 13 ISO + Atauro (AT provisional); admin posts out of scope |
| Togo | TG | 5 | 5 | 5 | 1 | administrative: region | — | L1 |
| Tonga | TO | 5 | 5 | 5 | 1 | administrative: division | — | L1 — 5 divisions |
| Trinidad and Tobago | TT | 17 | 17 | 17 | 1 | administrative: region | — | L1 — 12 regions + 3 boroughs + POS + Tobago |
| Tunisia | TN | 24 | 24 | 24 | 1 | administrative: governorate | — | L1 |
| Turkmenistan | TM | 6 | 6 | 6 | 1 | administrative: region | — | L1 — 5 regions + Ashgabat |
| Turks and Caicos | TC | 6 | 6 | 6 | 1 | administrative: district | — | L1 — 6 districts |
| Tuvalu | TV | 8 | 8 | 8 | 1 | administrative: island_council | — | L1 — 7 island + 1 town council |
| Türkiye | TR | 81 | 81 | 81 | 1 | administrative: province | — | L1; ilçe (~970) candidate (TÜİK) |
| Uganda | UG | 4 | 4 | 4 | 1 | administrative: region | — | L1 — regions only; districts excluded (volatile) |
| Ukraine | UA | 27 | 27 | 27 | 1 | administrative: oblast | — | L1 — 24 oblasts + Kyiv/Sevastopol/Crimea |
| United Arab Emirates | AE | 7 | 7 | 7 | 1 | administrative: emirate | — | L1; no official tier-2 |
| United Kingdom | GB | 4 | 4 | 4 | 1 | administrative: nation | — | L1 — 4 nations; 221 subdivisions global-only |
| United States | US | 56 | 56 | 56 | 1 | administrative: state | — | L1 — 50 + DC + 5 territories; AA/AE/AP/UM global-only |
| US Minor Outlying Islands | UM | 9 | 9 | 9 | 1 | administrative: island | — | L1 — 9 islands; uninhabited |
| US Virgin Islands | VI | 3 | 3 | 3 | 1 | administrative: district | — | L1 — 3 districts |
| Uruguay | UY | 19 | 19 | 19 | 1 | administrative: department | — | L1 |
| Uzbekistan | UZ | 14 | 14 | 14 | 1 | administrative: region | — | L1; tuman (~175) candidate |
| Vanuatu | VU | 6 | 6 | 6 | 1 | administrative: province | — | L1 |
| Venezuela | VE | 25 | 25 | 25 | 1 | administrative: state | — | L1 — 23 states + Caracas + dependencies |
| Vietnam | VN | 34 | 34 | 34 | 1 | administrative: province | — | L1 post-merger 34; communes candidate |
| Wallis and Futuna | WF | 3 | 3 | 3 | 1 | administrative: administrative_precinct | — | L1 — 3 precincts |
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

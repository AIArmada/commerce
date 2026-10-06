# Geography verification program

Country-by-country integrity verification of the bundled addressing
geography (areas, postcodes, links). Malaysia set the method; every other
country gets the same ladder, scaled to its data shape.

## The check ladder

Run top to bottom per country. Stop early only when a step proves the rest
unnecessary (e.g. Singapore/Brunei: clean data → verify-only).

1. **Structural integrity** — counts by type/level, zero orphans, parent
   code-prefix consistency, postcode format, exactly-one primary per code,
   zero dangling links, coverage count. Pure CSV work, no external source.
2. **Upstream fidelity** — when the tree derives from a pinned release
   (e.g. region-id v1.0.1), diff code sets, names, types, and parents
   directly against the release assets. Distinguishes "our build broke it"
   from "upstream is wrong".
3. **Official-truth diff** — diff names/codes against the authoritative
   decree or gazette (Kepmendagri 2025 for ID, UPI for MY). Fix parse
   artefacts; record deliberate deviations (display conventions,
   state-matched renames) in the gate so re-diffs stay green.
4. **Postcode link verification** — the expensive step. Never trust a
   single join key: GeoNames admin2 codes proved systematically rotated
   for ID (same bug the original overlay inherited). Method that worked:
   - Build a per-code place-name vote: match GeoNames postal rows against
     our own village/district tree (province-constrained unique match).
   - Categorize: 3-way agree (keep), vote+GN vs ours (fix), ours+GN vs
     vote (fix only if strong/unanimous, else hand-review), all-differ
     (hand-review with coords), no-vote (extend unanimous blocks only).
   - Safety net: unconstrained nationwide vote (n≥3) must not contradict
     finals — catches wrong-province keeps the constrained voter is blind
     to (323xx, 983xx, 996xx class).
   - Spot-corroborate anchors and anomalies against postal references
     (Pos Indonesia book, district-code pages, addressed sightings).
   - Record judgment calls for genuinely shared codes (city holds the
     primary: Tual 97611, Bima 84111 → regency on 11-0 place evidence).
5. **Durable proof** — a `gate_<cc>.py` in `docs/agents/audit/` (counts,
   anchors, block table), Pest pins in the provider test (renames + key
   links), a `05-country-data.md` revisit record, and the overlay-row
   update in `18-postal-overlays.md` where applicable.

## Status

| Country | State | What was done |
|---|---|---|
| Malaysia (MY) | Done — full rebuild | ~550 rows added, 14 retypes, 13 consolidations; 13 state gates; per-state records in 05-country-data.md |
| Singapore (SG) | Verified clean | Zero data changes; `gate_sg.py` ALL PASS |
| Brunei (BN) | Verified clean | 39 mukims match, 394 postcodes vs Brunei Post extract, 2 alternative names; no data fixes |
| Indonesia (ID) | Done — tree + link rebuild | 107 official-name fixes; 1,912/5,513 links corrected; `gate_id.py` ALL PASS; coverage 369 → 389 L2 |
| Indonesia gaps | Done — filled | +3,613 codes from the Pos Indonesia postcode book × GeoNames; 9,126 codes, 514/514 L2, all real ranges covered; 25 Timor + 11 typo rows deliberately dropped; verdicts in `audit/id-fill-verdict.json` |
| Indonesia town pages | Done — swept | 429 worldpostalcode town pages fetched (14,431 place rows, 8,855 codes); +241 codes after place-vote verdict, 7 dropped (1 typo, 2 page errors, 4 Timor-Leste); 7 Batusangkar links fixed 50 Kota → Tanah Datar; verdicts in `audit/id-wpc-verdict.json` |
| Indonesia contra sweep | Done — swept | All 8,848 shared town-page codes re-voted against our links: 99 links moved (mostly inherited GeoNames-admin2 errors), 24 typo-dupe/stale codes dropped, 15 keeps (sweep wrong); 9,361 codes; verdicts in `audit/id-contra-verdict.json`; `gate_id.py` ALL PASS; voter rebuilt as `audit/id-voteutil.py` |
| All other countries | Todo | Same ladder; start with step 1 + existing overlay-row claims — see Wave plan below |
| M1 batch (BH AE PS KM SL DJ LB TG) | Done — 6 clean, 2 fixed | DJ lac-assal Arta→Tadjourah; LB 18 retargets + 5 Hermel codes + 2 secondaries (688/701); SL +4 areaNames aliases; BH/LB overlay wording fixes; 8 gates ALL PASS; 606 Geography Pest tests green |

## Wave plan (resumable program backlog)

Wave 1 = Muslim-first: all 57 OIC member states in the dataset plus
Kosovo (Muslim-majority, non-member). Wave 2 = everything else.
Within a wave, batches run small→large so the gate pattern settles
early. Step 1 for ALL countries runs in one shot:
`python3 docs/agents/audit/struct_all.py` (report + `/tmp/geo-verify/struct_all.json`).

NEXT: B22 DONE — ladder complete (suite 2182 passed). All wave-1–3 open retries landed and integrated (ID/MY/FR/Padawan/UY/BN + GB/IT/CH/LR/NI/RS/UA/CF/ET/MG/BA/CU/MU). Residual holds stand per entries; follow-up programs (ID vintage, BN Belaban/finder, MY Langkon/mediums, UY flags) recorded in reports, not actioned.

Wave 1 batches (rows = areas CSV; done = gate + Pest pins + 05 record):

- [x] M1: BH AE PS KM SL DJ LB TG (4–44 rows)
- [x] M2: XK CI GW GM KG GA SN JO (45–63 rows)
- [x] M3: BF TM CM BD AL SR OM TJ (64–74 rows) — 3 verify-only (CM SR TM-tree), 5 fix-and-fill (BF 467 fills + Boulkiemdé; AL 138 retargets + 36 fills + 1 drop; BD 24 fills; OM 99 fills; TJ 74 areas clean, 336/390 expansion); 646 Geography Pest tests green
- [x] M4: MR NE SY TD GY MA BJ QA (78–98 rows) — 4 verify-only (MR NE SY QA), TD 1-cell el-fix, BJ 5 spelling fixes, MA 3 flips + 6 phantom drops (2083/2088), GY tree-clean + live 7-digit gap (guypost.gy walled); 653 Geography Pest tests green
- [x] M5: SO LY IQ KW MZ UG SA ML (107–179 rows) — 3 verify-only (LY KW UG), SO 2 renames, ML 1-cell fix, IQ 11 retargets, MZ 32 fills + 1 secondary (145/151), SA 3-link Samtah flip; 661 Geography Pest tests green
- [x] M6: PK KZ SD MV UZ TN YE (185–355 rows) — 2 verify-only (PK MV), KZ 9 reparents + 070209 flip, SD 13315 leg-drop, UZ 5-link Oqoltin fix, YE 2-cell space-fix, TN +174 fills (969/982); 673 Geography Pest tests green
- [x] M7: EG AF IR DZ AZ NG TR (392–1,054 rows) — 3 verify-only (TR AZ NG: TR 34, AZ 56, NG 90 gate checks), EG verify-only + 7-digit gap, DZ 19 retargets (3908/3908 kept), AF 12 moves + 2 fills (1408/1408, OSM-boundary correction via COD-AB polygons), IR 96914 move + 97716 secondary (109/111)
- [x] MY BN ID already done (prior passes)

Wave 2 batches (169 countries, small→large; SG done):

- [x] B1: BL MF PM BQ MS GL TF BZ (1–6 rows) — 6 verify-only (BL/MF/PM single UPU codes, BQ ISO 3/3 no-codes + 2026 postcode plan watch, TF 5 districts no domestic system, BZ ISO 6/6 no-codes), MS phantom parish drop (4→3, Saint Patrick's is a destroyed village) + MSR1310 → Saint Peter, GL 3985 fill → Sermersooq (27→28, Mittarfeqarfiit official usage); 10 new B1 Pest tests green
- [x] B2: TC VC WF AD GD ST AG TV (6–8 rows) — 5 verify-only (TC 6 districts + TKCA 1ZZ code-only, WF Hexasmal 98600/98610/98620, AD 7/7 GeoNames+UPU, GD 7/7 no-codes, AG 8/8 no-codes), VC Layou move → St Andrew (56/56 kept; VC0100 box-only + VC0292 disputed holds vindicated), ST Lemba→Lembá, TV Nanumaga alias + TUV-system gap (no public directory); 14 B2 Pest tests green
- [x] B3: AW SM UM DM GQ KY LC SH (9–10 rows) — 8 verify-only (AW 8 CBS regions + capital row, SM UPU full list 10/10, UM ISO 9/9 US-system, DM ISO 02–11, GQ ISO 10/10 incl. DJ, KY 7 districts box-only no-import, LC govt table 47/48 + Marisule dual + ISO 10/10, SH 3 UK codes Jamestown-primary); 13 B3 Pest tests green
- [x] B4: BB BM LI GG BE AI JM NR (11–14 rows) — 7 verify-only (BB BPS finder 1161/1161 + 15 office bases + 9 BB23 duals + BB190215 typo hold, BE GeoNames 1146/1146 zero cross-province + 12 seats + bpost-list watch, LI ISO 11/11 + 9489 held out, GG GY1–GY10 + 3 duals + St-spelling hold, AI 14 districts + AI-2640, JM ISO 14/14 no-system, NR Baiti hold + NRU68), BM fix-and-fill (BPO Blue Pages street vote 105→113 links: 7 primary moves + HM restructure + GE01 town drop + St. George rename; HA hold); 15 new B4 Pest tests green, suite 1900 passed
- [x] B5: NU TT AX PW MC YT HK FJ (14–19 rows) — 8 verify-only (NU 14 villages + 9974, TT ISO 15/15 + 6-digit-system gap no-directory, AX 16 mun + 33/33 street codes + 5 box twins held out, PW ISO 16/16 + 96939 Ngerulmud-only + 96940 rest, MC ISO 17/17 + 98000 + 01–99 special held out, YT 17 communes + 11/18 Hexasmal exact, HK 18 districts + 999077 CN-code excluded, FJ ISO 19/19 + Nadroga-Navosa hyphen kept); 12 new B5 Pest tests green, suite 1912 passed
- [x] B6: GU AS GF VI ME MH IM KI
- [x] B7: SC RE TO IE MW BS GP LK
- [x] B8: FO RW NC MQ BW BI HT PF
- [x] B9: CV SV SZ ZA ER JE MT ES — CV areas 56 verify-only + postal stays admin-ready (CPN portal-only, 32 annex examples not bundled, OSM pre-2019 stale); SV 12 parents fixed inline + 262/262 worker crosswalk verify-only; SZ 55→59 tinkhundla + Hlane→Gilgal + Zombodze→Zombodze Emuva + 5 spelling + H101 (80→81); ER Kudo Be'ur→Emni Haili (COD ER610 + GN + 2 WP lists); JE 56 vingtaines verify-only; MT 8-cell count-neutral swap 27823/27823; ZA 101 retargets + 33 secondaries + 11 fills → 3277/3318 street-first; ES 87 removals + 5 fills + 3 dropped legs + 7 duals + 3 moves → 11068/11094 (Correos nuclei API sweep); suite 1976 passed
- [x] B10: IS VU LT CL ZW FM MK TL — 5 verify-only (FM 79/4 Tol+Utwe holds, LT 2023/2068 GN-exact, ZW, TL admin-ready, IS 69/174/178 duals 276/641/701/851 folded Húnabyggð+Skagafjörður+Múlaþing garabr/mulaing contracted ids), VU 63→67 L2 (East Ambae/North Maewo/South Epi/bare Whitesands + Torba 7 islands, census+COD over pre-2008 Statoids), CL Cautin→Cautín 1-cell (346/346 re-verified), MK 1128 petrovec→ilinden 1-retarget (OSM+history.mk+terminal address, 326/326 kept, 1137 exclusion stands, 21-post-2016 vintage hold); suite 1993 passed
- [x] B11: RU NP SK CZ LS CR AM EE — 2 verify-only (NP 84/753/753 GPO-exact, CR 91/492/492 Lagunillas-hold), RU 136-link 626xxx KHM→Tyumen, SK 114 region→district moves, LS 1-cell Malingoaneng-dup→Senqu, AM 2 fills + 3518 Goris→Sisian (779→781), CZ +15 RUIAN-ref secondaries (2723→2738), EE 11 EHAK cells + 84 pii/ADS fills (5481/5499); suite 848 Geography green
- [x] B12: MM PA GE SS CF AT DK CG — 2 verify-only (PA 14/81 INEC-exact + postal none, DK 103/1159/1159 GN-exact), MM tree rebuild 80→126 L2 (5 deletes + 2 renames + 51 adds, census-operationalized 2022 districts), SS 4 junk/era deletes + 2 renames (10/84), GE Gali retype 65→64/16→17 + 74/83 gpost re-verified zero moves, CF 4 drops + 9 adds (L2 80→85, decree posts 1–85), AT 16 city retargets + 1 secondary, CG +3 districts Odziba/Bouemba/Ile Mbamou + Ollombo move + 5 JO renames (L2 89→92); suite 2035 passed
- [x] B13: KN LU GB PG FR BO BY ZM — BO 8 renames, ZM Mansa fix, KN KN0111 Cayon-primary + LF norm, FR 20316/20340/24 duals (4624 strips, 7 deletes, 13 Rhône→69M, 3 flips, 93380 fill), PG Bulolo rename + 5 office fills (67/76; 541/417 held), LU 15 drops + 18 fills + 24 legs (4333/4435/94; worker multi-97 corrected), GB 2 renames + 87 legs + 10 drops + 358 flips + E22/MK20 (2943/3750/687; NSPL plurality reproduced 358/358; GB-EAY citation corrected), BY 29 retargets + 2 drops + 19 fills (3140/3140, 118/118; integrator Mapanet Talachyn pull for 211091); suite 2059 passed
- [x] B14: IT NA ET LR MG UY BA MU
- [x] B15: UA LA NI CD CH CU RS KP
- [x] B16: SB DO SI PE TZ BT KH HU — 3 verify-only (SB 193 SINSO-exact, SI 212/212 ISO + 467/468 GN, BT 2 renames Chhukha/Lhuentse), DO Quisqueya rename (528/528 INPOSDOM-exact), HU 2 drops + 3 fills (3048/3066/18), PE Loreto 4-code rotation + 42 moves + Raimondi/Alcides renames, KH 5-leg Samraong fix (1633/1633), TZ NBS-2022 rebuild (+Mlimba/+Mtama/+Kibiti, Tanganyika, −Kilombero/−Lindi; 124 fills + swap − 63 removes + 59 moves → 4096/4096); suite 2113 passed
- [x] B17: KR EC GH PY BG FI SE HN — 3 verify-only (KR, GH, PY), HN integrated, EC 10 renames + 176 moves, SE 62 moves + Göteborg, BG 22 fills + 34 removals + 5 Malko Tarnovo renumber moves (835x→816x, direction reversed) + 2789/2791 leg fixes (4351/4363), FI 00002 hattula→helsinki (3576/3576); suite 2128 passed
- [x] B18: PT KE GR AO WS NL VE MN — PT Lisboa ×2 + postal CRLF→LF (197772/197772, 25/25 CTT-mirror), KE 28 fills + 26 moves (977/977; Tigiji/Watalii + 3 Nairobi holds), GR 3 leg moves (14121/14122→Irakleio, 49083→Central Corfu; 974/984 ELTA-exact), AO Chicapa 1-cell (21/326), WS 5 itumalo re-parents + 2 district legs (223/240), NL 1216 DEL + 6153/6881 ADD (4071/4092 BAG), MN 4 renames (39/39 clean), VE 2 Bolívar renames + 5 shared codes reconfirmed vs fresh Mapanet (444/450, no postal change); 7 collateral subagents lost to network outage, finished inline; suite 2143 passed
- [x] B19: GT CN NO TW PL DE AU AR — 7/8 first wave (GT CN NO TW PL AU AR verify/fix; gates 0 FAIL), VE-r2 +8 codes, DE 11 endonym renames + 20 retargets + 4 swaps + 1 insert (S5 held; OSM H7 re-close 11/12 + 10/10 spots); suite 2167 passed
- [x] B20: HR LV CY IN PR TH MD CO — HR + PR verify-only (577/577, Census-2024 exact), MD 15 moves + 1 del + 1 add (1215/1220), TH 789/930 postal (0 codeless, tree held), LV 697/731 (96 moves + 12 adds), CY 1132/1135 (L2 755, homoglyph renames), CO +1 area + 42 renames + 3681/3681, IN tree zero + 19238/19488 (Ladakh-5 LGD-held); all gates 0 FAIL; suite 2167 passed
- [x] B21: NZ JP MX US RO VN CA BR — US + VN verify-only (40977/40977, 3320/3320 MOST-exact), JP 77 Tenryu-ku + 432-0000 drop + 38 adds (120720/120801), CA T1 fix + G0B/H4Z removes + 14 adds (1663/1673), RO 17 renames, BR 38 RO renumbers + 78937 drop + 68948 relink + 22 adds + 7 follow-ups (5547/5547), MX 10 renames + PF1 77580/77586, NZ 6 renames + 28 appends + 32 adds + 23 retargets (1313/1769/1769); all gates 0 FAIL; suite 2180 passed
- [x] B22: PH — PSGC-1Q2025-exact tree (83/83 L1, 1642/1642 L2, 41798/41798 L3) + 3 renames, postal 1933→1919 (15 drops, 9610/9612 relegs, 2222 add); gate 0 FAIL; suite 2182 passed

Batch states flip to [x] only with gate ALL PASS + Pest green +
05-country-data record in the same pass.

## Artifacts

- `docs/agents/audit/gate_*.py` — the gates. Run from repo root:
  `python3 docs/agents/audit/gate_id.py`. MY gates import sibling
  `*_data.py` expectation files in the same dir.
- `docs/agents/audit/id-*.json` — ID verdict map, block table, vote records.
- `/tmp/my-audit/` — regenerable scratch only (do not depend on it across
  reboots). Rebuild inputs with:

```sh
mkdir -p /tmp/my-audit/geonames /tmp/my-audit/id-upstream
cd /tmp/my-audit/geonames
curl -sL -o ID-postal.zip https://download.geonames.org/export/zip/ID.zip
unzip -o -q ID-postal.zip -d postal
curl -sL -o ../wilayah.sql https://raw.githubusercontent.com/cahyadsn/wilayah/master/db/wilayah.sql
cd ../id-upstream
for f in provinces regencies districts villages; do
  curl -sL -o $f.csv https://github.com/lokabisa-oss/region-id/releases/download/v1.0.1/$f.csv
done
```

## Resume procedure (new country or follow-up)

1. Read this file and the owning package docs first.
2. Run step 1 (structural) from the CSVs; check the existing overlay row
   and provider test for prior claims.
3. Pick oracles before verdicts: one official-truth source for the tree,
   two independent signals for postcodes (place names + coords, or
   place names + postal directory). Snippets are not evidence — open
   sources.
4. Encode everything in a new gate + Pest pins + docs in the same pass.

## Blocked / revisit log (rule)

When a check stops short of full certainty — rate limits, dead sources,
ambiguous evidence, guessed URLs — do not silently absorb it. Log it here
with four lines: (1) what was attempted, (2) why it stopped, (3) the
workaround used plus its confidence impact, (4) exactly what would unblock
it. Revisit entries when the blocker clears; delete the entry when
resolved. A workaround that "mostly worked" is still a workaround until
the unblock condition is met.

A source is only "absent" after alternatives have been ruled out. Before
logging, try: obvious mirrors and alternative paths on the same host, a
second independent directory covering the same ground truth, and the
official source even when it is heavy to use. Log what was tried.
Confirming a 404 and stopping is not a completed check.

### Open

1. **Weak single-evidence ID verdicts (residual: 3 holds + vintage
   program).** CLOSED 2026-10-06 (6 relinks + 2 drops + 9 proven):
   retried against the official Pos Indonesia directory (directly
   queryable: POST `kodepos.posindonesia.co.id/CariKodepos`, field
   `kodepos=`) + youbianku / OSM / WPC / village tree. Structural
   finding: the GN-family directories are an OLD vintage — whole
   districts renumbered since. Relinks: 99674→Boven Digoel,
   20524/20525→Medan, 92661→Sinjai, 98865→Dogiyai, 84111→Kota Bima.
   Drops: 52191 (WPC page error), 34663 (zero-source). Proven: 26235,
   23612, 42114, 42181, 46371, 97611, 74714/74715, 93915. 15220 stays
   dropped (reason upgraded: stale-superseded by 15424). Gate block
   table + anchors + Pest pins + doc05/overlay updated deliberately
   in the same pass (9,359 codes). Report: `/tmp/geo-verify/OPEN/ID/
   REPORT.md`. Still open: 91954 Lutra, 46166 Kota Tasik, 87256
   Sumba Barat (single-sourced; unblock: old Pos book or addressed
   usage). Follow-up vintage program (official-new codes absent from
   the overlay: 20258/59, 15421–29, 42197, 46571, 84164, 92562,
   9765x, 5249x, 46116/17; plus 98864/99671–78 re-verdicts, 97651→
   Malteng check) recorded in the retry report, not actioned here.
   (Prior contra-sweep resolutions stand: 53331 → Purbalingga, 30914 →
   Banyuasin, 94714 Banggai confirmed, 91671 dropped as typo-dupe of
   92671/92672.)
2. **MY Sabah/Sarawak residuals (residual: Langkon + 3 mediums).**
   CLOSED 2026-10-06 (5 fixes + 39-town sweep): retried against
   Sarawak gazettes (Sinar), MOH Hajj PDFs, SKAS gov lists,
   postcode.my + addressed usage (135 files). Melalap/Sepulot/
   Benoni weak → PROVEN; Tulid no-row vindicated (kampung);
   Song DK closed as non-entity (1873→full district 2 Apr 1973);
   Lachau + Sungai Tenggang rows + 95000 added. Fixes: 96510→Pakan
   primary + 96100 Pakan drop, Tandek 89050→89100, Beluru 98050
   dual. New `gate_my.py` + Pest pins + doc05/overlay updated
   deliberately in the same pass (2221 areas / 3048 codes /
   3924 legs). Report: `/tmp/geo-verify/OPEN/MY/REPORT.md`.
   Still open: Langkon 89050 HELD (directory vs school-box zone,
   zero addressed either way); Pekan Paitan 90107 + Pekan Sook
   89000 medium (town+zone proven, addressed string wanted);
   Pekan Karakit 89050 medium-high (optional seal). Unblock: one
   fetched addressed usage per item (JKN Sabah lists, district
   offices, PeKa B40 from clean egress).
3. **Padawan L3 follow-up (CLOSED — premise rebutted, L4 executed).**
   CLOSED 2026-10-06 (L3 program): the bundled L3 tier already matches
   the official state district list 45/45 (state portal table × enwiki
   district list, no new-district news 2025–2026) — Padawan is NOT a
   district (portal Sub-Districts column, Kuching division DK office,
   gazetted sub-district 11 Aug 1983; full status still a proposal),
   so zero L3 rows were added. The L4 residual executed instead:
   Padawan rejoined as `daerah_kecil` under Kuching (admin-only, no
   postal links). Gate + Pest + doc05 updated deliberately in the same
   pass. Report: `/tmp/geo-verify/OPEN/PADAWAN/REPORT.md`. Unblock
   for any future flip: a Sarawak Gazette district proclamation
   (revisit only on 2 new positive signals).
4. **BN finder-extract kampung gaps (residual: Kedayan 1v1 + Amo B/C).**
   CLOSED 2026-10-06 (17/21 gap kampungs): retried against Buku
   Poskod Edisi ke 2 (Dec-2018, both 2018 Wayback snapshots) +
   the Mapanet family (56ok/postcodebase/postcode.info/postalcoder
   share one 2013–2015 dataset with phantom rows — one lineage,
   corroboration-only). All 17 PROVEN codes already bundled incl.
   Lumapas B BJ3524 and Amo PD1151. Gate + Pest holds pinned
   deliberately in the same pass (zero data changes).
   Still open: Sungai Kedayan A/B BK1711/BK1511 vs family
   BN1711/BN1511 (genuine 1v1 — incumbents kept, flip needs
   2-signal proof); Amo B/C PD1351/PD1551 (family-only,
   single contaminated lineage — no fill). Unblock: live finder
   queries (`103.4.188.167/app/api/values`, TCP-dead 2026-10-06
   but reached hours earlier — retry from clean egress) for
   Kedayan 'A'/'B', Amo 'B'/'C', Lumapas 'B'; or addressed mail
   bearing a disputed code; or a Brunei Post renumber notice.
   Report: `/tmp/geo-verify/OPEN/BN/REPORT.md`.

5. **M1 carry-forwards (BH/LB/DJ).** BH: 479 shipped vs SLRB 478
   recall (off-by-one unresolved; no SLRB block list — data.gov.bh
   stats only) + 573 uncovered (single Mapanet Janabiyah row, not
   filled). LB: Tripoli caza codeless in every source (Mapanet
   Trablous/Mina rows empty, no 56ok cards); 3868 Bsharri secondary
   original-build-only (fresh signals Koura-unanimous, kept
   conservatively); 3911 Koura→Bsharri medium-strong (no wiki
   oracle). CLOSED 2026-10-05: hyphenated `1107-2090` now keys
   the Baabda row first (`[$code, NNNN-NNNN, NNNN]`), pinned in
   `PostalCodeLookupKeysTest` (78 passed).
   DJ: 77102–77104 UPU-table-only; 2024 census 18-unit scheme vs
   classic 20 retained (no decree found). Unblock: SLRB block list;
   LibanPost directory access or addressed-sighting fills; INSTAD
   decree note. Any change must update the gate block table and
   test pins deliberately, never silently.
6. **B12 CF carry-forward (spellings only; 84-vs-85 CLOSED).**
   CLOSED 2026-10-06 (count half): RNL post 69002 (2024-06-04,
   fetched full text) quotes the Loi du 21 janvier 2021 itself as
   creating 20 préfectures + 85 sous-préfectures, and OCHA COD-AB
   v02 (fetched xlsx) lists exactly 20 admin1 + 85 admin2 with
   per-prefecture counts matching the gate cell-for-cell; the
   "84" is the Dec-2020 headline's own arithmetic slip.
   Still open (spellings): `Amdafock` vs `Amdafoc`, `Nana-Outa`
   vs `Nana-Ouata`. Retry 2026-10-06: no JO scan, no assembly
   copy (domain lapsed ~2021, now spam), no Décret 24.149/150/151
   scan on any reachable source; droit-afrique bot-walled (403);
   `.cf` government hosts DNS-dead. New evidence: 12+ RNL
   articles + Oubangui's own sidebar slug use house -ck
   (transcription #63 `Amdafoc` looks like a typo); WHO PHSA
   Dec-2025 gives `Nana-Ouata` full-weight institutional
   currency; COD-AB writes `Nana-Outa` + neutral `Am-Dafok`.
   Pinned spellings unchanged (medium / medium-high). Unblock: a
   manual-browser pass on droit-afrique RCA holdings, an SGG JO
   scan, ministry Facebook decree images (May–Jun 2024), or an
   ICASEES-validated COD-AB v03+. Any change must update the gate
   membership table and test pins deliberately, never silently.
7. **B13 FR carry-forward (CLOSED — swept, zero fills).**
   CLOSED 2026-10-06: the "no stable ID column" premise was wrong
   — Hexasmal's raw file carries `#Code_commune_INSEE` on all
   39,192 rows, so the crosswalk built trivially (6,328 codes,
   0 unmapped, geo.api tie-break on all 35,007 INSEE). Downward
   diff is EMPTY: 6,051/6,051 metro codes already bundled, 0
   dept-attribution deltas, 24 cross-dept codes = gate's 24 duals
   exactly (B13's only-ever candidate 93380 was already filled).
   277 overseas codes routed out by design (136 DOM → GP/MQ/GF/RE/YT
   files, 139 COM → PM/WF/PF/NC, Monaco + Clipperton excluded).
   Zero data changes; no gate/test/doc changes needed. Crosswalk +
   report in `/tmp/geo-verify/OPEN/FR/`.
8. **B13 GB carry-forward (PAF half only; NI half CLOSED).**
   CLOSED 2026-10-06 (NI half): NISRA CPD_LIGHT Jul-2026
   (`explore.nisra.gov.uk/postcode-search/CPD_LIGHT.csv`, 49,256
   active units, open) confirms all 17 BT flips at 81–100% and
   agrees with NSPL on 80/80 BT primaries (99.98% unit agreement);
   BT75 flipped to Mid Ulster (CPD 73/136 + NSPL 72/136 +
   Fivemiletown town majority; dual F&O leg kept). Gate + Pest +
   doc05 updated deliberately in the same pass.
   Still open (PAF half): Royal Mail PAF labels for BN91/GIR-class
   drops and the 10 NSPL-live large-user-ungeocoded outwards (GIR,
   IM99, CH90, EN77, LS78, PO24, S94, SN80, SR43, TW98). Stopped:
   PAF is a paid product. Workaround: holds pinned in gate_gb.py.
   Unblock: PAF access to re-label the 10 holds. Nothing needed
   for pcio (no bulk outcode endpoint; single-GET exemplars done).
9. **B14 LR carry-forward (omitted print rows + label normalizations).**
    Attempted: district-membership certainty for all 15 counties from
    the LISGIS 2022 report App.B. Stopped: B4/B8 omit one row each in
    print (Owensgrove, Dugbe River — both retained via exact gap
    arithmetic + triple-sealed county totals + S8/COD; CDA-Sinoe prose
    affirms Dugbe district); report prints qualifier-style labels
    ("Commonwealth Robertsport" wrap, "District Number N (clan)") and
    typos ("Lousana", "New georgia"). Workaround: short-form
    normalization (Commonwealth, District #N) + typo corrections
    (Louisiana per both CDAs, Barnersville per MIA/judiciary);
    Gbarpolu postal still codeless (Bopolu `?` in philib, held).
    Unblock: an LISGIS errata/corrigendum or county-profile reissue
    confirming the two omitted rows and the qualifier labels; a
    Bopolu postcode from MOPT/postal docs. Any change must update
    gate_lr.py tables and Pest pins deliberately, never silently.
    Update 2026-10-04 (B14-PDF reconciliation, user-supplied final
    report): the omitted-row half is CLOSED — B4 gap +14,422 seals
    Owensgrove, B8 gap +17,478 seals Dugbe River, B2 gap +17,986
    seals Gounwolaila under Gbarpolu while B3/B5 gaps −17,986 each
    prove its two printed rows spurious dups, and B11 sums exactly
    (no 8th district; the "Barobo 18,758" figure was void
    mixed-frame arithmetic and is struck). Zero data changes;
    `pdf-*` gate + test pins added. Still open: Bopolu postcode —
    retry 2026-10-06 exhausted the open web (0 value assertions
    anywhere): MOPT 2016 table prints Gbarpolu affirmatively
    blank, philib live still `?` (same lineage), MOPT 2025
    charter confirms the Bopolu office operates but publishes no
    code, GeoNames has no LR postal dump, mirrors/WPC/Nominatim/
    enwiki all codeless, postalcoder 00000 junk, Mapanet
    paywalled. Hold vindicated. Unblock: licensed Mapanet LR
    dump, UPU POST*CODE access, a direct MOPT/Liberia Post
    request, or an addressed-mail sighting.
10. **B14 ET carry-forward (residual: town codes + office table).**
    CLOSED 2026-10-06 (1230): added to Addis Ababa single — cheat
    + vendor pool + addressed Beseka School `PO BOX 28/1230` (3
    lineages). CLOSED 2026-10-06 (1150): Sheger City secondary
    added, Addis primary kept (EHRCO press + Anbessa bank data;
    `et:zone:sheger-city` already existed). CLOSED 2026-10-06
    (Tigray): shorts kept — the Tigray Finance Bureau's own
    report uses BOTH forms, so no canonical form exists; longs
    recorded as variants. CLOSED 2026-10-06 (Somali): 6 specials
    (COD-AB town units + DDSI 2016 record; Harawo = regular
    Fafan woreda). Gate + Pest + doc05 updated deliberately in
    the same pass (51 codes / 83 legs / 23 multis). Still open:
    Dessie/Woldiya/Sekota town codes (EthioPost's 629-branch
    office API carries names only, zero postcodes; no vendor/
    Nominatim/university rows; answers.com 3000 junk rejected)
    + the vendor Abergele-6200 vs bundle-7220 contradiction
    (both single-signal, held). Unblock: an EthioPost postcode
    publication or explicit-code addressed sightings (box
    numbers do not count).
11. **B14 MG carry-forward (residual: 3 holds).** CLOSED
    2026-10-06 (29/32): the unsealed set re-derives to 32 codes;
    29 sealed by WP town/commune articles + OSM calculated
    postcodes + addressed usage (WFP/hotel sightings) over the
    French-list base. Lineage correction recorded: Mapanet +
    youbianku + postcodebase + 56ok are ONE lineage (shared
    quirks), so B14's youbianku corroboration was weak —
    replaced by the new seals. Paositra live site has a
    193-agency map but no postcode table; postcodesdb dead,
    postcode.info MG leaves 404. Zero data changes; gate seals
    + Pest headline pins + doc05/overlay updated deliberately.
    Still open: 111 Antsirabe II, 323 Manandriana, 606
    Ankazoabo-Atsimo (WP fully exhausted; 110-bleed + 223-noise
    documented). Unblock: rural-district addressed usage per
    code or the UPU POST*CODE DB.
12. **B14 BA carry-forward (manual-text + unit grain + 55-vs-50).**
    CLOSED 2026-10-06 (563 codes / 625 legs / 57 multis): the 2013
    HP routing table (561 codes, operator tags, byte-identical across
    2012/2013 captures) superseded the offline manual, and all three
    live operator networks were fully enumerated (Pošte Srpske 234
    codes, BH Pošta 240, HP Mostar 91) — delivery-vs-unit grain
    settled for all 65 WP-only codes. Filled 47: 37 PROVEN-live
    (15 Sarajevo units on the WP split, duals 71124/71213, Kiseljak
    71275/71335, 72293, 76281/76298, 80202/80205/80246, 10 Mostar
    units, 88221/88322) + 10 beyond-65 (71126, duals 71214/71216,
    73300, 79101, 74231, 74273, 71212, 71218, 71323); dropped 79293
    (erroneous Opara, replaced by 72293); retired 74321/75000/88267
    confirmed absent with successors live; 55-vs-50 recorded as
    dropped-unreconcilable (no snapshot; multi-count moved with the
    new duals). Gate + Pest + doc05 + overlay updated deliberately
    in the same pass. Residual holds (gate-pinned absent): 10
    LIKELY-live Hodovo-class delivery codes, 15 likely-retired unit
    codes, all single-lineage singletons, weak keeps 78108/78249/
    74221 + 75248 Čelić leg. Unblock: one addressed-usage sighting
    per held code (78249 also needs the Laktaši-vs-Srbac Razboj
    disambiguation; 79291 wants the Istočni Drvar kontakt page).
    See the retry REPORT unblock index at
    `/tmp/geo-verify/OPEN/BA/REPORT.md` (§"Unblock index").
13. **B14 MU carry-forward (CLOSED transcription; link-audit
    + enhancement residual).** CLOSED 2026-10-06 (replication):
    the MP finder yielded a plain same-page POST endpoint and a
    full 146-town harvest (4,283 rows) replicates 1,990/1,990
    codes exactly; MDPA 2024 street snapshot replicates
    1,987/1,990 with 6 stale/typo rows adjudicated to the bundle
    (publication-independent but MP-derived — proven to
    MP-operator level). Zero data changes. Still open: (a) the
    link-level code→area audit (naive town join 1,349/1,990 —
    needs the integrator's `mp-table.json` replay against
    gate_mu.py counts); (b) a non-MP ground-truth oracle
    (documented negative: GN/WPC/postcodebase absent, OSM 406,
    Nominatim junk, POST*CODE walled); (c) the Rodrigues
    5-sub-office enhancement (banked in OPEN/MU scratch).
14. **B14 IT carry-forward (residual: DPR number + sealed holds).**
    CLOSED 2026-10-06 (sigla): SU PROVEN — governo.it CdM n.168
    preliminare (09-04-2026) + n.181 definitivo (14-07-2026,
    Consiglio di Stato opinion incorporated) + Lega press explicit
    SU↔Sulcis mapping + ItalyHeritage MIT-formalization note;
    ISTAT Feb-2026 CI is stale (predates the regulation). Filled
    + gate/pins/doc05 updated deliberately in the same pass.
    CLOSED 2026-10-06 (NSC seconds): rate block gone; all 7
    Cesena/Ravenna fills re-confirmed live by fresh NSC street
    tables + official Poste.it Variazioni-CAP delta bulletins
    (2025–2026, 15 PDFs fetched; no full CAP table exists at
    poste.it — deltas only, method documented).
    Still open (residual): (a) DPR number / GU publication of the
    sigla regolamento (value unaffected; GU search WAF-walled,
    normattiva timeout); (b) 09132/09133 + 19127–19130 holds,
    now triple-sealed absent (fresh NSC NOT_FOUND + en/it.wiki
    ranges skipping exactly + WPC skips vs CJ-2019 padding
    alone) — revisit only on 2 new positive signals. No data
    change for (a)/(b); gate keeps asserting absent.
15. **B14 UY carry-forward (residual: 3 holds).** CLOSED 2026-10-06
    (27 of 30 holds resolved + Lezica proven): retried against IDElUy
    municipio polygons (pip), Corte Electoral plan-circuital (7,197
    circuits), INE loccen/loccat, Falling Rain, OSM/Nominatim/Photon
    (390 files). 5 flips (12100 E→F, 12000 F→D, 35200 FL→Cerro Chato,
    60200 dept→Quebracho), 7 drops (12000-E, 20500-Lascano/Maldonado,
    33000-Vergara, 50000-Belén, 70000-Tarariras, 60200-dept), 16 adds
    (8 MVD slivers, Soca lean, Conchillas, TT-dept, Chapicuy, CChato).
    Gate MULTIS/SINGLES/HOLDS + Pest pins + doc05/overlay updated
    deliberately in the same pass (124 codes / 351 legs / 93 multis).
    Vindicated: ties→incumbent, vote-over-seat, Pando, Agraciada split,
    3 names, G-single Lezica. Report: `/tmp/geo-verify/OPEN/UY/REPORT.md`
    (+ PART-MVD/CAEAST.md). Still open: H7 37000→Tupambaé (Rincón de
    Tacuarí unplaceable — unblock: Intendencia CL list / IDE zone map /
    gazetteer coord / registered map-grid reading); H13-SJ 91500→San
    Jacinto (paraje "Blanco" — unblock: CE/census/directory placement);
    H27-carmelo 70000→Carmelo (Santa Rosa 50 m straddle — unblock:
    sub-100 m placement vs the border). Flags (not actioned):
    30100→Montes (firm), 41100 dept→Ansina swap (single-signal),
    H30 belt-and-braces dept leg.
16. **B15 LA carry-forward (EPL district-office layer + VTE overlap).**
    Attempted: postal-truth proof for 26 codes / 148 legs (EPL
    official API 7,781 village rows, Mapanet 9/9 VTE + Namtha
    03000, addressed usage, LSB/census tree oracles; Meun 10-13,
    SV 3 identity fixes, XSB rotation, 2 qualifier strips, CH
    16010->16000, XSB 10000->18000 applied). Stopped: EPL PP0D0
    district-office blocks stay below bundle grain (re-granularizing
    26->~150 codes is a redesign, not verification); VTE EPL
    blocks overlap districts (01002/01004/01082 shared) so VTE
    keeps 2004-UPU subs; LM 03001 thin 2-row sub-code held;
    EPL misses Nong (SV) + Morkmay (XK) + 6 KH districts (zone
    fallback); UPU parcel compendium + countryzipcode PDF
    unreachable (http 000). Workaround: zones verified against
    EPL capital-district bases; holds pinned in gate_la.py. Full
    EPL join saved at /tmp/geo-verify/B15/small/LA/epl-by-pcode.json
    for the future batch. Unblock: an EPL district-primary rule
    for VTE overlaps, or a re-granularization decision. Any
    change must update gate_la.py tables and Pest pins
    deliberately, never silently.
17. **B15 CH carry-forward (sliver holds + Uvrier vintage; La
    Cibourg prong CLOSED).** CLOSED 2026-10-06 (2333-drop
    evidence, now triple-signal): Renan commune tourism page
    places La Cibourg on its heights (2616) and La Ferrière
    commune runs a dedicated La Cibourg heritage article (site
    footer 2333 La Ferrière) — the Bernese 2333 slice stands,
    no data change. Retry 2026-10-06 on the rest: fresh GN dump
    (2026-10-05) still has no 2827→Thierstein row; Beinwil
    commune site + 2 info-blatt PDFs show PLZ 4229 only;
    post.ch bot-walled; OSM Overpass finds 15 addr:postcode=2827
    objects on the SO side (same AV/GWR lineage as swisstopo —
    semi-independent, does not clear the GN convention); Uvrier
    has no post-2020 figure anywhere probed (Sion/Ayent sites,
    press, plan docs silent; 1958 flip stands on the 76% gap).
    Unblock: a Swiss Post PLZ entry for 2827 naming Beinwil
    (SO), one non-register addressed sighting at Hof Grosse
    Rotmatt 138 / Scheltenstrasse 152 in 2827 Schelten, or a
    Sion locality-breakdown / BFS STATPOP Uvrier pop (reverse
    1958 only if >~2,400). Any change must update gate_ch.py
    tables and Pest pins deliberately, never silently.
18. **B15 UA carry-forward (residual: Berestove + occupied
    edges + 2 multis).** CLOSED 2026-10-06 (7 gaps): the live
    Ukrposhta operator API (index.ukrposhta.ua street-grain +
    serving-office records, no key) + GeoNames prove all 7
    (27019/26135/26230/52029/08146/84423/64372 value + link;
    infoboxes in 5/7 carry the neighbour's code). CLOSED
    2026-10-06 (90124): flipped to berehove-sole — the B15
    reform-wholly premise was false (old Irshavskyi split
    Kamianska→Berehove); 4 lineages. Gate + Pest + doc05
    updated deliberately in the same pass (incl. Mayak
    53542→53524 correction). Still open: Berestove infobox
    32911 (corrupt back to Feb-2023; operator confirms 63744 =
    Berestove/Kupiansk, 32911 zero rows); 47431/82563 multis
    (directory favours sole — Pakhynia 47428, Ivashkivtsi
    82525 — 1v1 pending a municipal second signal); occupied-
    territory holds (93279 operator-blind; OSM edges stable-
    but-weak). Unblock: municipal passports / addressed mail
    for the multis; post-liberation operator data for 93279.
19. **B15 NI carry-forward (finder/sector tail only; SJN +
    Madriz map CLOSED).** CLOSED 2026-10-06 (SJN): Ley N° 434
    (aprobada 2002-07-15, Gaceta N° 149) Art. 1–2 renames the
    municipio to San Juan de Nicaragua + consolidated Ley 59
    (2021) + INIDE anuario2023 (31×, 0× del Norte) + operator
    print-all 4 captures + enwiki — 5 signals; renamed with
    gate/pins/doc05 updated deliberately in the same pass (id
    keeps the pre-2002 slug, same as waspan). SJRC "del"
    law-backed too (Decreto 981 of 1964 + consolidation; note
    INIDE anuario2023 prints "de" ×10, law wins). CLOSED
    2026-10-06 (map recovery): MADRIZ.jpg re-captured from
    Wayback 20151225082009 (file-verified 600×429 CS5 JPEG,
    family-fit; content pixels not yet eyeballed — needs one
    human viewing pass or crop-upscale OCR). Still open: live
    Correos finder (Cloudflare persists on live + JS challenge;
    Wayback grid page 1 only, Arquivo.pt shell-only, IA CDX hit
    an outage window) and the ~117 scheme-only Managua sectors.
    Unblock: JS-capable finder retry from clean egress, Arquivo.pt
    print-all replays, or IA CDX enumeration of grid pages 2..N.
20. **B15 CU carry-forward (residual: dual-validity + 10
    sectors + singletons).** CLOSED 2026-10-06 (API): the office
    search was never dead — `POST /wp-admin/admin-ajax.php`
    (`action=oficinas`) is live; 638 office rows pulled across
    11 provinces (reusable oracle in OPEN/CU scratch; provinces
    11–15 unpulled, resume script saved). New-scheme keeps
    vindicated (operator 100% 35/37/38); old-code census:
    32300/32400/32500 2-signal stale (existence only),
    32000/33600/33700/33800/34140/34150/34390/22800 single,
    32100/32200 zero — all stay unfilled. REBUTTED (do not
    fill): 74680 La Jagua (office carries 74540), 74360 San
    Miguel de Bagá (74370 + Nuevitas, not Guáimaro).
    Still open: 10 Camaguey sectors (operator- + OSM-silent);
    62410 (likely off-by-10, all sources 62400); 77200
    (de-facto 77210); 34390/34140 old-scheme; 35110/37330/
    37550/34360 operator-only finds. Unblock: a Correos
    renumber notice with a dual-validity clause (likely
    paper-only or pre-2015 web), addressed mail bearing an
    old/new code, or a second lineage per sector code.
21. **B15 RS carry-forward (CLOSED except 11161 + notes).**
    CLOSED 2026-10-06 (~150 new PAK probes + PlanPlus directory
    + OSM + addressed usage): 11040→Savski Venac P + Voždovac S
    (Rakovica drops), 11050→Zvezdara P + Voždovac S (delivery-
    office rule, Vračar drops), 17508→Vranje P + Trgovište S
    (Sveti Ilija = Barelić hamlet PO), 18110→Niš P, 18251→Niš P
    + Nišava span marker, 18252 Merošina-sole, 18411
    Doljevac-sole, 91/92 L1 branch codes to city-municipalities
    (office-street method per code; DM list graded zone-hub,
    never decisive). Gate + Pest + doc05/overlay updated
    deliberately in the same pass (1411 legs, 67 multis).
    Still open: 11161 generic (zero signals anywhere —
    unblock: Pošta lokacije JS retry, scribd Pošta Lat doc, or
    an addressed sighting); WPC-only slices (18251-Merošina,
    17508-Trgovište — unblock: RGZ register / delivery-area
    doc / addressed mail); 18252 single-oracle caveat;
    11113/11140 borderline rows (revisit on contrary evidence).
22. **BN Belaban PD2451-vs-PD3151 (finder-vs-Buku 1v2).**
    Attempted: BN gap retry §6 (2026-10-06) cross-checked the
    bundled finder value. Found: bundle carries finder PD2451
    for Belaban (Amo) but Buku Poskod (both 2018 snapshots) +
    the Mapanet family are unanimous PD3151. Stopped: out of the
    retry's 20-kampung scope — no verdict rendered. Workaround:
    finder PD2451 stays bundled, pinned in `gate_bn.py` + Pest.
    Unblock: live finder query for Belaban from clean egress, or
    addressed mail bearing PD2451/PD3151, or a Brunei Post
    renumber notice. A flip must update gate + pins + doc05
    deliberately, never silently.

## Standing lessons

- A check built from the assumption under test proves nothing: the
  original ID overlay and GeoNames admin2 agreed with each other and were
  both wrong. The oracle must be independent (village-tree place vote,
  coordinates, postal book).
- Voters need a leash: province-constrained matching is blind to
  cross-province errors; always run the unconstrained safety net after.
- Missing code means "uncovered", never "invalid" — document gaps, don't
  fill from a single shaky source.
- Typo-dupes have a signature: same place label as an existing code,
  exactly one digit off, no book/GeoNames support (ID: 24 dropped, e.g.
  91671↔92671, 34562↔34762). When all three hold, drop; the surviving
  twin must already exist and be linked.
- Postal codes survive pemekaran: a district's code can stay in the old
  regency's block after a split (ID: Awayan/Juai/Halong 7146x kept
  HSU-block codes under Balangan, Rimbo Pengadang 39161 under Lebong).
  Current-admin district table beats block fit in these cases; verify the
  new regency doesn't already hold the district under a new code first.
- Town-page labels are claims, not verdicts: worldpostalcode pages
  overreach into neighbours, swap digits, and carry Timor-Leste rows on
  NTT pages. Every page-only code needs a tree/block second vote.
- Anchors must move with evidence: a gate anchor pinning an old wrong
  link (ID 78356 Kubu Raya → Landak) is updated with its justification,
  never silently.
- A GeoNames postal dump can be non-postal data: AE.txt holds 178,171
  Makani geocode rows and zero postcodes. Check code shape before
  trusting dump presence/absence as a system signal.
- UPU's own lists contradict: Comoros sits on both the require
  (Aug-2026) and do-not-require (Sep-2025) lists. Adjudicate with
  the country profile + dumps + live usage, and record the conflict.
- CSV line endings are mixed per file (LB postal files are CRLF, DJ
  areas are LF). Bulk appliers must preserve each file's endings;
  assert round-trip before writing.
- Census arithmetic must stay in one frame: a county total and its
  district rows must come from the SAME table. Setting a 2008 county
  total against 2022 district rows fabricates a phantom gap (LR:
  void "Barobo 18,758", printed nowhere, struck after the final-PDF
  recheck showed B11 summing exactly). Recompute per table before
  asserting any omission.
- The postal operator's own API outranks every directory: EPL's
  7,781 village rows structurally overturned Mapanet's all-16010
  Champasak (16010 is the Champasack-district office; the zone is
  16000) and exposed 56ok-LA sidebar codes as machine gibberish.
  Dig for the operator finder/API (even through Next.js chunks)
  before trusting directory unanimity.
- Zones and offices are different grains: a province zone is only
  coherent when it equals the capital-district office base (LA:
  16000 Pakse works as CH zone; 16010 routes to the wrong district
  office). When the operator's own blocks overlap districts (LA
  Vientiane 01002/01004/01082), hold the verified coarser layer
  instead of forcing a false precision.
- Audit the worker's evidence tags, not its verdict prose: a
  hardcoded tag can claim a check the code never ran (UA: 88
  multi drops stamped twin-only-secondary-no-strict-place by an
  unconditional loop). Re-adjudicate the class from primary
  signals before applying; the sample caught 2 holds + 1 flip.
- Village infoboxes settle twin postcodes: ukwiki settlement
  pages carry both postcode and raion/hromada, disambiguating
  same-name villages (UA: Matkiv-82563, Pahinya/Karnachivka-47431
  cross-raion proofs; Luchka-42600/42547, Mayak-53542, Bubnivka-32011
  row-error proofs). Prefer them over coordinate reverse-geocoding.
- GeoNames accuracy-1 rows share junk coords: identical
  coordinates across rows of a code (UA 47431/90124) are
  centroid fallbacks, not village locations. Discard them and
  verify by name/council; use reverse-geocoding only on acc-4 rows.
- The operator can contradict itself across years: Correos 2013
  print-all said "San Juan de Nicaragua" but the 2015 map says
  "San Juan del Norte". Same-institution diachronic agreement is
  one lineage, not two; a same-source contradiction is a hold,
  settled only by the legal text (NI: Asamblea DPA law).
- OSM postcode boundaries misattribute at edges: 5/144 NI
  codes resolved against OSM (42600 Tipitapa, 46400/46600
  neighbours, 48500 Moyogalpa, 66600/66700 map-center) via the
  operator maps + a directory. Treat OSM as one vote, never the
  verdict, and always pull the second signal on flags.
- OSM can be systematically garbage in one city while reliable
  everywhere else: rural CU probes went 20/20 sensible but
  Habana-city points misplace whole codes (10100/10500/10700/
  10900/10800). Score OSM per stratum; a noisy stratum needs a
  non-OSM second oracle (CU: parish usage directory).
- Parish and diocese directories are high-grade usage oracles:
  the Havana Archdiocese lists 100+ parishes with C.P. codes,
  settling base disputes (10200/10500/10900/11400), confirming
  fine sectors (Mayabeque 34xxx), and exposing duals (10600).
  Look for church, school, and government office directories
  when the operator finder is dead.
- A renumber splits oracle lineages by vintage: operator + UPU
  carry new Artemisa 35/37/38 while Mapanet + OSM + usage carry
  old 32xxx. Agreement within a vintage is one lineage; the
  operator's current lineage wins, and old codes stay unfilled
  until dual-validity is proven with addressed mail.
- Postal operators with street-level finders settle dense-city
  disputes outright: Pošta Srbije PAK probes (settlement +
  street → delivery post + opština) confirmed 8 flips, 7 fills,
  2 refinements, and 40+ village memberships, overruling OSM
  twice (11231 misplaced point, 11102 half-right polygon).
  Harvest real street names from the finder's own autocomplete
  before probing; parse per-row settlement columns since common
  street names return multi-settlement result sets.
- GeoNames web hierarchies are algorithmic, not authoritative:
  the 31311 Bela Zemlja admin2 (Čajetina) contradicted the
  operator's delivery rows (Užice villages Drijetanj/Ljubanje)
  and lost. Use GN hierarchy as a lead, never as the deciding
  vote on a hamlet.
- Nominatim concurrent batches trip 400-blocks: strictly
  sequential queries with a fresh UA recovered full service.
  Never run two Nominatim clients at once.

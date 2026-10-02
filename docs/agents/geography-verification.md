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
| All other countries | Todo | Same ladder; start with step 1 + existing overlay-row claims |

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

1. **Weak single-evidence ID verdicts.** A handful of verdicts rest
   on range-only or tie-break reasoning and are pinned in the gate as-is.
   Fill: 99674 Mappi (tentative), 20524/20525 Deli Serdang by range,
   92661 Bulukumba (9-8 split), 26235 Payakumbuh, 15220 dropped as
   unverifiable. Rebuild: 98865 Paniai (single source), 23612 Aceh
   Barat + 42114/42181 Serang + 46371 Pangandaran (single generic
   voters; 42181 re-confirmed Kab over city page claim), 97611 Tual
   city / 84111 Bima regency (genuinely shared codes; city-holds-primary
   vs 11-0 place majority). Town pages: 91954 Lutra (no Limbong
   district; page+block only), 46166 Kota Tasik (only Tasik-area tree
   match is the city district), 52191 Tegal regency (page place
   Sumurpanggang is city; code fits regency run), 74714/74715 Sukamara,
   87256 Sumba Barat, 34663 Tuba (page place contradicts block).
   Contra sweep: 93915 Kolut (GN villages vote Kolaka; page + 5/6 run
   win). Resolved by the contra sweep: 53331 → Purbalingga, 30914 →
   Banyuasin, 94714 Banggai confirmed, 91671 dropped as typo-dupe of
   92671/92672. Unblock: corroborate or correct against a second
   postal source; any change must update the gate block table and test
   pins deliberately, never silently.
2. **MY Sabah/Sarawak residuals (no block; evidence shortfalls).**
   Attempted: full East-MY rectification against gazettes, directories,
   addressed usage. Stopped: directory silence / no town proof for a few
   items, all recorded in `05-country-data.md` (Sabah/Sarawak audit
   sections). Workaround: weak keeps for Melalap/Sepulot/Benoni
   (single-road usage vs directory silence); no rows for Tulid (DUN
   named for a kampung, no town), Lachau/Sungai Tenggang (Pantu pekan,
   no codes), Song daerah kecil (town row only, DK unproven);
   ~60 rows stay linkless for lack of own-code evidence. Confidence
   impact: contained — each is an explicitly marked keep/skip, not a
   guess. Unblock: new gazette/directory/addressed evidence for the
   named items; re-run the state gate after any change.
3. **Padawan L3 follow-up (deferred program, not a block).** Attempted:
   Sarawak rectification. Stopped: Padawan is a district whose L4 row
   was removed as misplaced; the L3 tier itself was out of scope.
   Workaround: none — explicitly deferred, district missing from L3.
   Unblock: run the L3 program for the affected tier; see the Sarawak
   audit residual note.
4. **BN finder-extract kampung gaps (no block; source shortfall).**
   Attempted: full Brunei verification against the Brunei Post finder
   extract. Stopped: the extract itself returned no data for a few
   kampungs. Workaround: those kampungs carry no code here either.
   Unblock: a newer or more complete Brunei Post extract.

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

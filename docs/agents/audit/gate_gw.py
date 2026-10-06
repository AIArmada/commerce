import csv, re, sys
from collections import Counter
# Guinea-Bissau gate. M2 verification (2026-10-03): tree = en.wiki
# Sectors list + region articles + ISO 3166-2:GW; postcodes = Mapanet
# full pull (150 locality rows, scraped 2026-10-03 incl. coords) with
# per-row OSM Nominatim sector re-derivation (150/150 resolved).
# Pins the CORRECTED state: sector gw:sector:bafata is 'Bafatá'
# (accent fix, areas line 11). Until that 1-cell change lands, the
# 'sector-name-bafata' check FAILs and everything else PASSes.
# Run from repo root: python3 docs/agents/audit/gate_gw.py
A = './packages/addressing/resources/geography/guinea-bissau-address-areas.csv'
C = './packages/addressing/resources/geography/guinea-bissau-postal-codes.csv'
L = './packages/addressing/resources/geography/guinea-bissau-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A, encoding='utf-8-sig')))
codes = list(csv.DictReader(open(C, encoding='utf-8-sig')))
links = list(csv.DictReader(open(L, encoding='utf-8-sig')))
byid = {r['source_id']: r for r in rows}
check('areas-47', len(rows) == 47, str(len(rows)))
l1 = [r for r in rows if r['level'] == '1']
check('l1-8-region-1-as', sum(1 for r in l1 if r['type'] == 'region') == 8
      and sum(1 for r in l1 if r['type'] == 'autonomous_sector') == 1)
check('l1-root', all(r['parent_source_id'] == '' for r in l1))
l2 = [r for r in rows if r['level'] == '2']
check('l2-38-sector', len(l2) == 38 and all(r['type'] == 'sector' for r in l2),
      str(len(l2)))
check('l2-parents-resolve', all(r['parent_source_id'] in byid for r in l2))
# ISO 3166-2:GW first-level divisions (UPU region list agrees).
iso = {'gw:region:bafata': ('Bafatá', 'BA'), 'gw:region:biombo': ('Biombo', 'BM'),
       'gw:autonomous_sector:bissau': ('Bissau', 'BS'),
       'gw:region:bolama': ('Bolama', 'BL'), 'gw:region:cacheu': ('Cacheu', 'CA'),
       'gw:region:gabu': ('Gabú', 'GA'), 'gw:region:oio': ('Oio', 'OI'),
       'gw:region:quinara': ('Quinara', 'QU'),
       'gw:region:tombali': ('Tombali', 'TO')}
for sid, (nm, cd) in iso.items():
    r = byid.get(sid)
    check(f'iso-{cd}', r and r['name'] == nm and r['code'] == cd, str(r))
# Leste/Norte/Sul are statistical (OSM GW-N/GW-S/E), never shipped.
check('no-statistical', not [r for r in rows
      if (r['code'] or '') in ('L', 'N', 'S')
      or (r['name'] or '').lower() in ('leste', 'norte', 'sul')])
# Full en.wiki Sectors-list name table (region article spells the
# Bafata sector 'Bafatá'; the Sectors-page piped display is unaccented).
sectors = {
 'gw:region:bafata': ['Bafatá', 'Bambadinca', 'Contuboel', 'Galomaro',
                      'Gamamundo', 'Xitole'],
 'gw:region:gabu': ['Boe', 'Gabú', 'Piche', 'Pirada', 'Sonaco'],
 'gw:region:biombo': ['Prabis', 'Quinhamel', 'Safim'],
 'gw:region:cacheu': ['Bigene', 'Bula', 'Cacheu', 'Caio', 'Canghungo',
                      'São Domingos'],
 'gw:region:oio': ['Bissorã', 'Farim', 'Mansaba', 'Mansôa', 'Nhacra'],
 'gw:region:bolama': ['Bolama', 'Bubaque', 'Caravela', 'Uno'],
 'gw:region:quinara': ['Buba', 'Empada', 'Fulacunda', 'Tite'],
 'gw:region:tombali': ['Bedanda', 'Cacine', 'Catió', 'Quebo', 'Komo'],
}
for pid, names in sectors.items():
    have = sorted(r['name'] for r in rows if r['parent_source_id'] == pid)
    check(f'sectors-{pid.split(":")[-1]}-{len(names)}', have == sorted(names),
          str(have))
r = byid.get('gw:sector:bafata')
check('sector-name-bafata', r and r['name'] == 'Bafatá', str(r))
r = byid.get('gw:sector:gabu')
check('sector-name-gabu', r and r['name'] == 'Gabú', str(r))
check('codes-52', len(codes) == 52, str(len(codes)))
check('links-62', len(links) == 62, str(len(links)))
check('distinct-52', len({c['code'] for c in codes}) == 52)
bad = [c['code'] for c in codes if not re.match(r'^\d{4}$', c['code'])]
check('code-format-4d', not bad, str(bad[:3]))
nums = sorted(int(c['code']) for c in codes)
check('range-1160-9300', nums[0] == 1160 and nums[-1] == 9300,
      f'{nums[0]}-{nums[-1]}')
dang = [l for l in links if l['area_source_id'] not in byid
        or l['postcode'] not in {c['code'] for c in codes}]
check('no-dangling', not dang, str(dang[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
check('exactly-one-primary',
      len(prim) == 52 and all(c == 1 for c in prim.values()))
check('all-served-by',
      all(l['relationship_type'] == 'served_by' for l in links))
check('codes-links-same-set', {c['code'] for c in codes} == set(prim))
plink = {l['postcode']: l['area_source_id']
         for l in links if l['is_primary'] == 'true'}
legs = Counter()
for l in links:
    legs[(l['postcode'], l['area_source_id'])] += 1
def has(pc, sid, primary):
    return any(l['postcode'] == pc and l['area_source_id'] == sid
               and l['is_primary'] == primary for l in links)
# Bissau 12-code set (Mapanet Bissau rows; all OSM -> Autonomous Sector).
bissau = ['1160', '1190', '1220', '1250', '1280', '1400', '1430', '1490',
          '1600', '1640', '1670', '1890']
check('bissau-12', sorted(pc for pc, sid in plink.items()
      if sid == 'gw:autonomous_sector:bissau') == bissau)
# Per-region primary-code counts (primary sector's parent region).
regcount = Counter(byid[sid]['parent_source_id'] for sid in plink.values()
                   if sid != 'gw:autonomous_sector:bissau')
exp = {'gw:region:bafata': 6, 'gw:region:gabu': 5, 'gw:region:biombo': 3,
       'gw:region:cacheu': 8, 'gw:region:oio': 10, 'gw:region:quinara': 4,
       'gw:region:tombali': 3, 'gw:region:bolama': 1}
for pid, n in exp.items():
    check(f'primcount-{pid.split(":")[-1]}-{n}', regcount[pid] == n,
          str(regcount[pid]))
# Seat anchors: 5000 Bafatá, 1950 Nhacra (both 'Bissorã'/'Cumere' rows
# sit in Nhacra), 9300 Uno triple.
check('seat-5000-bafata', plink.get('5000') == 'gw:sector:bafata')
check('map-5000-galomaro-sec',
      has('5000', 'gw:sector:galomaro', 'false'))
check('map-5000-gamamundo-sec',
      has('5000', 'gw:sector:gamamundo', 'false'))
check('seat-1950-nhacra', plink.get('1950') == 'gw:sector:nhacra')
check('seat-9300-uno', plink.get('9300') == 'gw:sector:uno')
check('map-9300-bubaque-sec', has('9300', 'gw:sector:bubaque', 'false'))
check('map-9300-caravela-sec', has('9300', 'gw:sector:caravela', 'false'))
# Duals (OSM vote noted; seat holds primary throughout).
check('dual-3200-mansaba', plink.get('3200') == 'gw:sector:mansaba')
check('dual-3200-mansoa-sec', has('3200', 'gw:sector:mansoa', 'false'))
check('dual-3300-bissora', plink.get('3300') == 'gw:sector:bissora')
check('dual-3300-mansaba-sec', has('3300', 'gw:sector:mansaba', 'false'))
# 3600 JUDGMENT: OSM votes Nhacra 3 (Binar/Bissorã/Encheia rows) vs
# Mansoa 1, but the Mansoa row IS Mansoa town (12.06874,-15.31969),
# so the sector capital holds primary (ID Tual/Bima precedent).
check('dual-3600-mansoa-seat', plink.get('3600') == 'gw:sector:mansoa')
check('dual-3600-nhacra-sec', has('3600', 'gw:sector:nhacra', 'false'))
check('dual-6400-boe', plink.get('6400') == 'gw:sector:boe')
check('dual-6400-gabu-sec', has('6400', 'gw:sector:gabu', 'false'))
check('dual-8300-cacine', plink.get('8300') == 'gw:sector:cacine')
check('dual-8300-bedanda-sec', has('8300', 'gw:sector:bedanda', 'false'))
check('dual-8400-quebo', plink.get('8400') == 'gw:sector:quebo')
check('dual-8400-bedanda-sec', has('8400', 'gw:sector:bedanda', 'false'))
# 5300 REJECTION: the lone Xitole vote is a 'Bafatá'-labeled centroid
# row 40km from Bafatá town; the 3 town rows vote Galomaro. Kept
# Galomaro-only (single-row secondaries elsewhere rest on real
# locality rows: Bijine, Geba, Guileje, Jemberem, Olossato, Cutia).
check('single-5300-galomaro', plink.get('5300') == 'gw:sector:galomaro'
      and not has('5300', 'gw:sector:xitole', 'false')
      and sum(1 for l in links if l['postcode'] == '5300') == 1)
# Codeless-in-source sectors: zero of 150 Mapanet rows fall in them.
for slug in ['bigene', 'catio', 'komo', 'bolama']:
    sid = f'gw:sector:{slug}'
    check(f'codeless-{slug}', sid in byid
          and not [l for l in links if l['area_source_id'] == sid])
# Cedex/PO-box layer stays out: UPU example 1000, UPU central office
# 1011, OSM Bissau tag 1021, philatelic Codex 1029. None is a Mapanet
# locality code.
for pc in ['1000', '1011', '1021', '1029']:
    check(f'no-cedex-{pc}', pc not in set(prim))
# 2nd signals (observed, not pinned): 56ok directory (same lineage,
# name-deduped) shows every code shown inside the 52-set, 0
# contradictions; UPU profile confirms the 4-digit system; Smarty
# confirms 9999 format. osint-repo 1031/6115 are self-declared
# examples, discarded. No addressed sightings found (thin usage).
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

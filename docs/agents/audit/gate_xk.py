import csv, re, sys
from collections import Counter
# Kosovo gate. M2 revisit 2026-10-03: ONE data fix proposed (40700
# mitrovica -> skenderaj; Runike office sits in Runik, Skenderaj).
# This gate pins the CORRECTED state, so it FAILS on current CSVs
# (40700/skenderaj/mitrovica checks) until the fix is applied.
# Run from repo root:
# python3 docs/agents/audit/gate_xk.py
A = './packages/addressing/resources/geography/kosovo-address-areas.csv'
C = './packages/addressing/resources/geography/kosovo-postal-codes.csv'
L = './packages/addressing/resources/geography/kosovo-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
byid = {r['source_id']: r for r in rows}
check('areas-45', len(rows) == 45, str(len(rows)))
districts = [r for r in rows if r['type'] == 'district']
munis = [r for r in rows if r['type'] == 'municipality']
check('districts-7-L1-root', len(districts) == 7 and all(
    r['level'] == '1' and r['parent_source_id'] == '' for r in districts))
check('municipalities-38-L2', len(munis) == 38 and all(
    r['level'] == '2' for r in munis))
check('all-L2-parented', all(r['parent_source_id'] in byid for r in munis))
# District codes are an internal scheme (no ISO 3166-2:XK entry; XK is
# user-assigned). PEJ/PRI break the X-prefix pattern; pinned as-is.
dcode = {'xk:district:ferizaj': ('Ferizaj', 'XUF'),
         'xk:district:gjakova': ('Gjakova', 'XDG'),
         'xk:district:gjilan': ('Gjilan', 'XGJ'),
         'xk:district:mitrovica': ('Mitrovica', 'XKM'),
         'xk:district:peja': ('Peja', 'PEJ'),
         'xk:district:pristina': ('Pristina', 'XPI'),
         'xk:district:prizren': ('Prizren', 'PRI')}
for sid, (nm, cd) in dcode.items():
    r = byid.get(sid)
    check(f'district-{cd}', r and r['name'] == nm and r['code'] == cd, str(r))
# 7-district municipality table (Districts of Kosovo wiki table):
# Ferizaj 5, Gjakova 4, Gjilan 6, Mitrovica 7, Peja 3, Pristina 8, Prizren 5.
dmap = {'xk:district:ferizaj': ['ferizaj', 'hani-i-elezit', 'kacanik',
        'shtime', 'strpce'],
        'xk:district:gjakova': ['decan', 'gjakova', 'junik', 'rahovec'],
        'xk:district:gjilan': ['gjilan', 'kamenica', 'klokot', 'partes',
        'ranilug', 'viti'],
        'xk:district:mitrovica': ['leposavic', 'mitrovica', 'north-mitrovica',
        'skenderaj', 'vushtrri', 'zubin-potok', 'zvecan'],
        'xk:district:peja': ['peja', 'istog', 'klina'],
        'xk:district:pristina': ['drenas', 'gracanica', 'kosovo-polje',
        'lipjan', 'novo-brdo', 'obiliq', 'podujeve', 'pristina'],
        'xk:district:prizren': ['dragash', 'malisheva', 'mamusha', 'prizren',
        'suva-reka']}
for dsid, slugs in dmap.items():
    have = sorted(r['source_id'].split(':')[-1] for r in munis
                  if r['parent_source_id'] == dsid)
    check(f'map-{dsid.split(":")[-1]}-{len(slugs)}', have == sorted(slugs),
          str(have))
check('codes-127', len(codes) == 127, str(len(codes)))
check('links-127', len(links) == 127, str(len(links)))
check('distinct-127', len({c['code'] for c in codes}) == 127)
bad = [c['code'] for c in codes if not re.match(r'^[1-7]\d{4}$', c['code'])]
check('code-format-5d', not bad, str(bad[:3]))
nums = sorted(int(c['code']) for c in codes)
check('range-10000-73000', nums[0] == 10000 and nums[-1] == 73000,
      f'{nums[0]}-{nums[-1]}')
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
check('exactly-one-primary', len(prim) == 127
      and all(c == 1 for c in prim.values()))
check('all-served-by', all(l['relationship_type'] == 'served_by'
      for l in links))
check('codes-links-same-set', {c['code'] for c in codes} == set(prim))
link = {l['postcode']: l['area_source_id'] for l in links}
# Full code -> municipality map, CORRECTED state (40700 skenderaj).
exp = {'10000': 'pristina', '10010': 'pristina', '10030': 'pristina',
 '10040': 'pristina', '10050': 'pristina', '10060': 'pristina',
 '10070': 'pristina', '10080': 'pristina', '10090': 'pristina',
 '10100': 'pristina', '10110': 'pristina', '10120': 'pristina',
 '10130': 'pristina', '10500': 'gracanica', '10510': 'pristina',
 '10520': 'pristina', '10530': 'pristina', '11000': 'podujeve',
 '11050': 'podujeve', '11060': 'podujeve', '11070': 'podujeve',
 '12000': 'kosovo-polje', '12010': 'kosovo-polje',
 '12050': 'kosovo-polje', '12060': 'kosovo-polje', '13000': 'drenas',
 '13050': 'drenas', '14000': 'lipjan', '14050': 'lipjan',
 '14060': 'lipjan', '15000': 'obiliq', '15050': 'obiliq',
 '16000': 'novo-brdo', '20000': 'prizren', '20010': 'prizren',
 '20020': 'prizren', '20030': 'prizren', '20040': 'prizren',
 '20050': 'prizren', '20060': 'prizren', '20080': 'prizren',
 '20510': 'prizren', '20520': 'prizren', '20530': 'prizren',
 '20540': 'mamusha', '20550': 'prizren', '21000': 'rahovec',
 '21010': 'rahovec', '21020': 'rahovec', '21050': 'rahovec',
 '21060': 'rahovec', '21070': 'rahovec', '21080': 'rahovec',
 '22000': 'dragash', '22050': 'dragash', '22060': 'dragash',
 '22070': 'dragash', '22080': 'dragash', '23000': 'suva-reka',
 '23050': 'suva-reka', '23060': 'suva-reka', '24000': 'malisheva',
 '24050': 'malisheva', '24060': 'malisheva', '30000': 'peja',
 '30010': 'peja', '30030': 'peja', '30040': 'peja', '30050': 'peja',
 '30080': 'peja', '30090': 'peja', '31000': 'istog', '31010': 'istog',
 '31020': 'istog', '31030': 'peja', '32000': 'klina', '32050': 'klina',
 '40000': 'mitrovica', '40010': 'mitrovica', '40040': 'mitrovica',
 '40050': 'mitrovica', '40060': 'mitrovica', '40500': 'mitrovica',
 '40550': 'mitrovica', '40600': 'mitrovica', '40650': 'zubin-potok',
 '40700': 'skenderaj', '41000': 'skenderaj', '41050': 'skenderaj',
 '42000': 'vushtrri', '43000': 'zvecan', '43500': 'leposavic',
 '50000': 'gjakova', '50010': 'gjakova', '50040': 'gjakova',
 '50050': 'gjakova', '50060': 'gjakova', '50070': 'gjakova',
 '50080': 'gjakova', '50090': 'gjakova', '50100': 'gjakova',
 '50500': 'gjakova', '50550': 'gjakova', '51000': 'decan',
 '51050': 'junik', '60000': 'gjilan', '60010': 'gjilan',
 '60030': 'gjilan', '60510': 'gjilan', '60520': 'gjilan',
 '61000': 'viti', '61050': 'klokot', '61060': 'viti',
 '62000': 'kamenica', '62050': 'kamenica', '62060': 'kamenica',
 '62070': 'kamenica', '70000': 'ferizaj', '70010': 'ferizaj',
 '70030': 'ferizaj', '70040': 'ferizaj', '70510': 'ferizaj',
 '70520': 'ferizaj', '71000': 'kacanik', '71510': 'hani-i-elezit',
 '72000': 'shtime', '73000': 'strpce'}
badlink = {pc: (link.get(pc), f'xk:municipality:{m}')
           for pc, m in exp.items()
           if link.get(pc) != f'xk:municipality:{m}'}
check('full-link-map-127', not badlink, str(badlink))
have = Counter(l['area_source_id'].split(':')[-1] for l in links)
counts = {'pristina': 16, 'podujeve': 4, 'kosovo-polje': 4, 'drenas': 2,
 'lipjan': 3, 'obiliq': 2, 'novo-brdo': 1, 'gracanica': 1, 'prizren': 12,
 'rahovec': 7, 'dragash': 5, 'suva-reka': 3, 'malisheva': 3, 'mamusha': 1,
 'peja': 8, 'istog': 3, 'klina': 2, 'mitrovica': 8, 'skenderaj': 3,
 'vushtrri': 1, 'zvecan': 1, 'leposavic': 1, 'zubin-potok': 1,
 'gjakova': 11, 'decan': 1, 'junik': 1, 'gjilan': 5, 'viti': 2,
 'klokot': 1, 'kamenica': 4, 'ferizaj': 6, 'kacanik': 1,
 'hani-i-elezit': 1, 'shtime': 1, 'strpce': 1}
for muni, n in counts.items():
    check(f'count-{muni}-{n}', have[muni] == n, str(have[muni]))
for muni in ['north-mitrovica', 'partes', 'ranilug']:
    check(f'codeless-{muni}', have[muni] == 0, str(have[muni]))
# Region-capital anchors (PK regional PDFs + postcodebase mirrors).
for pc, muni in [('10000', 'pristina'), ('20000', 'prizren'),
                 ('30000', 'peja'), ('40000', 'mitrovica'),
                 ('50000', 'gjakova'), ('60000', 'gjilan'),
                 ('70000', 'ferizaj')]:
    check(f'capital-{pc}-{muni}',
          link.get(pc) == f'xk:municipality:{muni}', str(link.get(pc)))
# Post-split remaps (office town now a municipality seat; PDF files it
# under the pre-split parent).
for pc, muni in [('10500', 'gracanica'), ('20540', 'mamusha'),
                 ('51050', 'junik'), ('61050', 'klokot'),
                 ('71510', 'hani-i-elezit'), ('40650', 'zubin-potok')]:
    check(f'remap-{pc}-{muni}',
          link.get(pc) == f'xk:municipality:{muni}', str(link.get(pc)))
# Filing-vs-location cases: 31030 Gorazhdevc sits on the 310xx Istog
# series but the office is in Peja (PDF files it under PEJE too).
check('filing-31030-peja', link.get('31030') == 'xk:municipality:peja')
# 60520 Zheger exists only in the official Gjilan regional PDF
# (directories are silent); place is in Gjilan municipality.
check('official-only-60520-gjilan',
      link.get('60520') == 'xk:municipality:gjilan')
# CORRECTED: 40700 Runike office is in Runik, Skenderaj (addressed
# sighting + en/sq wiki + OSM); PDF filing under Mitrovica is a
# postal-hierarchy artefact like 40650.
check('fix-40700-skenderaj',
      link.get('40700') == 'xk:municipality:skenderaj',
      str(link.get('40700')))
# Exclusions: 10020 QTP/TPC transit centre (non-geographic); Mapanet
# phantoms 20070/20500; Serbian-system 38220/38251/38267 never PK codes.
for pc in ['10020', '20070', '20500', '38220', '38251', '38267']:
    check(f'excluded-{pc}', pc not in link)
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

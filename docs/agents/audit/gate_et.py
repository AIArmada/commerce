import csv, sys
from collections import Counter
# Ethiopia gate. Pins the B14 fix pass: 4 renames (Borana->Borena,
# West Haraghe->West Hararghe, East Welega GIMBIE->East Welega,
# Mekele->Mekelle), 3 drops (unlinked West Gojjam dup, wolkait +
# north-gojjam lowercase artifacts) and the 1000 Addis-primary flip.
# Tree 141 -> 138 rows (14 L1 + 124 L2); postal 50 -> 51 codes /
# 81 -> 83 legs / 22 -> 23 multis (2026-10-06 retry: +1230 Addis
# single, +1150 Sheger secondary).
# Oracles: WP List_of_zones_of_Ethiopia (counts/names), Oromia/South/
# Somali/Afar region article zone tables, citypopulation district-code
# prefix sets per region (cp), GeoNames ET ADM2 dump, UPU ethEn.pdf
# (1000 ADDIS ABABA), AgoraTube EthioPost cheat-sheet (weak postal
# corroboration for 34/50 codes), MDPI/Haramaya (East Bale).
# Asserts POST-fix state; run from repo root:
# python3 docs/agents/audit/gate_et.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/ethiopia-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/ethiopia-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/ethiopia-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
raw_a = open(A, 'rb').read()
raw_c = open(C, 'rb').read()
raw_l = open(L, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-trailing-LF', raw_a.endswith(b'\n'))
check('codes-LF-only', b'\r' not in raw_c)
check('codes-trailing-LF', raw_c.endswith(b'\n'))
check('links-LF-only', b'\r' not in raw_l)
check('links-trailing-LF', raw_l.endswith(b'\n'))
try:
    rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
    codes = list(csv.DictReader(open(C, newline='', encoding='utf-8')))
    links = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
except FileNotFoundError as e:
    check('files-present', False, str(e))
    print(f'{len(fails)} FAILURES')
    sys.exit(1)
byid = {r['source_id']: r for r in rows}
# --- tree: 14 L1 + 124 L2 ---
check('areas-138', len(rows) == 138, str(len(rows)))
check('l1-14', sum(1 for r in rows if r['level'] == '1') == 14)
check('l2-124', sum(1 for r in rows if r['level'] == '2') == 124)
per_l1 = {'et:city:addis-ababa': 11, 'et:region:afar': 7,
          'et:region:amhara': 13, 'et:region:benishangul-gumuz': 3,
          'et:region:central-ethiopia': 10, 'et:city:dire-dawa': 0,
          'et:region:gambela': 3, 'et:region:harari': 9,
          'et:region:oromia': 22, 'et:region:sidama': 4,
          'et:region:somali': 17, 'et:region:south-ethiopia': 12,
          'et:region:southwest-ethiopia': 6, 'et:region:tigray': 7}
got = Counter(r['parent_source_id'] for r in rows if r['level'] == '2')
for sid, n in per_l1.items():
    check(f"l2-{sid.split(':')[-1]}", got.get(sid, 0) == n,
          f'{got.get(sid, 0)} != {n}')
# --- 4 renames (post-fix id -> name) ---
renames = {'et:zone:borana': ('Borena', 'et:region:oromia'),
           'et:zone:east-welega-gimbie': ('East Welega', 'et:region:oromia'),
           'et:zone:west-haraghe': ('West Hararghe', 'et:region:oromia'),
           'et:zone:mekele': ('Mekelle', 'et:region:tigray')}
for sid, (name, parent) in renames.items():
    r = byid.get(sid)
    check(f'rename-{sid.split(":")[-1]}',
          bool(r) and r['name'] == name and r['parent_source_id'] == parent
          and r['level'] == '2', str(r))
# --- 3 drops absent; single West Gojjam keeps the legs ---
for sid in ('et:zone:amhara:west-gojjam',
            'et:zone:wolkait-tegede-stit-humera-zone',
            'et:zone:north-gojjam-zone'):
    check(f'drop-{sid.split(":")[-1]}', sid not in byid)
check('west-gojjam-single',
      sum(1 for r in rows if r['name'] == 'West Gojjam') == 1)
check('west-gojjam-id', 'et:zone:west-gojjam' in byid)
check('no-lowercase-artifacts',
      not any(r['name'] in ('north gojjam zone',
                            'wolkait tegede stit humera zone') for r in rows))
# --- contested keeps ---
keeps = {'et:zone:mahi-rasu': ('Mahi Rasu', 'et:region:afar'),
         'et:zone:hari-rasu': ('Hari Rasu', 'et:region:afar'),
         'et:zone:argobba': ('Argobba', 'et:region:afar'),
         'et:zone:anywaa': ('Anywaa', 'et:region:gambela'),
         'et:zone:gardula': ('Gardula', 'et:region:south-ethiopia'),
         'et:zone:koore': ('Koore', 'et:region:south-ethiopia'),
         'et:zone:east-bale': ('East Bale', 'et:region:oromia'),
         'et:zone:east-borana': ('East Borana', 'et:region:oromia'),
         'et:zone:buno-bedele': ('Buno Bedele', 'et:region:oromia'),
         'et:zone:kelam-welega': ('Kelam Welega', 'et:region:oromia'),
         'et:zone:illubabor': ('Illubabor', 'et:region:oromia'),
         'et:zone:sheger-city': ('Sheger City', 'et:region:oromia'),
         'et:zone:borana': ('Borena', 'et:region:oromia'),
         'et:zone:bahir-dar': ('Bahir Dar', 'et:region:amhara'),
         'et:zone:jigjiga-special': ('Jigjiga Special', 'et:region:somali'),
         'et:zone:tog-wajale-special': ('Tog Wajale Special',
                                        'et:region:somali'),
         'et:zone:degehabur-special': ('Degehabur Special',
                                       'et:region:somali'),
         'et:zone:gode-special': ('Gode Special', 'et:region:somali'),
         'et:zone:kebri-beyah-special': ('Kebri Beyah Special',
                                         'et:region:somali'),
         'et:zone:kebri-dahar-special': ('Kebri Dahar Special',
                                         'et:region:somali')}
for sid, (name, parent) in keeps.items():
    r = byid.get(sid)
    check(f"keep-{sid.split(':')[-1]}",
          bool(r) and r['name'] == name and r['parent_source_id'] == parent,
          str(r))
# --- postal: 51 codes / 83 legs / 23 multis (2026-10-06: +1230 Addis
# single, +1150 Sheger secondary) ---
check('codes-51', len(codes) == 51, str(len(codes)))
check('legs-83', len(links) == 83, str(len(links)))
bycode = {}
for r in links:
    bycode.setdefault(r['postcode'], []).append(r)
multis = sorted(k for k, v in bycode.items() if len(v) > 1)
check('multis-23', len(multis) == 23, ','.join(multis))
for code, legs in bycode.items():
    prim = [r for r in legs if r['is_primary'] == 'true']
    check(f'primary-{code}', len(prim) == 1, str(len(prim)))
# 1000 Addis-primary flip (UPU ethEn + cheat-sheet).
legs1000 = {r['area_source_id']: r['is_primary']
            for r in bycode.get('1000', [])}
check('1000-addis-primary',
      legs1000.get('et:city:addis-ababa') == 'true'
      and legs1000.get('et:zone:oromia:north-shewa') == 'false',
      str(legs1000))
# 2026-10-06: 1230 Akaki Beseka single + 1150 Sheger secondary.
check('1230-addis-single', bycode.get('1230', []) != []
      and len(bycode['1230']) == 1
      and bycode['1230'][0]['area_source_id'] == 'et:city:addis-ababa'
      and bycode['1230'][0]['is_primary'] == 'true', str(bycode.get('1230')))
legs1150 = {r['area_source_id']: r['is_primary'] for r in bycode.get('1150', [])}
check('1150-sheger-secondary',
      legs1150.get('et:city:addis-ababa') == 'true'
      and legs1150.get('et:zone:sheger-city') == 'false', str(legs1150))
# Sealed single-leg anchors.
anchors = {'3000': 'et:city:dire-dawa', '3020': 'et:city:dire-dawa',
           '3040': 'et:zone:shabelle', '3060': 'et:zone:korahe',
           '3120': 'et:zone:dollo', '3200': 'et:region:harari',
           '3220': 'et:zone:east-hararghe', '6040': 'et:zone:agew-awi',
           '6060': 'et:zone:metekel', '4540': 'et:zone:bale',
           '4560': 'et:zone:west-arsi', '5140': 'et:zone:bench-sheko'}
for code, sid in anchors.items():
    legs = bycode.get(code, [])
    check(f'anchor-{code}',
          len(legs) == 1 and legs[0]['area_source_id'] == sid
          and legs[0]['is_primary'] == 'true', str(legs))
# Corroborated multi primaries (woreda-containment + cheat localities).
primaries = {'2040': 'et:zone:arsi', '2120': 'et:zone:west-arsi',
             '3260': 'et:zone:jarar', '4400': 'et:zone:gamo',
             '4420': 'et:zone:south-omo', '4520': 'et:zone:bale',
             '4580': 'et:zone:east-bale', '4600': 'et:zone:gamo',
             '4620': 'et:zone:gofa', '5040': 'et:zone:keffa',
             '5060': 'et:zone:bench-sheko', '5220': 'et:zone:asosa',
             '5440': 'et:zone:anywaa', '6000': 'et:zone:west-gojjam',
             '6200': 'et:zone:central-gondar',
             '6260': 'et:zone:central-gondar',
             '7000': 'et:zone:south-east-tigray',
             '7220': 'et:zone:kilbet-rasu', '7240': 'et:zone:awsi-rasu'}
for code, sid in primaries.items():
    legs = bycode.get(code, [])
    prim = [r for r in legs if r['is_primary'] == 'true']
    check(f'multi-primary-{code}',
          len(prim) == 1 and prim[0]['area_source_id'] == sid, str(legs))
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

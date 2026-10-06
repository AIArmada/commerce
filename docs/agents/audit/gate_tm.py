import csv, re, sys
from collections import Counter
# Turkmenistan gate. M3 revisit: 2-cell fix (wiki-bold markup stripped
# from the Mary + Türkmenbaşy city names); links verified via a fresh
# 267-row Mapanet re-pull (49-code set exact) + full Nominatim
# re-attribution, all primaries adjudicated keeps. Run from repo root:
# python3 docs/agents/audit/gate_tm.py
A = './packages/addressing/resources/geography/turkmenistan-address-areas.csv'
C = './packages/addressing/resources/geography/turkmenistan-postal-codes.csv'
L = './packages/addressing/resources/geography/turkmenistan-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
byid = {r['source_id']: r for r in rows}
# --- tree: 5 regions + Ashgabat + 58 districts ---
check('areas-64', len(rows) == 64, str(len(rows)))
check('regions-5', sum(1 for r in rows if r['type'] == 'region') == 5)
check('cities-1', sum(1 for r in rows if r['type'] == 'city') == 1)
check('districts-58', sum(1 for r in rows if r['type'] == 'district') == 58)
iso = {'tm:region:ahal': ('Ahal', 'A'), 'tm:city:ashgabat': ('Ashgabat', 'S'),
 'tm:region:balkan': ('Balkan', 'B'), 'tm:region:dasoguz': ('Daşoguz', 'D'),
 'tm:region:lebap': ('Lebap', 'L'), 'tm:region:mary': ('Mary', 'M')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1', str(r))
table = {'tm:region:ahal': ['Ak bugdaý', 'Altyn asyr', 'Arkadag',
    'Babadaýhan', 'Bäherden', 'Gorjaw', 'Gökdepe', 'Kaka', 'Kärizek',
    'Sarahs', 'Tejen'],
 'tm:city:ashgabat': ['Bagtyýarlyk', 'Berkararlyk', 'Büzmeýin',
    'Köpetdag'],
 'tm:region:balkan': ['Awaza', 'Balkanabat', 'Bereket', 'Esenguly',
    'Etrek', 'Gyzylarbat', 'Magtymguly', 'Türkmenbaşy', 'Türkmenbaşy'],
 'tm:region:dasoguz': ['Akdepe', 'Boldumsaz', 'Daşoguz', 'Garaşsyzlyk',
    'Gubadag', 'Görogly', 'Köneürgenç', 'Ruhubelent',
    'Saparmyrat Türkmenbaşy', 'Şabat'],
 'tm:region:lebap': ['Darganata', 'Dänew', 'Döwletli', 'Farap',
    'Garabekewül', 'Halaç', 'Hojambaz', 'Kerki', 'Köýtendag', 'Saýat',
    'Türkmenabat', 'Çärjew'],
 'tm:region:mary': ['Baýramaly', 'Baýramaly', 'Garagum', 'Mary',
    'Mary', 'Murgap', 'Oguzhan', 'Sakarçäge', 'Tagtabazar',
    'Türkmengala', 'Wekilbazar', 'Ýolöten']}
ok = True
for par, names in table.items():
    have = sorted(r['name'] for r in rows if r['parent_source_id'] == par)
    if have != sorted(names):
        print('FAIL parent', par, have); fails.append(f'parent {par}'); ok = False
if ok: print('PASS all 6 parent mappings (58 districts)')
# M3 fix: no wiki-bold markup in names (city rows display plain, same
# as the Baýramaly pair already did).
marked = [r['source_id'] for r in rows if "'''" in (r['name'] or '')]
check('no-bold-markup', not marked, str(marked[:3]))
check('mary-city-plain', byid['tm:district:mary']['name'] == 'Mary')
check('turkmenbasy-city-plain',
      byid['tm:district:turkmenbasy']['name'] == 'Türkmenbaşy')
# Awaza is a real 2013 borough of Türkmenbaşy city, kept at L2 under
# Balkan by the cities-at-L2 flattening (no etrap codes attached).
check('awaza-kept', byid.get('tm:district:awaza', {}).get('parent_source_id')
      == 'tm:region:balkan')
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# --- postal: 49 codes / 73 links ---
check('codes-49', len(codes) == 49, str(len(codes)))
check('links-73', len(links) == 73, str(len(links)))
bad = [c['code'] for c in codes if not re.match(r'^\d{6}$', c['code'])]
check('code-format-6n', not bad, str(bad[:3]))
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', len(prim) == 49 and not multi,
      f'primaries={len(prim)} multi={multi[:3]}')
counts = Counter(l['postcode'] for l in links)
multis = sorted(pc for pc, c in counts.items() if c > 1)
check('multis-20', len(multis) == 20, str(multis))
quads = sorted(pc for pc, c in counts.items() if c == 4)
triples = sorted(pc for pc, c in counts.items() if c == 3)
check('quad-744000', quads == ['744000'], str(quads))
check('triples-2', triples == ['745420', '746632'], str(triples))
# --- full multi map (M3-adjudicated keeps) ---
exp = {'744000': [('tm:district:berkararlyk', 'true'),
    ('tm:district:bagtyyarlyk', 'false'), ('tm:district:buzmeyin', 'false'),
    ('tm:district:kopetdag', 'false')],
 '745000': [('tm:district:turkmenbasy', 'true'),
    ('tm:district:balkan:turkmenbasy', 'false')],
 '745100': [('tm:district:balkanabat', 'true'),
    ('tm:district:balkan:turkmenbasy', 'false')],
 '745130': [('tm:district:bereket', 'true'),
    ('tm:district:balkan:turkmenbasy', 'false')],
 '745150': [('tm:district:gyzylarbat', 'true'),
    ('tm:district:bereket', 'false')],
 '745200': [('tm:district:gokdepe', 'true'),
    ('tm:district:ak-bugday', 'false')],
 '745205': [('tm:district:ak-bugday', 'true'),
    ('tm:district:kaka', 'false')],
 '745220': [('tm:district:buzmeyin', 'true'),
    ('tm:district:arkadag', 'false')],
 '745240': [('tm:district:esenguly', 'true'),
    ('tm:district:etrek', 'false')],
 '745360': [('tm:district:tejen', 'true'),
    ('tm:district:altyn-asyr', 'false')],
 '745400': [('tm:district:murgap', 'true'),
    ('tm:district:wekilbazar', 'false')],
 '745405': [('tm:district:garagum', 'true'),
    ('tm:district:mary', 'false')],
 '745420': [('tm:district:sakarcage', 'true'),
    ('tm:district:oguzhan', 'false'), ('tm:district:mary:mary', 'false')],
 '745430': [('tm:district:turkmengala', 'true'),
    ('tm:district:mary:bayramaly', 'false')],
 '746000': [('tm:district:bayramaly', 'true'),
    ('tm:district:mary:bayramaly', 'false')],
 '746222': [('tm:district:danew', 'true'),
    ('tm:district:farap', 'false')],
 '746300': [('tm:district:dasoguz', 'true'),
    ('tm:district:boldumsaz', 'false')],
 '746400': [('tm:district:yoloten', 'true'),
    ('tm:district:tagtabazar', 'false')],
 '746613': [('tm:district:kerki', 'true'),
    ('tm:district:dowletli', 'false')],
 '746632': [('tm:district:halac', 'true'),
    ('tm:district:hojambaz', 'false'), ('tm:district:sayat', 'false')]}
for pc, legs in exp.items():
    got = sorted((l['area_source_id'], l['is_primary']) for l in links
                 if l['postcode'] == pc)
    check(f'multi-{pc}', got == sorted(legs), str(got))
# --- per-district primary counts ---
expect = {'tm:district:ak-bugday': 1, 'tm:district:akdepe': 2,
 'tm:district:babadayhan': 1, 'tm:district:baherden': 1,
 'tm:district:balkanabat': 2, 'tm:district:bayramaly': 1,
 'tm:district:bereket': 1, 'tm:district:berkararlyk': 1,
 'tm:district:buzmeyin': 1, 'tm:district:carjew': 1,
 'tm:district:danew': 2, 'tm:district:darganata': 2,
 'tm:district:dasoguz': 1, 'tm:district:esenguly': 1,
 'tm:district:farap': 1, 'tm:district:garabekewul': 1,
 'tm:district:garagum': 1, 'tm:district:gokdepe': 2,
 'tm:district:gorogly': 1, 'tm:district:gyzylarbat': 1,
 'tm:district:halac': 1, 'tm:district:hojambaz': 1,
 'tm:district:kaka': 1, 'tm:district:kerki': 2,
 'tm:district:koneurgenc': 2, 'tm:district:koytendag': 2,
 'tm:district:magtymguly': 1, 'tm:district:murgap': 2,
 'tm:district:sakarcage': 1, 'tm:district:saparmyrat-turkmenbasy': 1,
 'tm:district:sarahs': 1, 'tm:district:sayat': 1,
 'tm:district:tagtabazar': 2, 'tm:district:tejen': 1,
 'tm:district:turkmenabat': 1, 'tm:district:turkmenbasy': 1,
 'tm:district:turkmengala': 1, 'tm:district:yoloten': 2}
have = Counter(l['area_source_id'] for l in links
               if l['is_primary'] == 'true')
for sid, n in expect.items():
    check(f'count-{sid.split(":")[-1]}-{n}', have[sid] == n,
          str(have[sid]))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

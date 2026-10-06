import csv, sys
from collections import Counter
# Mauritius gate. Pins the B14 VERIFY-ONLY pass (zero content fixes):
# 9 districts + 3 dependencies with ISO codes, 142 L2 (1 city + 4
# towns + 137 villages) matching the WP places table with zero diffs
# (modulo Rivière Noire/Black River + display-text normalization) and
# the Agaléga article for the 3 Agalega villages; postal 1990 codes /
# 1990 legs / 0 multis (1804 numeric + 182 Rodrigues R + 4 Agalega A).
# Oracles: WP Districts/places of Mauritius tables, Agaléga article,
# ISO 3166-2:MU, UPU musEn.pdf (11213/42602 anchors), Mauritius Post
# finder locality vocabulary (146 options ≈ bundle + town quarters).
# No bulk second oracle exists for the 1,990 codes (MP finder is
# JS-walled; no GeoNames MU postal export; directories paywalled),
# so per-code transcription is MP-scrape-authoritative + structural.
# Asserts POST-fix state; run from repo root:
# python3 docs/agents/audit/gate_mu.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/mauritius-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/mauritius-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/mauritius-postal-code-areas.csv'
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
byname = {r['name']: r for r in rows}
# --- tree: 12 L1 + 142 L2 ---
check('areas-154', len(rows) == 154, str(len(rows)))
check('l1-12', sum(1 for r in rows if r['level'] == '1') == 12)
check('l2-142', sum(1 for r in rows if r['level'] == '2') == 142)
iso = {'mu:district:black-river': ('Black River', 'BL'),
       'mu:district:flacq': ('Flacq', 'FL'),
       'mu:district:grand-port': ('Grand Port', 'GP'),
       'mu:district:moka': ('Moka', 'MO'),
       'mu:district:pamplemousses': ('Pamplemousses', 'PA'),
       'mu:district:plaines-wilhems': ('Plaines Wilhems', 'PW'),
       'mu:district:port-louis': ('Port Louis', 'PL'),
       'mu:district:riviere-du-rempart': ('Rivière du Rempart', 'RR'),
       'mu:district:savanne': ('Savanne', 'SA'),
       'mu:dependency:agalega-islands': ('Agalega Islands', 'AG'),
       'mu:dependency:rodrigues-island': ('Rodrigues Island', 'RO'),
       'mu:dependency:saint-brandon-islands':
       ('Saint Brandon Islands', 'CC')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'l1-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1' and r['parent_source_id'] == '', str(r))
per_l1 = {'mu:district:port-louis': 2, 'mu:district:plaines-wilhems': 6,
          'mu:district:black-river': 13,
          'mu:district:riviere-du-rempart': 20,
          'mu:district:pamplemousses': 20, 'mu:district:savanne': 14,
          'mu:district:grand-port': 25, 'mu:district:flacq': 25,
          'mu:district:moka': 14,
          'mu:dependency:agalega-islands': 3,
          'mu:dependency:rodrigues-island': 0,
          'mu:dependency:saint-brandon-islands': 0}
got = Counter(r['parent_source_id'] for r in rows if r['level'] == '2')
for sid, n in per_l1.items():
    check(f"l2-{sid.split(':')[-1]}", got.get(sid, 0) == n,
          f'{got.get(sid, 0)} != {n}')
# Urban grain: 1 city + 4 towns; Agalega trio.
check('urban-5', sum(1 for r in rows if r['level'] == '2'
                     and r['type'] in ('city', 'town')) == 5)
for name, typ, parent in (
        ('Port Louis', 'city', 'Port Louis'),
        ('Beau Bassin-Rose Hill', 'town', 'Plaines Wilhems'),
        ('Curepipe', 'town', 'Plaines Wilhems'),
        ('Quatre Bornes', 'town', 'Plaines Wilhems'),
        ('Vacoas-Phoenix', 'town', 'Plaines Wilhems'),
        ('Vingt-Cinq', 'village', 'Agalega Islands'),
        ('La Fourche', 'village', 'Agalega Islands'),
        ('Sainte Rita', 'village', 'Agalega Islands')):
    r = byname.get(name)
    check(f"place-{name.split(',')[0].split(' ')[0]}",
          bool(r) and r['type'] == typ and r['level'] == '2'
          and byid[r['parent_source_id']]['name'] == parent, str(r))
# --- postal: 1990 codes / 1990 legs / 0 multis ---
check('codes-1990', len(codes) == 1990, str(len(codes)))
check('legs-1990', len(links) == 1990, str(len(links)))
bycode = {}
for r in links:
    bycode.setdefault(r['postcode'], []).append(r)
multis = [k for k, v in bycode.items() if len(v) > 1]
check('multis-0', not multis, str(multis[:5]))
check('all-primary',
      all(r['is_primary'] == 'true' for r in links))
cc = [r['code'] for r in codes]
check('numeric-1804', sum(1 for c in cc if c[0].isdigit()) == 1804)
check('r-182', sum(1 for c in cc if c.startswith('R')) == 182)
check('a-4', sum(1 for c in cc if c.startswith('A')) == 4)
# R-codes at the Rodrigues dependency; A-codes split 3 villages + 1 dep.
rlegs = {r['area_source_id'] for r in links if r['postcode'].startswith('R')}
check('r-at-rodrigues', rlegs == {'mu:dependency:rodrigues-island'},
      str(rlegs))
alegs = sorted((r['postcode'], byid[r['area_source_id']]['name'])
               for r in links if r['postcode'].startswith('A'))
check('a-codes', alegs == [('A1101', 'La Fourche'),
                           ('A1102', 'Agalega Islands'),
                           ('A1103', 'Vingt-Cinq'),
                           ('A2101', 'Sainte Rita')], str(alegs))
check('st-brandon-codeless',
      not any(byid[r['area_source_id']]['name']
              == 'Saint Brandon Islands' for r in links))
# UPU anchors.
for code, name in (('11213', 'Port Louis'), ('42602', 'Lalmatie')):
    legs = bycode.get(code, [])
    check(f'anchor-{code}',
          len(legs) == 1
          and byid[legs[0]['area_source_id']]['name'] == name, str(legs))
# Cross-block villages: whole-village postal-district spillover (58).
home = {'1': 'Port Louis', '2': 'Pamplemousses',
        '3': 'Rivière du Rempart', '4': 'Flacq', '5': 'Grand Port',
        '6': 'Savanne', '7': 'Plaines Wilhems', '8': 'Moka',
        '9': 'Black River'}
def dist(r):
    a = byid[r['area_source_id']]
    return a['name'] if a['level'] == '1' else byid[a['parent_source_id']]['name']
x = Counter()
for r in links:
    if r['postcode'][0] in home and dist(r) != home[r['postcode'][0]]:
        x[byid[r['area_source_id']]['name']] += 1
check('crossblock-58', sum(x.values()) == 58, str(sum(x.values())))
check('crossblock-villages', dict(x) == {
    'Belle Vue Haurel': 8, 'Plaine des Roches': 1, 'Midlands': 4,
    'Seizième Mille': 9, 'Quatre Soeurs': 5, 'Rivière du Poste': 4,
    "L'Escalier": 17, 'La Flora': 8, "Saint Julien d'Hotman": 1,
    'Ripailles': 1}, str(dict(x)))
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

import csv, re, sys
from collections import Counter
# Chile gate. Pins the B10 fix-and-fill pass: 72 areas (16 regions ISO
# 3166-2:CL + 56 provinces incl. post-2018 Nuble split) + 346 commune-base
# codes / 346 single-primary links, plus the 1-cell B10 fix (Cautin -> Cautín).
# Oracles: GeoNames CL.zip (346 rows, code set 1:1), en/es WP
# Provinces + Regions of Chile, ISO 3166-2:CL, en/es WP Nuble split tables
# (21 communes), es Anexo:Cdgigos postales (344/345 commune rows match),
# UPU CHL profile (7-digit, 3-digit commune sector). Run from repo root:
# python3 docs/agents/audit/gate_cl.py
A = './packages/addressing/resources/geography/chile-address-areas.csv'
C = './packages/addressing/resources/geography/chile-postal-codes.csv'
L = './packages/addressing/resources/geography/chile-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
try:
    rows = list(csv.DictReader(open(A)))
    codes = list(csv.DictReader(open(C)))
    links = list(csv.DictReader(open(L)))
except FileNotFoundError as e:
    check('files-present', False, str(e))
    print(f'{len(fails)} FAILURES')
    sys.exit(1)
byid = {r['source_id']: r for r in rows}
# --- tree: 16 regions + 56 provinces ---
check('areas-72', len(rows) == 72, str(len(rows)))
check('regions-16', sum(1 for r in rows if r['type'] == 'region') == 16)
check('provinces-56', sum(1 for r in rows if r['type'] == 'province') == 56)
# ISO 3166-2:CL codes + names. CL-AI pinned byte-exact per ISO (unaccented
# "Ibanez"); CL-MA keeps the official long name (ISO short "Magallanes").
iso = {'cl:region:aisen-del-general-carlos-ibanez-del-campo': ('Aisén del General Carlos Ibañez del Campo', 'AI'),
 'cl:region:antofagasta': ('Antofagasta', 'AN'),
 'cl:region:arica-y-parinacota': ('Arica y Parinacota', 'AP'),
 'cl:region:atacama': ('Atacama', 'AT'),
 'cl:region:biobio': ('Biobío', 'BI'),
 'cl:region:coquimbo': ('Coquimbo', 'CO'),
 'cl:region:la-araucania': ('La Araucanía', 'AR'),
 'cl:region:libertador-general-bernardo-ohiggins': ("Libertador General Bernardo O'Higgins", 'LI'),
 'cl:region:los-lagos': ('Los Lagos', 'LL'),
 'cl:region:los-rios': ('Los Ríos', 'LR'),
 'cl:region:magallanes-y-de-la-antartica-chilena': ('Magallanes y de la Antártica Chilena', 'MA'),
 'cl:region:maule': ('Maule', 'ML'),
 'cl:region:nuble': ('Ñuble', 'NB'),
 'cl:region:region-metropolitana-de-santiago': ('Región Metropolitana de Santiago', 'RM'),
 'cl:region:tarapaca': ('Tarapacá', 'TA'),
 'cl:region:valparaiso': ('Valparaíso', 'VS')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1' and r['parent_source_id'] == '', str(r))
# Full region -> provinces table per Provinces-of-Chile oracle.
table = {'cl:region:arica-y-parinacota': ['Arica', 'Parinacota'],
 'cl:region:tarapaca': ['Iquique', 'Tamarugal'],
 'cl:region:antofagasta': ['Antofagasta', 'El Loa', 'Tocopilla'],
 'cl:region:atacama': ['Copiapó', 'Huasco', 'Chañaral'],
 'cl:region:coquimbo': ['Elqui', 'Limarí', 'Choapa'],
 'cl:region:valparaiso': ['Isla de Pascua', 'Los Andes', 'Marga Marga',
    'Petorca', 'Quillota', 'San Antonio', 'San Felipe de Aconcagua',
    'Valparaíso'],
 'cl:region:region-metropolitana-de-santiago': ['Santiago', 'Cordillera',
    'Maipo', 'Talagante', 'Melipilla', 'Chacabuco'],
 'cl:region:libertador-general-bernardo-ohiggins': ['Cachapoal',
    'Colchagua', 'Cardenal Caro'],
 'cl:region:maule': ['Talca', 'Linares', 'Curicó', 'Cauquenes'],
 'cl:region:nuble': ['Diguillín', 'Punilla', 'Itata'],
 'cl:region:biobio': ['Concepción', 'Biobío', 'Arauco'],
 'cl:region:la-araucania': ['Cautín', 'Malleco'],
 'cl:region:los-rios': ['Valdivia', 'El Ranco'],
 'cl:region:los-lagos': ['Llanquihue', 'Osorno', 'Chiloé', 'Palena'],
 'cl:region:aisen-del-general-carlos-ibanez-del-campo': ['Coyhaique',
    'Aysén', 'General Carrera', 'Capitán Prat'],
 'cl:region:magallanes-y-de-la-antartica-chilena': ['Magallanes',
    'Última Esperanza', 'Tierra del Fuego', 'Antártica Chilena']}
ok = True
for reg, names in table.items():
    have = sorted(r['name'] for r in rows if r['parent_source_id'] == reg)
    if have != sorted(names):
        print('FAIL region', reg, have); fails.append(f'region {reg}'); ok = False
if ok: print('PASS all 16 region mappings (56 provinces)')
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
check('l1-no-parents', all(r['parent_source_id'] == '' for r in rows if r['level'] == '1'))
check('l2-all-parented', all(r['parent_source_id'] != '' for r in rows if r['level'] == '2'))
badslug = [r['source_id'] for r in rows if not re.match(r'^cl:(region|province):[a-z0-9-]+$', r['source_id'])]
check('slug-shape', not badslug, str(badslug[:3]))
# B10 fix cell: Cautín carries the accent (GeoNames + en/es WP + official).
check('name-cautin-accent', byid.get('cl:province:cautin', {}).get('name') == 'Cautín',
      str(byid.get('cl:province:cautin')))
# --- postal counts: 346 codes / 346 links (codes/links CRLF) ---
check('codes-346', len(codes) == 346, str(len(codes)))
check('links-346', len(links) == 346, str(len(links)))
check('country-CL', all(c['country_code'] == 'CL' for c in codes))
bad = [c['code'] for c in codes if not re.match(r'^\d{7}$', c['code'])]
check('code-format-7digit', not bad, str(bad[:3]))
check('codes-unique', len({c['code'] for c in codes}) == 346)
check('codes-sorted', [c['code'] for c in codes] == sorted(c['code'] for c in codes))
lkeys = [(l['postcode'], l['area_source_id']) for l in links]
check('links-sorted', lkeys == sorted(lkeys))
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
badrel = [l for l in links if l['relationship_type'] != 'served_by' or l['is_primary'] not in ('true', 'false')]
check('link-shape', not badrel, str(badrel[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', len(prim) == 346 and not multi,
      f'primaries={len(prim)} multi={multi[:3]}')
check('all-links-primary', all(l['is_primary'] == 'true' for l in links))
check('every-code-linked', {c['code'] for c in codes} == {l['postcode'] for l in links})
counts = Counter(l['postcode'] for l in links)
check('no-duals', all(c == 1 for c in counts.values()))
check('provinces-all-linked', {l['area_source_id'] for l in links} == {r['source_id'] for r in rows if r['type'] == 'province'})
# --- per-province code counts (= commune counts; WP agrees save Petorca) ---
expect = {'cl:province:arica': 2, 'cl:province:parinacota': 2,
 'cl:province:iquique': 2, 'cl:province:tamarugal': 5,
 'cl:province:antofagasta': 4, 'cl:province:el-loa': 3,
 'cl:province:tocopilla': 2, 'cl:province:copiapo': 3,
 'cl:province:huasco': 4, 'cl:province:chanaral': 2,
 'cl:province:elqui': 6, 'cl:province:limari': 5, 'cl:province:choapa': 4,
 'cl:province:isla-de-pascua': 1, 'cl:province:los-andes': 4,
 'cl:province:marga-marga': 4, 'cl:province:petorca': 5,
 'cl:province:quillota': 5, 'cl:province:san-antonio': 6,
 'cl:province:san-felipe-de-aconcagua': 6, 'cl:province:valparaiso': 7,
 'cl:province:santiago': 32, 'cl:province:cordillera': 3,
 'cl:province:maipo': 4, 'cl:province:talagante': 5,
 'cl:province:melipilla': 5, 'cl:province:chacabuco': 3,
 'cl:province:cachapoal': 17, 'cl:province:colchagua': 10,
 'cl:province:cardenal-caro': 6, 'cl:province:talca': 10,
 'cl:province:linares': 8, 'cl:province:curico': 9,
 'cl:province:cauquenes': 3, 'cl:province:diguillin': 9,
 'cl:province:punilla': 5, 'cl:province:itata': 7,
 'cl:province:concepcion': 12, 'cl:province:biobio': 14,
 'cl:province:arauco': 7, 'cl:province:cautin': 21,
 'cl:province:malleco': 11, 'cl:province:valdivia': 8,
 'cl:province:el-ranco': 4, 'cl:province:llanquihue': 9,
 'cl:province:osorno': 7, 'cl:province:chiloe': 10,
 'cl:province:palena': 4, 'cl:province:coyhaique': 2,
 'cl:province:aysen': 3, 'cl:province:general-carrera': 2,
 'cl:province:capitan-prat': 3, 'cl:province:magallanes': 4,
 'cl:province:ultima-esperanza': 2, 'cl:province:tierra-del-fuego': 3,
 'cl:province:antartica-chilena': 2}
have = Counter(l['area_source_id'] for l in links)
for sid, n in expect.items():
    check(f'count-{sid.split(":")[-1]}-{n}', have[sid] == n, str(have[sid]))
check('count-sum-346', sum(have.values()) == 346, str(sum(have.values())))
# --- first-digit prefix counts (WP Postal-codes-in-Chile ranges) ---
px1 = {'1': 44, '2': 50, '3': 68, '4': 63, '5': 49, '6': 20, '7': 9,
 '8': 19, '9': 24}
have1 = Counter(c['code'][0] for c in codes)
for px, n in px1.items():
    check(f'prefix-{px}xx-{n}', have1[px] == n, str(have1[px]))
check('prefix-sum-346', sum(have1.values()) == 346, str(sum(have1.values())))
# --- per-code pins: region anchors + full 21-code Nuble split ---
plink = {l['postcode']: l['area_source_id'] for l in links if l['is_primary'] == 'true'}
anchors = {
 '1000000': 'cl:province:arica', '1100000': 'cl:province:iquique',
 '1240000': 'cl:province:antofagasta', '1530000': 'cl:province:copiapo',
 '1700000': 'cl:province:elqui', '2340000': 'cl:province:valparaiso',
 '2770000': 'cl:province:isla-de-pascua',
 '6500000': 'cl:province:marga-marga',
 '2820000': 'cl:province:cachapoal', '3340000': 'cl:province:curico',
 '4030000': 'cl:province:concepcion', '4780000': 'cl:province:cautin',
 '5090000': 'cl:province:valdivia', '5290000': 'cl:province:osorno',
 '5950000': 'cl:province:coyhaique', '6000000': 'cl:province:aysen',
 '6200000': 'cl:province:magallanes',
 '6350000': 'cl:province:antartica-chilena',
 '7500000': 'cl:province:santiago', '9580000': 'cl:province:melipilla',
 # Nuble split (Law 21.033; en+es WP agree 21/21)
 '3780000': 'cl:province:diguillin', '3820000': 'cl:province:diguillin',
 '3880000': 'cl:province:diguillin', '3890000': 'cl:province:diguillin',
 '3900000': 'cl:province:diguillin', '3910000': 'cl:province:diguillin',
 '3920000': 'cl:province:diguillin', '3930000': 'cl:province:diguillin',
 '3940000': 'cl:province:diguillin', '3840000': 'cl:province:punilla',
 '3850000': 'cl:province:punilla', '3860000': 'cl:province:punilla',
 '3870000': 'cl:province:punilla', '4020000': 'cl:province:punilla',
 '3950000': 'cl:province:itata', '3960000': 'cl:province:itata',
 '3970000': 'cl:province:itata', '3980000': 'cl:province:itata',
 '3990000': 'cl:province:itata', '4000000': 'cl:province:itata',
 '4010000': 'cl:province:itata',
 # es-annex gaps correctly included in bundled set
 '6170000': 'cl:province:ultima-esperanza',
 '9660000': 'cl:province:melipilla'}
for pc, want in anchors.items():
    check(f'anchor-{pc}', plink.get(pc) == want, str(plink.get(pc)))
# Labranza 4810000 is a Temuco locality, not a commune: correctly absent.
code_set = {c['code'] for c in codes}
link_set = {l['postcode'] for l in links}
check('no-4810000-labranza', '4810000' not in code_set and '4810000' not in link_set)
# --- EOL + trailing newlines (areas LF; codes/links CRLF) ---
rawA, rawC, rawL = open(A, 'rb').read(), open(C, 'rb').read(), open(L, 'rb').read()
check('areas-lf-only', rawA.count(b'\r') == 0 and rawA.count(b'\n') == 73,
      f"cr={rawA.count(bytes([13]))} lf={rawA.count(bytes([10]))}")
check('codes-crlf-347', rawC.count(b'\r\n') == 347 and rawC.count(b'\r') == 347 and rawC.count(b'\n') == 347,
      f"cr={rawC.count(bytes([13]))} lf={rawC.count(bytes([10]))}")
check('links-crlf-347', rawL.count(b'\r\n') == 347 and rawL.count(b'\r') == 347 and rawL.count(b'\n') == 347,
      f"cr={rawL.count(bytes([13]))} lf={rawL.count(bytes([10]))}")
check('areas-trailing-nl', rawA.endswith(b'\n') and not rawA.endswith(b'\r\n'))
check('codes-trailing-crlf', rawC.endswith(b'\r\n'))
check('links-trailing-crlf', rawL.endswith(b'\r\n'))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

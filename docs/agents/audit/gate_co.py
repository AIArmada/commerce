import csv, sys
from collections import Counter
# Colombia gate. Pins the B20 pass (1174 areas: 33 L1 + 1141 L2;
# 3681 codes / 3681 legs; areas LF, postal pure CRLF): L1 33/33
# exact vs ISO 3166-2:CO; L2 vs ES annex (1123) + api-colombia
# + HDX/OCHA COD-AB + 4-72 operator layers (1122). FIX iff
# >=2 lineages agree. Fixes: F1 +1 municipality San Jacinto
# del Cauca (Bolivar, DANE 13655 — the only missing L2);
# F2 +3 codes/legs (134060/67/68); F3 +2 (474001/474007 ->
# Pinto, was leg-less); F4 81 Bogota legs L1->20 localities
# (4-digit zones 1101-1120 = GN place; localities were
# leg-less); N01-N42 renames (source_ids unchanged).
# Display-name dupes after renames (La Paz x2, Providencia
# x2, San Andres x3, Ciudad Bolivar x2 cross-level) follow
# existing bundle convention (San Pedro x3 already):
# officially identical names, scoped by source_id+parent.
# HOLDS: H1 Alban 2v2 tie (needs DIVIPOLA); H2 88001 type
# ANM kept (needs DIVIPOLA clase); H3 Miriti hyphen format.
# Asserts verified state; run from repo root:
# python3 docs/agents/audit/gate_co.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
G = f'{ROOT}/packages/addressing/resources/geography'
A = f'{G}/colombia-address-areas.csv'
C = f'{G}/colombia-postal-codes.csv'
L = f'{G}/colombia-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
raw_a = open(A, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-lines-1175', raw_a.count(b'\n') == 1175, str(raw_a.count(b'\n')))
check('areas-trailing-LF', raw_a.endswith(b'\n'))
raw_c = open(C, 'rb').read()
check('codes-pure-CRLF', raw_c.count(b'\r\n') == raw_c.count(b'\n') == 3682, str(raw_c.count(b'\r\n')))
raw_l = open(L, 'rb').read()
check('legs-pure-CRLF', raw_l.count(b'\r\n') == raw_l.count(b'\n') == 3682, str(raw_l.count(b'\r\n')))
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
check('areas-1174', len(rows) == 1174, str(len(rows)))
check('source-ids-unique', len(byid) == len(rows))
check('L1-33', sum(1 for r in rows if r['level'] == '1') == 33)
check('L2-1141', sum(1 for r in rows if r['level'] == '2') == 1141)
check('parents-resolve', all(r['parent_source_id'] in byid for r in rows if r['level'] == '2'))
check('parents-are-L1', all(byid[r['parent_source_id']]['level'] == '1' for r in rows if r['level'] == '2'))
check('f1-san-jacinto', byid.get('co:municipality:san-jacinto-del-cauca', {}).get('parent_source_id') == 'co:department:bolivar')
for sid, nm in [('co:municipality:talaiga-nuevo', 'Talaigua Nuevo'),
                ('co:municipality:arroyo-hondo', 'Arroyohondo'),
                ('co:municipality:los-robles-la-paz', 'La Paz'),
                ('co:municipality:bolivar', 'Ciudad Bolívar'),
                ('co:municipality:san-andres', 'San Andrés de Cuerquía'),
                ('co:municipality:san-pedro', 'San Pedro de los Milagros'),
                ('co:municipality:providencia-and-santa-catalina-islands', 'Providencia'),
                ('co:non_municipalized_area:san-andres-islands', 'San Andrés'),
                ('co:municipality:pinto', 'Santa Bárbara de Pinto'),
                ('co:municipality:cartagena', 'Cartagena de Indias'),
                ('co:municipality:santander:el-carmen', 'El Carmen de Chucurí')]:
    check(f'n-{sid.split(":")[-1]}', byid.get(sid, {}).get('name') == nm)
check('n43-san-vicente-keep', byid.get('co:municipality:san-vicente', {}).get('name') == 'San Vicente')
check('h1-alban-keep', byid.get('co:municipality:alban', {}).get('name') == 'Albán')
codes = [r['code'] for r in csv.DictReader(open(C, newline='', encoding='utf-8'))]
legs = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
check('codes-3681', len(codes) == 3681, str(len(codes)))
check('codes-unique', len(set(codes)) == len(codes))
check('legs-3681', len(legs) == 3681, str(len(legs)))
check('legs-resolve', all(r['area_source_id'] in byid for r in legs))
prim = Counter(r['postcode'] for r in legs if r['is_primary'] == 'true')
check('every-code-linked', set(codes) == {r['postcode'] for r in legs})
check('one-primary-each', all(prim.get(c) == 1 for c in codes))
check('bogota-L1-legless', not any(r['area_source_id'] == 'co:capital_district:bogota-d-c' for r in legs))
bycode = {}
for r in legs:
    bycode.setdefault(r['postcode'], []).append((r['area_source_id'], r['is_primary']))
check('f2-134060', bycode['134060'] == [('co:municipality:san-jacinto-del-cauca', 'true')])
check('f3-474001', bycode['474001'] == [('co:municipality:pinto', 'true')])
check('f4-110111', bycode['110111'] == [('co:locality:usaquen', 'true')])
check('f4-110211', bycode['110211'] == [('co:locality:chapinero', 'true')])
check('f4-111111', bycode['111111'] == [('co:locality:suba', 'true')])
check('f4-112041', bycode['112041'] == [('co:locality:sumapaz', 'true')])
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

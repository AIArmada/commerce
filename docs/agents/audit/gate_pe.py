import csv, re, sys
from collections import Counter
# Peru gate. Pins the B16 inline pass (4-code Loreto rotation
# fix + 42 leg moves + 2 name fixes; 222 areas / 2669 codes /
# 2671 legs / 2 multis): 25 regions ISO 3166-2:PE exact + Lima
# metro municipality L1 + 196 provinces (es.wiki INEI-sourced
# annex: 196/196 names modulo 4 adjudicated pairs, 196/196
# parents, 192/192 codes + the 4 fixed). ROTATION: bundle
# held Putumayo=1605/Requena=1606/Ucayali=1607/Datem=1608;
# INEI truth (GN admin2 codes + es.wiki annex) is Requena
# 1605/Ucayali 1606/Datem 1607/Putumayo 1608 — codes fixed
# and all 42 affected legs remapped to GN-unanimous admin2.
# NAMES: Antonio Raymondi->Raimondi (es.wiki + en.wiki +
# gob.pe municipalidad title; slug + 7 legs) and Daniel
# Alcídes->Alcides Carrión (GN + en.wiki + es.wiki).
# KEEPS: Cusco over GN/es.wiki Cuzco (official modern +
# en.wiki), Huanca Sancos spaced (en.wiki), Vilcas Huamán
# accented (GN + en.wiki). Postal: bundle set == GN set
# exactly (2669/2669 zero diffs); GN itself spans >1 admin2
# on exactly the 2 bundled multis (14000 Chiclayo 72 rows >
# Lambayeque 62; 14013 Lambayeque 7 > Chiclayo 1).
# EOL: areas LF-only, postal files CRLF (pre-existing mix).
# Asserts POST-fix state; run from repo root:
# python3 docs/agents/audit/gate_pe.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/peru-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/peru-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/peru-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
raw_a = open(A, 'rb').read()
raw_c = open(C, 'rb').read()
raw_l = open(L, 'rb').read()
def is_crlf(raw):
    return b'\r\n' in raw and b'\r' not in raw.replace(b'\r\n', b'') and b'\n' not in raw.replace(b'\r\n', b'')
check('areas-LF-only', b'\r' not in raw_a)
check('areas-trailing-LF', raw_a.endswith(b'\n'))
check('codes-CRLF', is_crlf(raw_c))
check('codes-trailing-CRLF', raw_c.endswith(b'\r\n'))
check('links-CRLF', is_crlf(raw_l))
check('links-trailing-CRLF', raw_l.endswith(b'\r\n'))
try:
    rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
    codes = list(csv.DictReader(open(C, newline='', encoding='utf-8')))
    links = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
except FileNotFoundError as e:
    check('files-present', False, str(e))
    print(f'{len(fails)} FAILURES')
    sys.exit(1)
byid = {r['source_id']: r for r in rows}
check('areas-222', len(rows) == 222, str(len(rows)))
check('region-25', sum(1 for r in rows if r['type'] == 'region') == 25)
check('l1-municipality-1', sum(1 for r in rows if r['type'] == 'municipality' and r['level'] == '1') == 1)
check('province-196', sum(1 for r in rows if r['type'] == 'province') == 196)
check('l2-codes-unique', len({r['code'] for r in rows if r['level'] == '2'}) == 196)
spots = {'pe:region:loreto': ('Loreto', 'LOR'), 'pe:region:cusco': ('Cusco', 'CUS'),
         'pe:province:requena': ('Requena', '1605'), 'pe:province:ucayali': ('Ucayali', '1606'),
         'pe:province:datem-del-maranon': ('Datem del Marañón', '1607'),
         'pe:province:putumayo': ('Putumayo', '1608'),
         'pe:province:antonio-raimondi': ('Antonio Raimondi', '0203'),
         'pe:province:daniel-alcides-carrion': ('Daniel Alcides Carrión', '1902'),
         'pe:province:cusco': ('Cusco', '0801'),
         'pe:province:vilcas-huaman': ('Vilcas Huamán', '0511'),
         'pe:province:huanca-sancos': ('Huanca Sancos', '0503')}
for sid, (nm, cd) in spots.items():
    r = byid.get(sid, {})
    check(f'spot-{sid}', r.get('name') == nm and r.get('code') == cd,
          str((r.get('name'), r.get('code'))))
check('raymondi-gone', 'pe:province:antonio-raymondi' not in byid)
check('codes-2669', len(codes) == 2669, str(len(codes)))
check('links-2671', len(links) == 2671, str(len(links)))
check('codes-PE', all(c['country_code'] == 'PE' for c in codes))
clist = [c['code'] for c in codes]
check('codes-5digit', all(re.match(r'^\d{5}$', c) for c in clist))
check('all-served-by', all(l['relationship_type'] == 'served_by' for l in links))
have = Counter(l['postcode'] for l in links)
multis = sorted(c for c, v in have.items() if v > 1)
check('multis-2', multis == ['14000', '14013'], str(multis))
check('legs-resolve', all(l['area_source_id'] in byid for l in links))
prims = {}
for l in links:
    if l['is_primary'] == 'true':
        prims.setdefault(l['postcode'], []).append(l['area_source_id'])
check('one-primary-per-code', all(len(v) == 1 for v in prims.values()) and len(prims) == 2669)
def legs_of(pc):
    return sorted(l['area_source_id'] for l in links if l['postcode'] == pc)
check('14000-dual', legs_of('14000') == ['pe:province:chiclayo', 'pe:province:lambayeque'],
      str(legs_of('14000')))
check('14000-primary', prims.get('14000') == ['pe:province:chiclayo'])
check('14013-dual', legs_of('14013') == ['pe:province:chiclayo', 'pe:province:lambayeque'],
      str(legs_of('14013')))
check('14013-primary', prims.get('14013') == ['pe:province:lambayeque'])
keeps = {'16110': 'pe:province:putumayo', '16140': 'pe:province:putumayo',
         '16320': 'pe:province:requena', '16400': 'pe:province:requena',
         '16440': 'pe:province:ucayali', '16480': 'pe:province:ucayali',
         '16600': 'pe:province:datem-del-maranon', '16700': 'pe:province:datem-del-maranon',
         '08000': 'pe:province:cusco'}
for pc, sid in keeps.items():
    check(f'keep-{pc}', prims.get(pc) == [sid], str(prims.get(pc)))
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

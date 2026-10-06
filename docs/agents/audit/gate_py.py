import csv, re, sys
from collections import Counter, defaultdict
# Paraguay gate. Pins the B17 inline pass as VERIFY-ONLY (zero
# changes; 281 areas / 2887 codes / 2887 legs / 0 multis): 18 L1
# ISO 3166-2:PY exact (17 departments 1-16+19 + PY-ASU) + 263
# districts exact vs the es.wiki municipios annex (names +
# parents, 263/263). GN ADM2 (245) agrees modulo ~50
# abbreviation/short/digit variants + 15 newest districts absent
# (stale, documented). en.wiki Districts (161/262, 2002 census)
# + Departments column (261) + Esri 268 are stale/different
# universes — not used. Postal: 259 p4 prefixes all pure
# (1 district each) + 18 p2 prefixes all pure (DINACOPA dept
# 00-17: 00 Asunción ... 16 Boquerón, 17 Alto Paraguay);
# sectors gap-free (01..max per p4); youbianku transcription
# verified (5 detail breadcrumbs incl. Guayaibí/Bottrell/
# Ycuamandiyú bridges + 18/18 Guayaibí + 18/18 San Pedro
# capital + 4/4 Botrell detail-confirmed); 9 codeless districts
# confirmed code-empty on youbianku (Boquerón, Campo Aceval,
# Cerro Corá, Itacuá, Laurel, Nueva Asunción, Paso Horqueta,
# Puerto Adela, San José del Rosario); operator anchor 001013
# (DINACOPA HQ) bundled -> Asunción. No GN postal dump exists
# (404); Mapanet obsolete 4-digit stays excluded.
# EOL: areas LF-only; postal CRLF.
# Asserts verified state; run from repo root:
# python3 docs/agents/audit/gate_py.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/paraguay-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/paraguay-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/paraguay-postal-code-areas.csv'
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
check('codes-CRLF', b'\r\n' in raw_c and b'\r' not in raw_c.replace(b'\r\n', b''))
check('codes-trailing-CRLF', raw_c.endswith(b'\r\n'))
check('links-CRLF', b'\r\n' in raw_l and b'\r' not in raw_l.replace(b'\r\n', b''))
check('links-trailing-CRLF', raw_l.endswith(b'\r\n'))
try:
    rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
    codes = list(csv.DictReader(open(C, newline='', encoding='utf-8-sig')))
    links = list(csv.DictReader(open(L, newline='', encoding='utf-8-sig')))
except FileNotFoundError as e:
    check('files-present', False, str(e))
    print(f'{len(fails)} FAILURES')
    sys.exit(1)
byid = {r['source_id']: r for r in rows}
check('areas-281', len(rows) == 281, str(len(rows)))
check('department-17', sum(1 for r in rows if r['type'] == 'department') == 17)
check('capital-1', sum(1 for r in rows if r['type'] == 'capital_district') == 1)
check('district-263', sum(1 for r in rows if r['type'] == 'district') == 263)
iso = {'concepcion': '1', 'san-pedro': '2', 'cordillera': '3', 'guaira': '4',
       'caaguazu': '5', 'caazapa': '6', 'itapua': '7', 'misiones': '8',
       'paraguari': '9', 'alto-parana': '10', 'central': '11', 'neembucu': '12',
       'amambay': '13', 'canindeyu': '14', 'presidente-hayes': '15',
       'alto-paraguay': '16', 'boqueron': '19'}
ok = all(byid.get(f'py:department:{s}', {}).get('code') == c for s, c in iso.items())
check('iso-17', ok and len(iso) == 17)
check('asu-code', byid.get('py:capital_district:asuncion', {}).get('code') == 'ASU')
spots = {'py:district:guayaibi': ('Guayaibí', 'py:department:san-pedro'),
         'py:district:doctor-botrell': ('Doctor Botrell', 'py:department:guaira'),
         'py:district:san-pedro-de-ycuamandiyu': ('San Pedro de Ycuamandiyú',
                                                 'py:department:san-pedro'),
         'py:district:asuncion': ('Asunción', 'py:capital_district:asuncion'),
         'py:district:nueva-asuncion': ('Nueva Asunción', 'py:department:presidente-hayes'),
         'py:district:karapai': ('Karapaí', 'py:department:amambay')}
for sid, (nm, par) in spots.items():
    r = byid.get(sid, {})
    check(f'spot-{sid}', r.get('name') == nm and r.get('parent_source_id') == par,
          str((r.get('name'), r.get('parent_source_id'))))
check('codes-2887', len(codes) == 2887, str(len(codes)))
check('links-2887', len(links) == 2887, str(len(links)))
check('codes-PY', all(c['country_code'] == 'PY' for c in codes))
clist = [c['code'] for c in codes]
check('codes-6digit', all(re.match(r'^\d{6}$', c) for c in clist))
check('codes-unique', len(set(clist)) == len(clist))
check('all-served-by', all(l['relationship_type'] == 'served_by' for l in links))
check('all-primary', all(l['is_primary'] == 'true' for l in links))
have = Counter(l['postcode'] for l in links)
check('multis-0', all(v == 1 for v in have.values()))
check('legs-resolve', all(l['area_source_id'] in byid for l in links))
check('legs-L2-only', all(byid[l['area_source_id']]['level'] == '2' for l in links))
p4 = defaultdict(set)
p2 = defaultdict(set)
par = {r['source_id']: r['parent_source_id'] for r in rows if r['level'] == '2'}
for l in links:
    p4[l['postcode'][:4]].add(l['area_source_id'])
    p2[l['postcode'][:2]].add(par[l['area_source_id']])
check('p4-259-pure', len(p4) == 259 and all(len(v) == 1 for v in p4.values()),
      f'{len(p4)} prefixes')
check('p2-18-pure', len(p2) == 18 and all(len(v) == 1 for v in p2.values()),
      f'{len(p2)} prefixes')
dina = {'00': 'py:capital_district:asuncion', '01': 'py:department:concepcion',
        '02': 'py:department:san-pedro', '03': 'py:department:cordillera',
        '04': 'py:department:guaira', '05': 'py:department:caaguazu',
        '06': 'py:department:caazapa', '07': 'py:department:itapua',
        '08': 'py:department:misiones', '09': 'py:department:paraguari',
        '10': 'py:department:alto-parana', '11': 'py:department:central',
        '12': 'py:department:neembucu', '13': 'py:department:amambay',
        '14': 'py:department:canindeyu', '15': 'py:department:presidente-hayes',
        '16': 'py:department:boqueron', '17': 'py:department:alto-paraguay'}
bad = [k for k, v in p2.items() if v != {dina.get(k)}]
check('dinacopa-depts', not bad, str(bad))
leg = {l['postcode']: l['area_source_id'] for l in links}
for pc, sid in [('001013', 'py:district:asuncion'), ('021601', 'py:district:guayaibi'),
                ('041601', 'py:district:doctor-botrell'),
                ('020101', 'py:district:san-pedro-de-ycuamandiyu')]:
    check(f'anchor-{pc}', leg.get(pc) == sid, str(leg.get(pc)))
cov = Counter(l['area_source_id'] for l in links)
for slug in ['boqueron', 'campo-aceval', 'cerro-cora', 'itacua', 'laurel',
             'nueva-asuncion', 'paso-horqueta', 'puerto-adela', 'san-jose-del-rosario']:
    check(f'codeless-{slug}', cov.get(f'py:district:{slug}', 0) == 0)
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

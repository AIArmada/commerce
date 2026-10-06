import csv, sys
from collections import Counter
# Venezuela gate. Pins the B18 pass + B19 r2 postal top-up (360
# areas: 25 L1 + 335 municipalities; 452 codes / 458 links, all
# LF): tree verified vs es/en wiki + FOTW (Guayana Esequiba
# correctly excluded: no ISO code). TWO Bolívar renames:
# Heres->Angostura del Orinoco, Raúl Leoni->Angostura (name +
# slug). Postal: all 5 shared codes reconfirmed against a FRESH
# Mapanet pull (136+ pages, Oct 2026) — 2301 Guárico-p / Aragua-s,
# 2334 Aragua-p / Guárico-s, 2350 Guárico-p / Anzoátegui-s,
# 3101 Trujillo-p / Mérida-s / Zulia-s; 3158 Mérida-s.
# B19 r2: r2 worker proposed REMOVE 3101-Zulia — REJECTED:
# postcode.info p3101 lists 3 Zulia towns (Arapuey, Boscán,
# El Batey), so the non-primary Zulia leg stands (Mapanet r21
# is lossy here). ADD 8 codes (yb page + postcode.info page
# each, town+state agreeing): 2303/2304 Guárico, 3060 Lara,
# 3102/3108/3113/3115/3149 Trujillo — all STATE-ONLY legs
# (admin2 unverified; Mapanet town-level folds conflict, e.g.
# Carvajal 3101 vs yb/pi 3102: fine-vs-coarse lineage, HOLD
# admin2 + HOLD 3101-Lara spillover Quebrada Arriba 1v1).
# IPOSTEL finder is a JS shell with no static table (no signal).
# Asserts verified state; run from repo root:
# python3 docs/agents/audit/gate_ve.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
G = f'{ROOT}/packages/addressing/resources/geography'
A = f'{G}/venezuela-address-areas.csv'
C = f'{G}/venezuela-postal-codes.csv'
L = f'{G}/venezuela-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
for tag, p, n in [('areas', A, 361), ('codes', C, 453), ('legs', L, 459)]:
    raw = open(p, 'rb').read()
    check(f'{tag}-LF-only', b'\r' not in raw)
    check(f'{tag}-lines-{n}', raw.count(b'\n') == n, str(raw.count(b'\n')))
    check(f'{tag}-trailing-LF', raw.endswith(b'\n'))
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
check('areas-360', len(rows) == 360, str(len(rows)))
check('source-ids-unique', len(byid) == len(rows))
check('L1-25', sum(1 for r in rows if r['level'] == '1') == 25)
check('municipalities-335', sum(1 for r in rows if r['level'] == '2') == 335)
iso = {'amazonas': 'Z', 'anzoategui': 'B', 'apure': 'C', 'aragua': 'D',
       'barinas': 'E', 'bolivar': 'F', 'carabobo': 'G', 'cojedes': 'H',
       'delta-amacuro': 'Y', 'falcon': 'I', 'guarico': 'J', 'la-guaira': 'X',
       'lara': 'K', 'merida': 'L', 'miranda': 'M', 'monagas': 'N',
       'nueva-esparta': 'O', 'portuguesa': 'P', 'sucre': 'R', 'tachira': 'S',
       'trujillo': 'T', 'yaracuy': 'U', 'zulia': 'V'}
ok = all(byid.get(f've:state:{s}', {}).get('code') == c for s, c in iso.items())
check('iso-23', ok and len(iso) == 23)
check('distrito-capital-A', byid.get('ve:capital_district:distrito-capital', {}).get('code') == 'A')
check('dependencias-W', byid.get('ve:federal_dependency:dependencias-federales', {}).get('code') == 'W')
check('no-esequiba', not any('esequib' in s for s in byid))
check('parents-resolve', all(r['parent_source_id'] in byid for r in rows if r['level'] == '2'))
check('parents-are-L1', all(byid[r['parent_source_id']]['level'] == '1' for r in rows if r['level'] == '2'))
check('fix-angostura-orinoco', byid.get('ve:municipality:angostura-del-orinoco', {}).get('name') == 'Angostura del Orinoco')
check('fix-angostura', byid.get('ve:municipality:angostura', {}).get('name') == 'Angostura')
check('fix-heres-gone', 've:municipality:heres' not in byid)
check('fix-raul-leoni-gone', 've:municipality:raul-leoni' not in byid)
codes = [r['code'] for r in csv.DictReader(open(C, newline='', encoding='utf-8'))]
legs = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
check('codes-452', len(codes) == 452, str(len(codes)))
check('codes-unique', len(set(codes)) == len(codes))
check('legs-458', len(legs) == 458, str(len(legs)))
check('legs-resolve', all(r['area_source_id'] in byid for r in legs))
prim = Counter(r['postcode'] for r in legs if r['is_primary'] == 'true')
check('every-code-linked', set(codes) == {r['postcode'] for r in legs})
check('one-primary-each', all(prim.get(c) == 1 for c in codes))
bycode = {}
for r in legs:
    bycode.setdefault(r['postcode'], []).append((r['area_source_id'], r['is_primary']))
check('shared-2301', sorted(bycode['2301']) == [('ve:state:aragua', 'false'), ('ve:state:guarico', 'true')])
check('shared-2334', sorted(bycode['2334']) == [('ve:state:aragua', 'true'), ('ve:state:guarico', 'false')])
check('shared-2350', sorted(bycode['2350']) == [('ve:state:anzoategui', 'false'), ('ve:state:guarico', 'true')])
check('shared-3101', sorted(bycode['3101']) == [('ve:state:merida', 'false'), ('ve:state:trujillo', 'true'), ('ve:state:zulia', 'false')])
check('shared-3158', sorted(bycode['3158']) == [('ve:state:merida', 'false'), ('ve:state:zulia', 'true')])
r2 = {'2303': 've:state:guarico', '2304': 've:state:guarico', '3060': 've:state:lara',
      '3102': 've:state:trujillo', '3108': 've:state:trujillo', '3113': 've:state:trujillo',
      '3115': 've:state:trujillo', '3149': 've:state:trujillo'}
check('r2-adds', all(bycode.get(c) == [(s, 'true')] for c, s in r2.items()))
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

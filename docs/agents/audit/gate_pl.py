import csv, sys
from collections import Counter
# Poland gate. Pins the B19 worker pass ROUNDS 1+2 (396 areas:
# 16 L1 + 380 L2; 20248 codes / 20395 links; areas LF, postal
# pure CRLF): tree VERIFY-ONLY — 380/380 L2 vs eTERYT TERC
# 2026-01-01 + PP operator district enumeration + wiki 380-list,
# 16/16 L1 vs ISO. R1 postal: 198 SIMC-vote conflicts ALL
# adjudicated vs live PP finder (operator PNA rows,
# 2026-10-05) — 154 codes changed: 95 primary flips (10
# same-name-twin clusters: bundle joined wrong voivodeship
# twin; + city-absorption singles), 10 primacy swaps, 59 dropped
# legs (systemic GN artifact: big-city street codes carry one
# bogus land-village row, typo-dupe class proven by twins IN
# GN). R2 postal (525 codes PP-probed: all 332 multis + 198
# conflicts + 94 inverse sweep + 10 novote sample): 227 more
# codes — 17 retarget flips + 81 primacy swaps + 142 leg drops
# + 10 leg adds + 51 stale-code drops (PP+KPI absent, GN rows
# twin/home-explained). Classes: conflict-hold retries (07-304/
# 305/306/308->1416, 66-614->0802, 96-314->1405), over-500 city
# codes (43-300 Bielsko-Biala single, 33-100 Tarnow primary),
# cross-voiv swaps (05-092/192, 05-807, 18-212, 24-120/160,
# 05-101 flip + 2210->1408), city-majority flips (PP street
# majority -> city primary), typo-dupe/noise drops (~110),
# box code 32-312 -> Klucze single. Keeps: thin applied-swap
# city legs, PP-confirmed duals/ties, 1102 GN-unanimous novotes
# (10/10 PP sample). Holds: H-ADD (13 single-signal PP minority
# rows, Lezica rule), H-COV 87-220 absent bundle+GN, H-MEDIUM
# thin-PP flips/drops (second signal each, pinned as-is).
# Asserts verified state; run from repo root:
# python3 docs/agents/audit/gate_pl.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
G = f'{ROOT}/packages/addressing/resources/geography'
A = f'{G}/poland-address-areas.csv'
C = f'{G}/poland-postal-codes.csv'
L = f'{G}/poland-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
raw_a = open(A, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-trailing-LF', raw_a.endswith(b'\n'))
raw_c = open(C, 'rb').read()
check('codes-pure-CRLF', raw_c.count(b'\r\n') == raw_c.count(b'\n') == 20249, str(raw_c.count(b'\r\n')))
raw_l = open(L, 'rb').read()
check('legs-pure-CRLF', raw_l.count(b'\r\n') == raw_l.count(b'\n') == 20396, str(raw_l.count(b'\r\n')))
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
check('areas-396', len(rows) == 396, str(len(rows)))
check('source-ids-unique', len(byid) == len(rows))
check('L1-16', sum(1 for r in rows if r['level'] == '1') == 16)
check('L2-380', sum(1 for r in rows if r['level'] == '2') == 380)
iso = {'lower-silesia': '02', 'kuyavia-pomerania': '04', 'lublin': '06',
       'lubusz': '08', 'odz': '10', 'lesser-poland': '12', 'mazovia': '14',
       'opole': '16', 'subcarpathia': '18', 'podlaskie': '20',
       'pomerania': '22', 'silesia': '24', 'holy-cross': '26',
       'warmia-masuria': '28', 'greater-poland': '30', 'west-pomerania': '32'}
ok = all(byid.get(f'pl:voivodeship:{s}', {}).get('code') == c for s, c in iso.items())
check('iso-16', ok and len(iso) == 16)
check('parents-resolve', all(r['parent_source_id'] in byid for r in rows if r['level'] == '2'))
check('parents-are-L1', all(byid[r['parent_source_id']]['level'] == '1' for r in rows if r['level'] == '2'))
codes = [r['code'] for r in csv.DictReader(open(C, newline='', encoding='utf-8'))]
legs = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
check('codes-20248', len(codes) == 20248, str(len(codes)))
check('codes-unique', len(set(codes)) == len(codes))
check('legs-20395', len(legs) == 20395, str(len(legs)))
check('legs-resolve', all(r['area_source_id'] in byid for r in legs))
prim = Counter(r['postcode'] for r in legs if r['is_primary'] == 'true')
check('every-code-linked', set(codes) == {r['postcode'] for r in legs})
check('one-primary-each', all(prim.get(c) == 1 for c in codes))
bycode = {}
for r in legs:
    bycode.setdefault(r['postcode'], {})[r['area_source_id']] = r['is_primary']
# Twin-cluster spot pins (one per cluster).
check('twin-sredzki', bycode['55-300'].get('pl:land_county:powiat-sredzki') == 'true')
check('twin-swidnicki', bycode['58-100'].get('pl:land_county:powiat-swidnicki') == 'true')
check('twin-tomaszowski', bycode['22-600'].get('pl:land_county:powiat-tomaszowski') == 'true')
check('twin-opolski', bycode['24-300'].get('pl:land_county:powiat-opolski') == 'true')
check('twin-krosnienski', bycode['66-600'].get('pl:land_county:powiat-krosnienski') == 'true')
check('twin-brzeski', bycode['32-800'].get('pl:land_county:powiat-brzeski') == 'true')
check('twin-grodziski', bycode['05-825'].get('pl:land_county:powiat-grodziski') == 'true')
check('twin-nowodworski', bycode['05-100'].get('pl:land_county:powiat-nowodworski') == 'true')
check('twin-ostrowski', bycode['07-300'].get('pl:land_county:powiat-ostrowski') == 'true')
check('twin-bielski', bycode['17-100'].get('pl:land_county:powiat-bielski') == 'true')
check('single-rzeszow', bycode['35-212'].get('pl:city_county:rzeszow') == 'true')
check('swap-bialystok', bycode['15-521'].get('pl:land_county:powiat-bialostocki') == 'true')
check('swap-elblag', bycode['82-300'].get('pl:land_county:powiat-elblaski') == 'true')
check('drop-wroclaw-50-003', 'pl:land_county:powiat-wroclawski' not in bycode['50-003'])
check('keep-89-525', bycode['89-525'].get('pl:land_county:powiat-tucholski') == 'true')
# R2 spot pins (one per fix class).
check('r2-flip-07-304', bycode['07-304'] == {'pl:land_county:powiat-ostrowski': 'true'})
check('r2-flip-05-101', bycode['05-101'].get('pl:land_county:powiat-nowodworski') == 'true')
check('r2-add-05-101', bycode['05-101'].get('pl:land_county:powiat-legionowski') == 'false')
check('r2-city-43-300', bycode['43-300'] == {'pl:city_county:bielsko-biala': 'true'})
check('r2-swap-07-401', bycode['07-401'].get('pl:city_county:ostroleka') == 'true')
check('r2-drop-00-100', 'pl:land_county:powiat-plonski' not in bycode['00-100'])
check('r2-box-32-312', bycode['32-312'] == {'pl:land_county:powiat-olkuski': 'true'})
check('r2-dropcode-00-320', '00-320' not in bycode and '00-320' not in codes)
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

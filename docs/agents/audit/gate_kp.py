import csv, os, sys
from collections import Counter
# North Korea gate. Verify-only B15 pass: the tree is exactly the OCHA
# COD-AB v01 DPRK release (valid 2019-06-24) — 179/179 admin2 rows match
# on pcode + name + parent, per-parent counts match, and all 13 L1 units
# match ISO 3166-2:KP (01-10 + 13/14/15). COD-AB covers 11 L1 (no
# Kaesong/Rason subdivisions); Kaesong + Rason ship childless, Pyongyang
# ships 3 rows (core + Kangdong + Unjong), Nampo 6. The Dec-2019
# Samjiyon county->city upgrade is grain-invisible (bare name kept).
# No postcode system: postal files must stay absent.
# Asserts POST-fix (= pre-fix) state; run from repo root:
# python3 docs/agents/audit/gate_kp.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/north-korea-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/north-korea-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/north-korea-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
check('no-postal-codes-file', not os.path.exists(C))
check('no-postal-legs-file', not os.path.exists(L))
raw_a = open(A, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-trailing-LF', raw_a.endswith(b'\n') and not raw_a.endswith(b'\r\n'))
try:
    rows = list(csv.DictReader(open(A, newline='', encoding='utf-8-sig')))
except FileNotFoundError as e:
    check('files-present', False, str(e))
    print(f'{len(fails)} FAILURES')
    sys.exit(1)
byid = {r['source_id']: r for r in rows}
check('areas-192', len(rows) == 192, str(len(rows)))
check('l1-13', sum(1 for r in rows if r['level'] == '1') == 13)
check('l2-179', sum(1 for r in rows if r['level'] == '2') == 179)
iso = {'kp:capital_city:pyongyang': ('Pyongyang', '01', 'capital_city'),
       'kp:province:south-pyongan': ('South Pyongan', '02', 'province'),
       'kp:province:north-pyongan': ('North Pyongan', '03', 'province'),
       'kp:province:chagang': ('Chagang', '04', 'province'),
       'kp:province:south-hwanghae': ('South Hwanghae', '05', 'province'),
       'kp:province:north-hwanghae': ('North Hwanghae', '06', 'province'),
       'kp:province:kangwon': ('Kangwon', '07', 'province'),
       'kp:province:south-hamgyong': ('South Hamgyong', '08', 'province'),
       'kp:province:north-hamgyong': ('North Hamgyong', '09', 'province'),
       'kp:province:ryanggang': ('Ryanggang', '10', 'province'),
       'kp:special_city:rason': ('Rason', '13', 'special_city'),
       'kp:special_city:nampo': ('Nampo', '14', 'special_city'),
       'kp:special_city:kaesong': ('Kaesong', '15', 'special_city')}
for sid, (nm, cd, ty) in iso.items():
    r = byid.get(sid)
    check(f'l1-{cd}', bool(r) and r['name'] == nm and r['code'] == cd
          and r['type'] == ty and r['level'] == '1'
          and r['parent_source_id'] == '', str(r))
got = Counter(r['parent_source_id'] for r in rows if r['level'] == '2')
expect = {'kp:capital_city:pyongyang': 3, 'kp:province:chagang': 18,
          'kp:province:kangwon': 17, 'kp:province:north-hamgyong': 16,
          'kp:province:north-hwanghae': 21, 'kp:province:north-pyongan': 25,
          'kp:province:ryanggang': 12, 'kp:province:south-hamgyong': 20,
          'kp:province:south-hwanghae': 20, 'kp:province:south-pyongan': 21,
          'kp:special_city:nampo': 6, 'kp:special_city:rason': 0,
          'kp:special_city:kaesong': 0}
for par, n in expect.items():
    check(f'l2-{par.split(":")[-1]}-{n}', got.get(par, 0) == n,
          str(got.get(par, 0)))
check('l2-total-179', sum(got.values()) == 179, str(sum(got.values())))
# COD-AB pcode spot pins (name + parent + pcode triple).
pins = {'kp:district:nampo-city': ('KP1102', 'Nampo City', 'kp:special_city:nampo'),
        'kp:district:samjiyon': ('KP0107', 'Samjiyon', 'kp:province:ryanggang')}
for sid, (pc, nm, par) in pins.items():
    r = byid.get(sid)
    check(f'pcode-{pc}', bool(r) and r['code'] == pc and r['name'] == nm
          and r['parent_source_id'] == par, str(r))
orphans = [r['source_id'] for r in rows
           if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orphans, str(orphans[:5]))
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

import csv, os, sys
# Hong Kong gate. B5 revisit: verify-only, zero data changes.
# Tree 18/18 vs Districts of Hong Kong + Statoids (no ISO 3166-2:HK
# subdivisions; H/K/N-prefix codes synthetic with region grouping:
# HKI 4, Kowloon 5, NT 9). No postal files: HK has no postcode
# system (UPU hkg examples carry no postcode section); GeoNames
# 999077 is the mainland-CN-assigned code, correctly excluded.
# Run from repo root: python3 docs/agents/audit/gate_hk.py
A = './packages/addressing/resources/geography/hong-kong-address-areas.csv'
C = './packages/addressing/resources/geography/hong-kong-postal-codes.csv'
L = './packages/addressing/resources/geography/hong-kong-postal-code-areas.csv'
DIST = {'HCW': 'Central and Western', 'HEA': 'Eastern',
        'NIS': 'Islands', 'KKC': 'Kowloon City', 'NKT': 'Kwai Tsing',
        'KKT': 'Kwun Tong', 'NNO': 'North', 'NSK': 'Sai Kung',
        'NST': 'Sha Tin', 'KSS': 'Sham Shui Po', 'HSO': 'Southern',
        'NTP': 'Tai Po', 'NTW': 'Tsuen Wan', 'NTM': 'Tuen Mun',
        'HWC': 'Wan Chai', 'KWT': 'Wong Tai Sin',
        'KYT': 'Yau Tsim Mong', 'NYL': 'Yuen Long'}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


areas = list(csv.DictReader(open(A, newline='')))
check('areas-18', len(areas) == 18, str(len(areas)))
got = {r['code']: r['name'] for r in areas}
check('names-18', got == DIST, str({k for k in DIST if got.get(k) != DIST[k]}))
check('regions-4-5-9', sum(1 for r in areas if r['code'].startswith('H')) == 4
      and sum(1 for r in areas if r['code'].startswith('K')) == 5
      and sum(1 for r in areas if r['code'].startswith('N')) == 9)
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES: {fails}')
sys.exit(1 if fails else 0)

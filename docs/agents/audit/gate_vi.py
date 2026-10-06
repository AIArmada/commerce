import csv, sys
# US Virgin Islands gate. B6 revisit: verify-only, zero data
# changes. Tree 23/23 vs Statoids districts + subdistricts (codes
# SC/SJ/ST on districts). Overlay 16/16 at district level exact vs
# the GeoNames VI dump + UPU vir range 00801-00851 (Christiansted/
# Frederiksted/Kingshill 00820-00851 all Saint Croix). Subdistrict
# split + PO-only status documented in the overlay row (no public
# per-subdistrict directory). Run from repo root:
# python3 docs/agents/audit/gate_vi.py
A = './packages/addressing/resources/geography/us-virgin-islands-address-areas.csv'
C = './packages/addressing/resources/geography/us-virgin-islands-postal-codes.csv'
L = './packages/addressing/resources/geography/us-virgin-islands-postal-code-areas.csv'
SC = 'vi:district:saint-croix'
SJ = 'vi:district:saint-john'
ST = 'vi:district:saint-thomas'
LINKS = {'00801': ST, '00802': ST, '00803': ST, '00804': ST, '00805': ST,
         '00820': SC, '00821': SC, '00822': SC, '00823': SC, '00824': SC,
         '00830': SJ, '00831': SJ,
         '00840': SC, '00841': SC, '00850': SC, '00851': SC}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


areas = list(csv.DictReader(open(A, newline='')))
check('areas-23', len(areas) == 23, str(len(areas)))
check('districts-3', sum(1 for r in areas if r['type'] == 'district') == 3)
subs = [r for r in areas if r['type'] == 'subdistrict']
check('subdistricts-20', len(subs) == 20, str(len(subs)))
check('sc-9', sum(1 for r in subs if r['parent_source_id'] == SC) == 9)
check('st-7', sum(1 for r in subs if r['parent_source_id'] == ST) == 7)
check('sj-4', sum(1 for r in subs if r['parent_source_id'] == SJ) == 4)
codes = [r['code'] for r in csv.DictReader(open(C, newline=''))]
check('codes-16', set(codes) == set(LINKS), str(set(codes) ^ set(LINKS)))
links = list(csv.DictReader(open(L, newline='')))
check('links-16', len(links) == 16, str(len(links)))
ok = all(r['postcode'] in LINKS and r['area_source_id'] == LINKS[r['postcode']]
         and r['is_primary'] == 'true' for r in links)
check('mapping-16', ok)
print('ALL PASS' if not fails else f'{len(fails)} FAILURES: {fails}')
sys.exit(1 if fails else 0)

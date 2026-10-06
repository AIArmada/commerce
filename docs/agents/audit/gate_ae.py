import csv, os, sys
# UAE gate. No postcode system (P.O. Boxes only). M1 revisit:
# verify-only, zero data changes. Run from repo root:
# python3 docs/agents/audit/gate_ae.py
A = './packages/addressing/resources/geography/united-arab-emirates-address-areas.csv'
C = './packages/addressing/resources/geography/united-arab-emirates-postal-codes.csv'
L = './packages/addressing/resources/geography/united-arab-emirates-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
byid = {r['source_id']: r for r in rows}
check('areas-7', len(rows) == 7, str(len(rows)))
check('all-emirate-L1-root', all(r['type'] == 'emirate' and r['level'] == '1'
      and r['parent_source_id'] == '' for r in rows))
# ISO 3166-2:AE.
iso = {'ae:emirate:ajman': ('Ajman', 'AJ'),
       'ae:emirate:abu-dhabi': ('Abu Dhabi', 'AZ'),
       'ae:emirate:dubai': ('Dubai', 'DU'),
       'ae:emirate:fujairah': ('Fujairah', 'FU'),
       'ae:emirate:ras-al-khaimah': ('Ras Al Khaimah', 'RK'),
       'ae:emirate:sharjah': ('Sharjah', 'SH'),
       'ae:emirate:umm-al-quwain': ('Umm Al Quwain', 'UQ')}
for sid, (nm, cd) in iso.items():
    r = byid.get(sid)
    check(f'iso-{cd}', r and r['name'] == nm and r['code'] == cd, str(r))
# Verdict none: no postal overlay files. UPU ARE profile 09/2014:
# "Deliveries are made to P.O. Boxes only".
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
# Known trap (do NOT import): GeoNames AE.txt holds 178,171 Makani
# geocode rows (N 5+5 digits), zero real postcodes. UPU Sep-2025
# do-not-require list also carries the UAE.
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

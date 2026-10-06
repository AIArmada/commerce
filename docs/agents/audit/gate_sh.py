import csv, os, sys
# Saint-Helena gate. B3 revisit: verify-only, zero data changes.
# Run from repo root: python3 docs/agents/audit/gate_sh.py
A = './packages/addressing/resources/geography/saint-helena-address-areas.csv'
C = './packages/addressing/resources/geography/saint-helena-postal-codes.csv'
L = './packages/addressing/resources/geography/saint-helena-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A, newline='')))
byid = {r['source_id']: r for r in rows}
check('areas-10', len(rows) == 10, str(len(rows)))
check('districts-8', sum(1 for r in rows if r['type'] == 'district') == 8)
check('islands-2', sum(1 for r in rows if r['type'] == 'island') == 2)
# ISO 3166-2:SH covers the three islands (AC/HL/TA); the eight St
# Helena districts carry synthetic 01-08 (file order swaps 07/08).
exp = {'sh:district:alarm-forest': ('Alarm Forest', '01'),
       'sh:district:blue-hill': ('Blue Hill', '02'),
       'sh:district:half-tree-hollow': ('Half Tree Hollow', '03'),
       'sh:district:jamestown': ('Jamestown', '04'),
       'sh:district:levelwood': ('Levelwood', '05'),
       'sh:district:longwood': ('Longwood', '06'),
       'sh:district:sandy-bay': ('Sandy Bay', '07'),
       'sh:district:saint-pauls': ("Saint Paul's", '08'),
       'sh:island:ascension': ('Ascension', 'AC'),
       'sh:island:tristan-da-cunha': ('Tristan da Cunha', 'TA')}
for sid, (name, code) in exp.items():
    r = byid.get(sid)
    check(f'area-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1' and r['parent_source_id'] == '', str(r))
codes = [r['code'] for r in csv.DictReader(open(C, newline=''))]
links = list(csv.DictReader(open(L, newline='')))
# UK postcode areas list + territory article: STHL 1ZZ St Helena
# (UPU shnEn single-code sheet, Jamestown GPO example), ASCN 1ZZ
# Ascension, TDCU 1ZZ Tristan. STHL shared by all 8 districts
# with Jamestown (office-holding GPO) primary.
check('codes-3', sorted(codes) == ['ASCN 1ZZ', 'STHL 1ZZ', 'TDCU 1ZZ'], str(sorted(codes)))
check('links-10', len(links) == 10, str(len(links)))
prim = {r['postcode']: r['area_source_id'] for r in links if r['is_primary'] == 'true'}
check('primaries', prim == {'STHL 1ZZ': 'sh:district:jamestown',
      'ASCN 1ZZ': 'sh:island:ascension', 'TDCU 1ZZ': 'sh:island:tristan-da-cunha'}, str(prim))
sec = sorted(r['area_source_id'] for r in links if r['is_primary'] != 'true')
check('sthl-secondaries-7', len(sec) == 7 and 'sh:district:jamestown' not in sec, str(sec))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

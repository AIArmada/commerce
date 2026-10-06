import csv, os, sys
# Saint-Pierre-and-Miquelon gate. B1 revisit: verify-only, zero
# data changes. Run from repo root: python3 docs/agents/audit/gate_pm.py
A = './packages/addressing/resources/geography/saint-pierre-and-miquelon-address-areas.csv'
C = './packages/addressing/resources/geography/saint-pierre-and-miquelon-postal-codes.csv'
L = './packages/addressing/resources/geography/saint-pierre-and-miquelon-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A, newline='')))
# ISO 3166-2:PM defines no codes; 01 synthetic.
check('areas-1', len(rows) == 1, str(len(rows)))
r = rows[0] if rows else {}
check('single-collectivity',
      r.get('source_id') == 'pm:overseas_collectivity:saint-pierre-and-miquelon'
      and r.get('name') == 'Saint-Pierre and Miquelon'
      and r.get('code') == '01' and r.get('level') == '1'
      and r.get('parent_source_id') == '', str(r))
codes = [x['code'] for x in csv.DictReader(open(C, newline=''))]
links = list(csv.DictReader(open(L, newline='')))
# UPU spmEn (08/2011): single code 97500; the worked example is a
# Miquelon address, so both communes share the one code.
check('codes-97500', codes == ['97500'], str(codes))
check('links-1', len(links) == 1, str(len(links)))
l = links[0] if links else {}
check('link-97500', l.get('postcode') == '97500'
      and l.get('area_source_id') == 'pm:overseas_collectivity:saint-pierre-and-miquelon'
      and l.get('relationship_type') == 'served_by'
      and l.get('is_primary') == 'true', str(l))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

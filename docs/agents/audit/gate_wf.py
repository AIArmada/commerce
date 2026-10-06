import csv, os, sys
# Wallis-and-Futuna gate. B2 revisit: verify-only, zero data changes.
# Run from repo root: python3 docs/agents/audit/gate_wf.py
A = './packages/addressing/resources/geography/wallis-and-futuna-address-areas.csv'
C = './packages/addressing/resources/geography/wallis-and-futuna-postal-codes.csv'
L = './packages/addressing/resources/geography/wallis-and-futuna-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A, newline='')))
byid = {r['source_id']: r for r in rows}
check('areas-6', len(rows) == 6, str(len(rows)))
# ISO 3166-2:WF: three kingdoms (precincts).
iso = {'wf:administrative_precinct:uvea': ('Uvea', 'UV'),
       'wf:administrative_precinct:alo': ('Alo', 'AL'),
       'wf:administrative_precinct:sigave': ('Sigave', 'SG')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['type'] == 'administrative_precinct' and r['level'] == '1'
          and r['parent_source_id'] == '', str(r))
# The three districts of Uvea (no ISO codes; code cells empty).
uvea = 'wf:administrative_precinct:uvea'
for slug, name in [('hihifo', 'Hihifo'), ('hahake', 'Hahake'), ('mu-a', "Mu'a")]:
    sid = f'wf:district:{slug}'
    r = byid.get(sid)
    check(f'district-{slug}', bool(r) and r['name'] == name and r['code'] == ''
          and r['type'] == 'district' and r['level'] == '2'
          and r['parent_source_id'] == uvea, str(r))
codes = [r['code'] for r in csv.DictReader(open(C, newline=''))]
links = list(csv.DictReader(open(L, newline='')))
# La Poste Hexasmal (official): 98600 UVEA, 98610 ALO, 98620 SIGAVE.
table = {'98600': uvea,
         '98610': 'wf:administrative_precinct:alo',
         '98620': 'wf:administrative_precinct:sigave'}
check('codes-3', sorted(codes) == sorted(table), str(sorted(codes)))
check('links-3', len(links) == 3, str(len(links)))
bycode = {r['postcode']: r for r in links}
for pc, sid in table.items():
    r = bycode.get(pc)
    check(f'link-{pc}', bool(r) and r['area_source_id'] == sid
          and r['relationship_type'] == 'served_by'
          and r['is_primary'] == 'true', str(r))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

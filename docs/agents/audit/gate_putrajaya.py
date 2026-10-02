import csv, sys
from collections import Counter
AREAS = './packages/addressing/resources/geography/malaysia-address-areas.csv'
LINKS = './packages/addressing/resources/geography/malaysia-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(AREAS)))
links = list(csv.DictReader(open(LINKS)))
byid = {r['source_id']: r for r in rows}
K = 'my:subdistrict:state:wilayah-persekutuan-putrajaya'
S = 'my:state:wilayah-persekutuan-putrajaya'
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', not multi, str(multi[:5]))
subs = [r for r in rows if r['parent_source_id'] == S]
check('putrajaya-subdistricts-20', len(subs) == 20, str(len(subs)))
check('all-precinct-type', all(r['type'] == 'precinct' for r in subs))
check('all-level-2', all(r['level'] == '2' for r in subs))
have = sorted(r['source_id'].split(':')[-1] for r in subs)
want = sorted(f'precinct-{i}' for i in range(1, 21))
check('precincts-1-to-20', have == want, str(set(want) - set(have)))
for r in subs:
    sid = r['source_id']
    check(f'{sid.split(":")[-1]}-linked', any(l['area_source_id'] == sid for l in links))
moves = [('62000',f'{K}:precinct-1'),('62050',f'{K}:precinct-14'),
         ('62100',f'{K}:precinct-2'),('62150',f'{K}:precinct-5'),
         ('62250',f'{K}:precinct-7'),('62300',f'{K}:precinct-11'),
         ('62502',S),('62200',S)]
for pc, sid in moves:
    hits = [l for l in links if l['postcode']==pc and l['is_primary']=='true']
    check(f'{pc}-primary-{sid.split(":")[-1]}', len(hits)==1 and hits[0]['area_source_id']==sid, str(hits))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

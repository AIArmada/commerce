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
K = 'my:subdistrict:state:wilayah-persekutuan-kuala-lumpur'
S = 'my:state:wilayah-persekutuan-kuala-lumpur'
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', not multi, str(multi[:5]))
subs = [r for r in rows if r['parent_source_id'] == S]
check('kl-subdistricts-30', len(subs) == 30, str(len(subs)))
check('all-level-2', all(r['level'] == '2' for r in subs))
mbp = sum(1 for r in subs if r['type'] in ('mukim','bandar','pekan'))
check('kl-mbp-19', mbp == 19, str(mbp))
towns = [('bandar-kuala-lumpur','bandar'),('bandar-petaling-jaya','bandar'),
         ('bandar-bandar-baharu-sungai-besi','bandar'),('bandar-sungai-besi','bandar'),
         ('pekan-batu','pekan'),('pekan-batu-caves','pekan'),('pekan-kepong','pekan'),
         ('pekan-kuala-pauh','pekan'),('pekan-petaling','pekan'),('pekan-salak-south','pekan'),
         ('pekan-sungai-penchala','pekan'),('pekan-sungai-besi','pekan')]
for slug, t in towns:
    r = byid.get(f'{K}:{slug}')
    check(f'{slug}={t}-L2', r and r['type']==t and r['level']=='2', str(r))
    check(f'{slug}-linkless', not [l for l in links if l['area_source_id']==f'{K}:{slug}'])
# postal design untouched: state + constituencies keep their codes
stays = [('52100',f'{K}:kepong'),('52000',f'{K}:kepong'),('50200',f'{K}:bukit-bintang'),
         ('56000',f'{K}:cheras'),('56100',f'{K}:cheras'),('59100',f'{K}:lembah-pantai'),
         ('51200',f'{K}:segambut'),('58000',f'{K}:seputeh'),('53200',f'{K}:titiwangsa'),
         ('52200',S),('53100',S),('60000',S),('50000',S)]
for pc, sid in stays:
    hits = [l for l in links if l['postcode']==pc and l['is_primary']=='true']
    check(f'{pc}-stays-{sid.split(":")[-1]}', len(hits)==1 and hits[0]['area_source_id']==sid, str(hits))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

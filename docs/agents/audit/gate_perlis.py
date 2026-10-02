import csv, sys
AREAS = './packages/addressing/resources/geography/malaysia-address-areas.csv'
LINKS = './packages/addressing/resources/geography/malaysia-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(AREAS)))
links = list(csv.DictReader(open(LINKS)))
byid = {r['source_id']: r for r in rows}
K = 'my:subdistrict:state:perlis'
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
from collections import Counter
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', not multi, str(multi[:5]))
# 22 mukims + 2 new towns + 2 retyped + 2 localities = 28 subdistricts
subs = [r for r in rows if r['parent_source_id'] == 'my:state:perlis']
check('perlis-subdistricts-28', len(subs) == 28, str(len(subs)))
check('all-level-2', all(r['level'] == '2' for r in subs))
mbp = sum(1 for r in subs if r['type'] in ('mukim','bandar','pekan'))
check('perlis-mbp-26', mbp == 26, str(mbp))
for slug, t in [('bandar-arau','bandar'),('pekan-kuala-perlis','pekan'),
                ('kangar','bandar'),('kaki-bukit','pekan'),
                ('padang-besar','locality'),('simpang-empat','locality')]:
    check(f'{slug}={t}', byid[f'{K}:{slug}']['type'] == t)
moves = [('02600',f'{K}:bandar-arau'),('02607',f'{K}:bandar-arau'),
         ('02609',f'{K}:bandar-arau'),('02000',f'{K}:pekan-kuala-perlis'),
         ('01000',f'{K}:kangar'),('02200',f'{K}:kaki-bukit'),
         ('02100',f'{K}:padang-besar'),('02700',f'{K}:simpang-empat')]
for pc, sid in moves:
    hits = [l for l in links if l['postcode']==pc and l['is_primary']=='true']
    check(f'{pc}-primary-{sid.split(":")[-1]}', len(hits)==1 and hits[0]['area_source_id']==sid, str(hits))
for pc, slug in [('02600','arau'),('02607','arau'),('02609','arau'),
                 ('02000','kuala-perlis'),('01000','jejawi'),
                 ('02100','titi-tinggi'),('02200','titi-tinggi'),('02700','sanglang')]:
    sid = f'{K}:{slug}'
    hits = [l for l in links if l['postcode']==pc and l['area_source_id']==sid]
    check(f'{pc}-secondary-{slug}', len(hits)==1 and hits[0]['is_primary']=='false', str(hits))
for slug in ['arau','kuala-perlis']:
    sid = f'{K}:{slug}'
    check(f'{slug}-no-primaries', not [l for l in links if l['area_source_id']==sid and l['is_primary']=='true'])
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

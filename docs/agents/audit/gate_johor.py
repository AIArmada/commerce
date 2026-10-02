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
K = 'my:subdistrict:district:johor'
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', not multi, str(multi[:5]))
# retype + removal
check('retype muar:panchor=bandar', byid[f'{K}:muar:panchor']['type'] == 'bandar')
check('removed segamat:bandar-segamat', f'{K}:segamat:bandar-segamat' not in byid)
# adds spot checks (type + parent + level)
spots = [('johor-bahru:bandar-tebrau','bandar','johor-bahru'),
 ('kluang:bandar-paloh','bandar','kluang'),('kluang:bandar-rengam','bandar','kluang'),
 ('mersing:bandar-jemaluang','bandar','mersing'),('mersing:mersing-kanan','bandar','mersing'),
 ('mersing:bandar-padang-endau','bandar','mersing'),('muar:bandar-bukit-kepong','bandar','muar'),
 ('muar:bandar-parit-jawa','bandar','muar'),('pontian:bandar-benut','bandar','pontian'),
 ('segamat:bandar-bekok','bandar','segamat'),('segamat:bandar-buloh-kasap','bandar','segamat'),
 ('segamat:bandar-jementah','bandar','segamat'),('segamat:bandar-labis','bandar','segamat'),
 ('segamat:gemas-bahru','pekan','segamat'),('tangkak:bukit-kangkar','bandar','tangkak'),
 ('tangkak:parit-bunga','bandar','tangkak'),('tangkak:bandar-serom','bandar','tangkak'),
 ('tangkak:pekan-grisek','pekan','tangkak')]
ok = True
for slug, t, parent in spots:
    r = byid.get(f'{K}:{slug}')
    if not r or r['type'] != t or r['parent_source_id'] != f'my:district:johor:{parent}' or r['level'] != '3':
        print('FAIL spot', slug, r); fails.append(f'spot {slug}'); ok = False
if ok: print('PASS all 18 add spots')
# town-code primaries moved
moves = [('82200',f'{K}:pontian:bandar-benut'),('84150',f'{K}:muar:bandar-parit-jawa'),
 ('84160',f'{K}:muar:bandar-parit-jawa'),('86600',f'{K}:kluang:bandar-paloh'),
 ('86300',f'{K}:kluang:bandar-rengam'),('85200',f'{K}:segamat:bandar-jementah'),
 ('85300',f'{K}:segamat:bandar-labis'),('86500',f'{K}:segamat:bandar-bekok'),
 ('85010',f'{K}:segamat:bandar-buloh-kasap'),('84700',f'{K}:tangkak:pekan-grisek'),
 ('85210',f'{K}:segamat:bandar-jementah'),('85220',f'{K}:segamat:bandar-jementah'),
 ('84710',f'{K}:tangkak:pekan-grisek')]
for pc, sid in moves:
    hits = [l for l in links if l['postcode']==pc and l['is_primary']=='true']
    check(f'{pc}-primary-{sid.split(":")[-1]}', len(hits)==1 and hits[0]['area_source_id']==sid, str(hits))
# old holders carry no primaries now
for slug in ['pontian:benut','muar:parit-jawa','kluang:paloh','segamat:jementah','segamat:labis',
             'segamat:bekok','kluang:renggam','tangkak:gerisek']:
    sid = f'{K}:{slug}'
    check(f'{slug}-no-primaries', not [l for l in links if l['area_source_id']==sid and l['is_primary']=='true'])
# untouched primaries stay put
stays = [('85000',f'{K}:segamat:segamat'),('84500',f'{K}:muar:panchor'),
 ('84000',f'{K}:muar:bandar'),('73400','my:subdistrict:district:negeri-sembilan:tampin:bandar-gemas')]
for pc, sid in stays:
    hits = [l for l in links if l['postcode']==pc and l['is_primary']=='true']
    check(f'{pc}-stays-{sid.split(":")[-1]}', len(hits)==1 and hits[0]['area_source_id']==sid, str(hits))
# new secondaries present and non-primary
for pc, slug in [('84600','muar:bandar-bukit-kepong'),('73400','segamat:gemas-bahru'),
                 ('84000','tangkak:parit-bunga'),('84400','tangkak:bandar-serom'),
                 ('84400','tangkak:bukit-kangkar'),('86300','kluang:renggam'),('84700','tangkak:gerisek'),
                 ('84710','tangkak:gerisek'),
                 ('82200','pontian:benut'),('84150','muar:parit-jawa'),('84160','muar:parit-jawa'),
                 ('86600','kluang:paloh'),('85200','segamat:jementah'),('85300','segamat:labis'),
                 ('86500','segamat:bekok'),('85010','segamat:buloh-kasap')]:
    sid = f'{K}:{slug}'
    hits = [l for l in links if l['postcode']==pc and l['area_source_id']==sid]
    check(f'{pc}-secondary-{slug.split(":")[-1]}', len(hits)==1 and hits[0]['is_primary']=='false', str(hits))
# 82200 secondaries stay on the mukims
for slug in ['pontian:benut','pontian:sungai-pinggan']:
    sid = f'{K}:{slug}'
    hits = [l for l in links if l['postcode']=='82200' and l['area_source_id']==sid]
    check(f'82200-secondary-stays-{slug.split(":")[-1]}', len(hits)==1 and hits[0]['is_primary']=='false', str(hits))
# linkless new rows (no town-code evidence)
for slug in ['johor-bahru:bandar-tebrau','mersing:bandar-jemaluang','mersing:mersing-kanan',
             'mersing:bandar-padang-endau']:
    sid = f'{K}:{slug}'
    check(f'{slug}-linkless', not [l for l in links if l['area_source_id']==sid])
# district counts pin completion (all 10 = UPI)
for d, mbp in [('batu-pahat',19),('johor-bahru',8),('kluang',11),('kota-tinggi',11),('mersing',18),
               ('muar',17),('pontian',14),('segamat',18),('kulai',5),('tangkak',12)]:
    n = sum(1 for r in rows if r['parent_source_id']==f'my:district:johor:{d}' and r['type'] in ('mukim','bandar','pekan'))
    check(f'{d}-count-{mbp}', n==mbp, str(n))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

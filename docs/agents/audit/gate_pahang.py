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
K = 'my:subdistrict:district:pahang'
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', not multi, str(multi[:5]))
# retypes
for slug, t in [('kuantan:gambang','bandar'),('lipis:benta','pekan'),('lipis:padang-tengku','pekan'),('bera:mengkarak','pekan')]:
    check(f'retype {slug}={t}', byid[f'{K}:{slug}']['type'] == t)
# adds spot checks (type + parent)
spots = [('bentong:bandar-bentong','bandar','bentong'),('bentong:telemung','pekan','bentong'),
 ('cameron-highlands:bandar-tanah-rata','bandar','cameron-highlands'),('cameron-highlands:lubok-tamang','pekan','cameron-highlands'),
 ('cameron-highlands:pekan-ringlet','pekan','cameron-highlands'),('jerantut:pekan-kuala-tembeling','pekan','jerantut'),
 ('jerantut:jeransang','pekan','jerantut'),('kuantan:pekan-beserah','pekan','kuantan'),('kuantan:tanjung-lumpur','pekan','kuantan'),
 ('lipis:bandar-kuala-lipis','bandar','lipis'),('pekan:bandar-pekan','bandar','pekan'),('pekan:pekan-kuala-pahang','pekan','pekan'),
 ('pekan:nenasi','pekan','pekan'),('raub:pekan-raub','pekan','raub'),('raub:pekan-dong','pekan','raub'),('raub:pekan-tras','pekan','raub'),
 ('raub:cheroh','pekan','raub'),('raub:sang-lee','pekan','raub'),('raub:sungai-ruan','pekan','raub'),('raub:sungai-kelau','pekan','raub'),
 ('temerloh:bandar-mentakab','bandar','temerloh'),('temerloh:pekan-kerdau','pekan','temerloh'),
 ('rompin:baharu-rompin','bandar','rompin'),('rompin:rompin-i','bandar','rompin'),('rompin:rompin-ii','bandar','rompin'),
 ('rompin:rompin-iii','bandar','rompin'),('rompin:rompin-iv','bandar','rompin'),('rompin:bandar-pontian','bandar','rompin'),
 ('rompin:bandar-endau','bandar','rompin'),('rompin:bandar-tioman','bandar','rompin'),('rompin:pekan-tioman','pekan','rompin'),
 ('maran:pekan-chenor','pekan','maran'),('maran:sri-jaya','pekan','maran'),('bera:bandar-triang','bandar','bera'),
 ('bera:durian-tawar','pekan','bera'),('bera:mengkuang','pekan','bera')]
ok = True
for slug, t, parent in spots:
    r = byid.get(f'{K}:{slug}')
    if not r or r['type'] != t or r['parent_source_id'] != f'my:district:pahang:{parent}' or r['level'] != '3':
        print('FAIL spot', slug, r); fails.append(f'spot {slug}'); ok = False
if ok: print('PASS all 36 add spots')
# pekan/bandar primaries moved
moves = [('39000',f'{K}:cameron-highlands:bandar-tanah-rata'),('39200',f'{K}:cameron-highlands:pekan-ringlet'),
 ('26600',f'{K}:pekan:bandar-pekan'),('26680',f'{K}:pekan:bandar-pekan'),('28400',f'{K}:temerloh:bandar-mentakab'),
 ('28100',f'{K}:maran:pekan-chenor'),('28300',f'{K}:bera:bandar-triang'),('27400',f'{K}:raub:pekan-dong'),
 ('27500',f'{K}:raub:sungai-ruan'),('28700',f'{K}:bentong:bandar-bentong'),('26100',f'{K}:kuantan:pekan-beserah')]
for pc, sid in moves:
    hits = [l for l in links if l['postcode']==pc and l['is_primary']=='true']
    check(f'{pc}-primary-{sid.split(":")[-1]}', len(hits)==1 and hits[0]['area_source_id']==sid, str(hits))
# old holders linkless or secondary-only
for sid in [f'{K}:cameron-highlands:tanah-rata',f'{K}:cameron-highlands:ringlet',f'{K}:pekan:pekan',
  f'{K}:temerloh:mentakab',f'{K}:maran:chenor',f'{K}:raub:dong',f'{K}:bentong:bentong']:
    check(f'{sid.split(":")[-2]}:{sid.split(":")[-1]}-no-primaries', not [l for l in links if l['area_source_id']==sid and l['is_primary']=='true'])
# 26150 stays on Sungai Karang; 26100 secondary stays on Beserah mukim
h = [l for l in links if l['postcode']=='26150' and l['is_primary']=='true']
check('26150-stays-sungai-karang', len(h)==1 and h[0]['area_source_id']==f'{K}:kuantan:sungai-karang', str(h))
s = [l for l in links if l['postcode']=='26100' and l['area_source_id']==f'{K}:kuantan:beserah']
check('26100-secondary-stays-beserah-mukim', len(s)==1 and s[0]['is_primary']=='false', str(s))
# district counts pin completion
for d, mbp in [('rompin',14),('raub',16),('cameron-highlands',7),('bera',6)]:
    n = sum(1 for r in rows if r['parent_source_id']==f'my:district:pahang:{d}' and r['type'] in ('mukim','bandar','pekan'))
    check(f'{d}-count-{mbp}', n==mbp, str(n))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

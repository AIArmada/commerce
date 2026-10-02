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
K = 'my:subdistrict:district:terengganu'
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', not multi, str(multi[:5]))
check('bukit-payong-removed', f'{K}:marang:bukit-payong' not in byid)
check('ayer-puteh-removed', f'{K}:kemaman:ayer-puteh' not in byid)
spots = [
 ('besut:pekan-kampung-raja','pekan','besut'),
 ('besut:pekan-kuala-besut','pekan','besut'),
 ('dungun:pekan-kuala-paka','pekan','dungun'),
 ('kemaman:mukim-cukai','mukim','kemaman'),
 ('kemaman:pekan-air-jernih','pekan','kemaman'),
 ('kemaman:pekan-air-putih','pekan','kemaman'),
 ('kemaman:pekan-kemasik','pekan','kemaman'),
 ('kemaman:pekan-kijal','pekan','kemaman'),
 ('kuala-terengganu:pekan-cabang-tiga','pekan','kuala-terengganu'),
 ('hulu-terengganu:pekan-kuala-berang','pekan','hulu-terengganu'),
 ('marang:pekan-bukit-payung','pekan','marang'),
 ('setiu:tasik','mukim','setiu'),
 ('kuala-nerus:pakoh','mukim','kuala-nerus'),
]
ok = True
for slug, t, parent in spots:
    r = byid.get(f'{K}:{slug}')
    if not r or r['type'] != t or r['parent_source_id'] != f'my:district:terengganu:{parent}' or r['level'] != '3':
        print('FAIL spot', slug, r); fails.append(f'spot {slug}'); ok = False
if ok: print(f'PASS all {len(spots)} add spots')
moves = [('22200',f'{K}:besut:pekan-kampung-raja'),
 ('22300',f'{K}:besut:pekan-kuala-besut'),('22307',f'{K}:besut:pekan-kuala-besut'),
 ('22309',f'{K}:besut:pekan-kuala-besut'),
 ('24200',f'{K}:kemaman:pekan-kemasik'),('24207',f'{K}:kemaman:pekan-kemasik'),
 ('24209',f'{K}:kemaman:pekan-kemasik'),('24210',f'{K}:kemaman:pekan-kemasik'),
 ('24220',f'{K}:kemaman:pekan-kemasik'),
 ('24100',f'{K}:kemaman:pekan-kijal'),('24107',f'{K}:kemaman:pekan-kijal'),
 ('24109',f'{K}:kemaman:pekan-kijal'),
 ('21700',f'{K}:hulu-terengganu:pekan-kuala-berang'),
 ('21400',f'{K}:marang:pekan-bukit-payung'),
 ('24050',f'{K}:kemaman:pekan-air-putih')]
for pc, sid in moves:
    hits = [l for l in links if l['postcode']==pc and l['is_primary']=='true']
    check(f'{pc}-primary-{sid.split(":")[-1]}', len(hits)==1 and hits[0]['area_source_id']==sid, str(hits))
stays = [('24000',f'{K}:kemaman:cukai'),('23100',f'{K}:dungun:paka'),
 ('23000',f'{K}:dungun:dungun'),('21600',f'{K}:marang:marang'),
 ('22100',f'{K}:setiu:permaisuri'),('21500',f'{K}:setiu:sungai-tong'),
 ('22000',f'{K}:besut:jertih')]
for pc, sid in stays:
    hits = [l for l in links if l['postcode']==pc and l['is_primary']=='true']
    check(f'{pc}-stays-{sid.split(":")[-1]}', len(hits)==1 and hits[0]['area_source_id']==sid, str(hits))
for pc, slug in [('22200','besut:kampung-raja'),('22300','besut:kuala-besut'),
 ('24200','kemaman:kemasik'),('24100','kemaman:kijal'),
 ('21700','hulu-terengganu:kuala-berang'),('21400','marang:bukit-payung'),
 ('24000','kemaman:mukim-cukai'),('24040','kemaman:mukim-cukai'),
 ('23100','dungun:pekan-kuala-paka'),('23100','dungun:kuala-paka')]:
    sid = f'{K}:{slug}'
    hits = [l for l in links if l['postcode']==pc and l['area_source_id']==sid]
    check(f'{pc}-secondary-{slug.split(":")[-1]}', len(hits)==1 and hits[0]['is_primary']=='false', str(hits))
for slug in ['kemaman:pekan-air-jernih','kuala-terengganu:pekan-cabang-tiga',
             'setiu:tasik','kuala-nerus:pakoh']:
    sid = f'{K}:{slug}'
    check(f'{slug}-linkless', not [l for l in links if l['area_source_id']==sid])
for d, mbp in [('besut',19),('dungun',13),('kemaman',17),('kuala-terengganu',21),
               ('hulu-terengganu',10),('marang',8),('setiu',7),('kuala-nerus',4)]:
    n = sum(1 for r in rows if r['parent_source_id']==f'my:district:terengganu:{d}' and r['type'] in ('mukim','bandar','pekan'))
    check(f'{d}-count-{mbp}', n==mbp, str(n))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

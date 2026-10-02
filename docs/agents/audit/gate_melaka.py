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
K = 'my:subdistrict:district:melaka'
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', not multi, str(multi[:5]))
check('retype alor-gajah:lubok-china=pekan', byid[f'{K}:alor-gajah:lubok-china']['type'] == 'pekan')
spots = [
 ('melaka-tengah:padang-semabok','mukim','melaka-tengah'),
 ('melaka-tengah:bandar-bukit-baru','bandar','melaka-tengah'),
 ('melaka-tengah:pekan-ayer-molek','pekan','melaka-tengah'),
 ('melaka-tengah:pekan-batu-berendam','pekan','melaka-tengah'),
 ('melaka-tengah:pekan-bukit-rambai','pekan','melaka-tengah'),
 ('melaka-tengah:pekan-kandang','pekan','melaka-tengah'),
 ('melaka-tengah:klebang','pekan','melaka-tengah'),
 ('melaka-tengah:pekan-paya-rumput','pekan','melaka-tengah'),
 ('melaka-tengah:pekan-sungai-udang','pekan','melaka-tengah'),
 ('melaka-tengah:pekan-tangga-batu','pekan','melaka-tengah'),
 ('melaka-tengah:pekan-tanjong-kling','pekan','melaka-tengah'),
 ('jasin:bandar-merlimau','bandar','jasin'),
 ('jasin:pekan-batang-malaka','pekan','jasin'),
 ('jasin:pekan-chin-chin','pekan','jasin'),
 ('jasin:kesang-pajak','pekan','jasin'),
 ('jasin:pekan-nyalas','pekan','jasin'),
 ('jasin:pekan-selandar','pekan','jasin'),
 ('jasin:sempang-bekoh','pekan','jasin'),
 ('jasin:pekan-sungai-rambai','pekan','jasin'),
 ('alor-gajah:bandar-masjid-tanah','bandar','alor-gajah'),
 ('alor-gajah:bandar-pulau-sebang','bandar','alor-gajah'),
 ('alor-gajah:pekan-durian-tunggal','pekan','alor-gajah'),
 ('alor-gajah:pekan-kuala-sungai-baru','pekan','alor-gajah'),
 ('alor-gajah:pekan-rembia','pekan','alor-gajah'),
]
ok = True
for slug, t, parent in spots:
    r = byid.get(f'{K}:{slug}')
    if not r or r['type'] != t or r['parent_source_id'] != f'my:district:melaka:{parent}' or r['level'] != '3':
        print('FAIL spot', slug, r); fails.append(f'spot {slug}'); ok = False
if ok: print(f'PASS all {len(spots)} add spots')
moves = [('76400',f'{K}:melaka-tengah:pekan-tanjong-kling'),('76409',f'{K}:melaka-tengah:pekan-tanjong-kling'),
 ('77300',f'{K}:jasin:bandar-merlimau'),('77309',f'{K}:jasin:bandar-merlimau'),
 ('77500',f'{K}:jasin:pekan-selandar'),
 ('78300',f'{K}:alor-gajah:bandar-masjid-tanah'),('78307',f'{K}:alor-gajah:bandar-masjid-tanah'),
 ('78309',f'{K}:alor-gajah:bandar-masjid-tanah'),
 ('76100',f'{K}:alor-gajah:pekan-durian-tunggal'),('76109',f'{K}:alor-gajah:pekan-durian-tunggal'),
 ('78200',f'{K}:alor-gajah:pekan-kuala-sungai-baru'),
 ('76300',f'{K}:melaka-tengah:pekan-sungai-udang'),
 ('77400',f'{K}:jasin:pekan-sungai-rambai'),('77409',f'{K}:jasin:pekan-sungai-rambai')]
for pc, sid in moves:
    hits = [l for l in links if l['postcode']==pc and l['is_primary']=='true']
    check(f'{pc}-primary-{sid.split(":")[-1]}', len(hits)==1 and hits[0]['area_source_id']==sid, str(hits))
h = [l for l in links if l['postcode']=='78100' and l['is_primary']=='true']
check('78100-stays-lubok-china', len(h)==1 and h[0]['area_source_id']==f'{K}:alor-gajah:lubok-china', str(h))
for slug in ['melaka-tengah:tanjong-kling','jasin:merlimau','jasin:selandar','alor-gajah:masjid-tanah',
             'alor-gajah:durian-tunggal','alor-gajah:kuala-sungai-baru','melaka-tengah:sungai-udang',
             'jasin:sungai-rambai']:
    sid = f'{K}:{slug}'
    check(f'{slug}-no-primaries', not [l for l in links if l['area_source_id']==sid and l['is_primary']=='true'])
for pc, slug in [('76400','melaka-tengah:tanjong-kling'),('77300','jasin:merlimau'),('77500','jasin:selandar'),
                 ('78300','alor-gajah:masjid-tanah'),('76100','alor-gajah:durian-tunggal'),
                 ('78200','alor-gajah:kuala-sungai-baru'),('76300','melaka-tengah:sungai-udang'),
                 ('77400','jasin:sungai-rambai'),
                 ('75050','melaka-tengah:padang-semabok'),('75150','melaka-tengah:bandar-bukit-baru'),
                 ('75200','melaka-tengah:klebang'),('75260','melaka-tengah:pekan-bukit-rambai'),
                 ('75350','melaka-tengah:pekan-batu-berendam'),('75460','melaka-tengah:pekan-ayer-molek'),
                 ('75460','melaka-tengah:pekan-kandang'),('76450','melaka-tengah:pekan-paya-rumput'),
                 ('76400','melaka-tengah:pekan-tangga-batu'),('77000','jasin:kesang-pajak'),
                 ('77000','jasin:pekan-chin-chin'),('77100','jasin:pekan-nyalas'),
                 ('78000','alor-gajah:pekan-rembia'),('73000','alor-gajah:bandar-pulau-sebang')]:
    sid = f'{K}:{slug}'
    hits = [l for l in links if l['postcode']==pc and l['area_source_id']==sid]
    check(f'{pc}-secondary-{slug.split(":")[-1]}', len(hits)==1 and hits[0]['is_primary']=='false', str(hits))
for slug in ['jasin:pekan-batang-malaka','jasin:sempang-bekoh']:
    sid = f'{K}:{slug}'
    check(f'{slug}-linkless', not [l for l in links if l['area_source_id']==sid])
for d, mbp in [('melaka-tengah',40),('jasin',31),('alor-gajah',38)]:
    n = sum(1 for r in rows if r['parent_source_id']==f'my:district:melaka:{d}' and r['type'] in ('mukim','bandar','pekan'))
    check(f'{d}-count-{mbp}', n==mbp, str(n))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

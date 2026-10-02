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
K = 'my:subdistrict:district:kedah'
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', not multi, str(multi[:5]))
# retypes (4 mistyped town mukims + Tikam Batu locality conversion)
for slug in ['kubang-pasu:bandar-jitra','kuala-muda:bandar-sungai-petani','baling:bandar-baling',
             'kulim:bandar-kulim','kuala-muda:tikam-batu']:
    check(f'retype {slug}=bandar', byid[f'{K}:{slug}']['type'] == 'bandar')
# removals (consolidated dups; zero-link Pekan Sik)
for slug in ['kota-setar:bandar-alor-setar','pendang:bandar-pendang','pokok-sena:pekan-pokok-sena',
             'sik:pekan-sik']:
    check(f'removed {slug}', f'{K}:{slug}' not in byid)
# adds spot checks (type + parent + level)
spots = [
 ('kota-setar:bandar-anak-bukit','bandar','kota-setar'),
 ('kota-setar:alor-merah','bandar','kota-setar'),
 ('kota-setar:bukit-pinang','bandar','kota-setar'),
 ('kota-setar:bandar-langgar','bandar','kota-setar'),
 ('kota-setar:alor-janggus','pekan','kota-setar'),
 ('kota-setar:pekan-gunung','pekan','kota-setar'),
 ('kubang-pasu:bandar-tunjang','bandar','kubang-pasu'),
 ('kubang-pasu:padang-sera','bandar','kubang-pasu'),
 ('kubang-pasu:kuala-sanglang','pekan','kubang-pasu'),
 ('kubang-pasu:pekan-sanglang','pekan','kubang-pasu'),
 ('kubang-pasu:kerpan','pekan','kubang-pasu'),
 ('kubang-pasu:sintok','pekan','kubang-pasu'),
 ('kubang-pasu:napoh','pekan','kubang-pasu'),
 ('kubang-pasu:sungai-korok','pekan','kubang-pasu'),
 ('padang-terap:naka','pekan','padang-terap'),
 ('padang-terap:durian-burung','pekan','padang-terap'),
 ('padang-terap:lubok-merbau','pekan','padang-terap'),
 ('padang-terap:bukit-tembaga','pekan','padang-terap'),
 ('padang-terap:padang-sanai','pekan','padang-terap'),
 ('padang-terap:kampung-tanjung','pekan','padang-terap'),
 ('langkawi:bandar-kuah','bandar','langkawi'),
 ('langkawi:bandar-padang-mat-sirat','bandar','langkawi'),
 ('langkawi:padang-lalang','bandar','langkawi'),
 ('langkawi:telok-datai','pekan','langkawi'),
 ('kuala-muda:teloi-kiri','mukim','kuala-muda'),
 ('kuala-muda:bandar-gurun','bandar','kuala-muda'),
 ('kuala-muda:sungai-lalang','bandar','kuala-muda'),
 ('kuala-muda:bandar-merbok','bandar','kuala-muda'),
 ('kuala-muda:bandar-semeling','bandar','kuala-muda'),
 ('kuala-muda:bandar-aman-jaya','bandar','kuala-muda'),
 ('kuala-muda:bukit-selambau','pekan','kuala-muda'),
 ('kuala-muda:tanjung-dawai','pekan','kuala-muda'),
 ('yan:bandar-yan','bandar','yan'),
 ('yan:simpang-tiga-sungai-limau','pekan','yan'),
 ('yan:sungai-limau','pekan','yan'),
 ('yan:teroi','pekan','yan'),
 ('yan:pekan-singkir','pekan','yan'),
 ('sik:bandar-sik','bandar','sik'),
 ('sik:batu-lima-sik','pekan','sik'),
 ('sik:gulau','pekan','sik'),
 ('sik:gajah-puteh','pekan','sik'),
 ('sik:charok-padang','pekan','sik'),
 ('baling:bandar-kupang','bandar','baling'),
 ('baling:kampung-baru-kejai','pekan','baling'),
 ('baling:pekan-pulai','pekan','baling'),
 ('baling:pekan-tawar','pekan','baling'),
 ('baling:parit-panjang','pekan','baling'),
 ('baling:kampung-lalang','pekan','baling'),
 ('baling:malau','pekan','baling'),
 ('kulim:pekan-junjong','pekan','kulim'),
 ('kulim:pekan-karangan','pekan','kulim'),
 ('kulim:labu-besar','pekan','kulim'),
 ('kulim:pekan-mahang','pekan','kulim'),
 ('kulim:merbau-pulas','pekan','kulim'),
 ('kulim:sungai-karangan','pekan','kulim'),
 ('kulim:sungai-kob','pekan','kulim'),
 ('kulim:pekan-padang-meha','pekan','kulim'),
 ('bandar-baharu:bandar-serdang','bandar','bandar-baharu'),
 ('bandar-baharu:lubuk-buntar','pekan','bandar-baharu'),
 ('bandar-baharu:selama','pekan','bandar-baharu'),
 ('bandar-baharu:sungai-kechil-ilir','pekan','bandar-baharu'),
 ('bandar-baharu:pekan-relau','pekan','bandar-baharu'),
 ('pendang:bukit-raya','mukim','pendang'),
 ('pendang:bukit-jenun','pekan','pendang'),
 ('pendang:kubur-panjang','pekan','pendang'),
 ('pendang:tanah-merah','pekan','pendang'),
 ('pendang:tokai','pekan','pendang'),
 ('pendang:kobah','pekan','pendang'),
 ('pendang:kampung-baru','pekan','pendang'),
 ('pendang:sungai-tiang','pekan','pendang'),
 ('pokok-sena:kebun-500','pekan','pokok-sena'),
]
ok = True
for slug, t, parent in spots:
    r = byid.get(f'{K}:{slug}')
    if not r or r['type'] != t or r['parent_source_id'] != f'my:district:kedah:{parent}' or r['level'] != '3':
        print('FAIL spot', slug, r); fails.append(f'spot {slug}'); ok = False
if ok: print(f'PASS all {len(spots)} add spots')
# town-code primaries moved
moves = [('07000',f'{K}:langkawi:bandar-kuah'),('07007',f'{K}:langkawi:bandar-kuah'),
 ('07009',f'{K}:langkawi:bandar-kuah'),('07100',f'{K}:langkawi:bandar-kuah'),
 ('06500',f'{K}:kota-setar:bandar-langgar'),('06507',f'{K}:kota-setar:bandar-langgar'),
 ('08300',f'{K}:kuala-muda:bandar-gurun'),('08330',f'{K}:kuala-muda:bandar-gurun'),
 ('08800',f'{K}:kuala-muda:bandar-gurun'),('08400',f'{K}:kuala-muda:bandar-merbok'),
 ('08407',f'{K}:kuala-muda:bandar-merbok'),('08409',f'{K}:kuala-muda:bandar-merbok'),
 ('06900',f'{K}:yan:bandar-yan'),('06910',f'{K}:yan:bandar-yan'),
 ('08200',f'{K}:sik:bandar-sik'),('08210',f'{K}:sik:bandar-sik'),('08340',f'{K}:sik:bandar-sik'),
 ('09200',f'{K}:baling:bandar-kupang'),('09700',f'{K}:kulim:pekan-karangan'),
 ('09800',f'{K}:bandar-baharu:bandar-serdang'),('09810',f'{K}:bandar-baharu:bandar-serdang')]
for pc, sid in moves:
    hits = [l for l in links if l['postcode']==pc and l['is_primary']=='true']
    check(f'{pc}-primary-{sid.split(":")[-1]}', len(hits)==1 and hits[0]['area_source_id']==sid, str(hits))
# consolidation samples (full move verified by no-dangling + counts)
for pc, slug in [('05000','kota-setar:alor-setar'),('05500','kota-setar:alor-setar'),
                 ('06700','pendang:pendang'),('06750','pendang:pendang'),
                 ('06350','pokok-sena:pokok-sena'),('06400','pokok-sena:pokok-sena'),
                 ('06000','kubang-pasu:bandar-jitra'),('08000','kuala-muda:bandar-sungai-petani'),
                 ('09000','kulim:bandar-kulim'),('09100','baling:bandar-baling')]:
    sid = f'{K}:{slug}'
    hits = [l for l in links if l['postcode']==pc and l['is_primary']=='true']
    check(f'{pc}-primary-{slug.split(":")[-1]}', len(hits)==1 and hits[0]['area_source_id']==sid, str(hits))
# Tikam Batu conversion keeps its links: 08700 primary stays Jeniang, secondary stays Tikam Batu
h = [l for l in links if l['postcode']=='08700' and l['is_primary']=='true']
check('08700-primary-jeniang', len(h)==1 and h[0]['area_source_id']==f'{K}:kuala-muda:jeniang', str(h))
s = [l for l in links if l['postcode']=='08700' and l['area_source_id']==f'{K}:kuala-muda:tikam-batu']
check('08700-secondary-tikam-batu', len(s)==1 and s[0]['is_primary']=='false', str(s))
# old holders carry no primaries now
for slug in ['langkawi:kuah','kota-setar:langgar','kuala-muda:gurun','kuala-muda:merbok','yan:yan',
             'sik:sik','baling:kupang','kulim:karangan','bandar-baharu:serdang']:
    sid = f'{K}:{slug}'
    check(f'{slug}-no-primaries', not [l for l in links if l['area_source_id']==sid and l['is_primary']=='true'])
# covering-mukim secondaries on the moved main codes
for pc, slug in [('07000','langkawi:kuah'),('06500','kota-setar:langgar'),('08300','kuala-muda:gurun'),
                 ('08400','kuala-muda:merbok'),('06900','yan:yan'),('08200','sik:sik'),
                 ('09200','baling:kupang'),('09700','kulim:karangan'),('09800','bandar-baharu:serdang')]:
    sid = f'{K}:{slug}'
    hits = [l for l in links if l['postcode']==pc and l['area_source_id']==sid]
    check(f'{pc}-secondary-{slug.split(":")[-1]}', len(hits)==1 and hits[0]['is_primary']=='false', str(hits))
# shared-code town secondaries (spot-check the evidence-backed set)
for pc, slug in [('05250','kota-setar:alor-merah'),('06200','kota-setar:bukit-pinang'),
                 ('06250','kota-setar:alor-janggus'),('06550','kota-setar:bandar-anak-bukit'),
                 ('06650','pendang:tokai'),('06660','pendang:tokai'),
                 ('06300','padang-terap:bukit-tembaga'),('06300','padang-terap:padang-sanai'),
                 ('06300','padang-terap:durian-burung'),('06350','padang-terap:naka'),
                 ('06400','padang-terap:naka'),('06400','pokok-sena:kebun-500'),
                 ('06700','pendang:tanah-merah'),('06750','pendang:sungai-tiang'),('06800','pendang:kobah'),
                 ('06100','kubang-pasu:padang-sera'),('06100','kubang-pasu:sintok'),
                 ('06100','kubang-pasu:pekan-sanglang'),('06150','kubang-pasu:kuala-sanglang'),
                 ('06150','kubang-pasu:kerpan'),('06150','kubang-pasu:sungai-korok'),
                 ('06000','kubang-pasu:bandar-tunjang'),('06000','kubang-pasu:napoh'),
                 ('08000','kuala-muda:sungai-lalang'),('08000','kuala-muda:bandar-aman-jaya'),
                 ('08010','kuala-muda:bukit-selambau'),('08600','kuala-muda:tikam-batu'),
                 ('09300','kulim:merbau-pulas'),('09200','baling:parit-panjang'),
                 ('09700','kulim:pekan-mahang'),('09700','kulim:sungai-kob'),
                 ('09800','bandar-baharu:lubuk-buntar'),('08800','yan:guar-cempedak'),
                 ('08100','kuala-muda:bandar-semeling'),('08110','yan:pekan-singkir'),
                 ('08400','yan:pekan-singkir'),('08200','sik:charok-padang'),('08210','sik:gulau'),
                 ('08400','kuala-muda:tanjung-dawai'),('08110','kuala-muda:tanjung-dawai'),
                 ('06710','padang-terap:lubok-merbau'),('14390','bandar-baharu:sungai-kechil-ilir'),
                 ('09310','baling:pekan-tawar')]:
    sid = f'{K}:{slug}'
    hits = [l for l in links if l['postcode']==pc and l['area_source_id']==sid]
    check(f'{pc}-secondary-{slug.split(":")[-1]}', len(hits)==1 and hits[0]['is_primary']=='false', str(hits))
# district counts pin completion (Yan 11: UPI counts the Guar Cempedak/Chempedak variant twice)
for d, mbp in [('kota-setar',29),('kubang-pasu',36),('padang-terap',18),('langkawi',10),
               ('kuala-muda',28),('yan',11),('sik',8),('baling',18),('kulim',24),
               ('bandar-baharu',13),('pendang',16),('pokok-sena',8)]:
    n = sum(1 for r in rows if r['parent_source_id']==f'my:district:kedah:{d}' and r['type'] in ('mukim','bandar','pekan'))
    check(f'{d}-count-{mbp}', n==mbp, str(n))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

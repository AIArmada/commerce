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
K = 'my:subdistrict:district:negeri-sembilan'
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', not multi, str(multi[:5]))
check('retype rembau:chengkau=mukim', byid[f'{K}:rembau:chengkau']['type'] == 'mukim')
check('retype seremban:bandar-seremban=bandar', byid[f'{K}:seremban:bandar-seremban']['type'] == 'bandar')
check('retype jempol:bandar-seri-jempol=bandar', byid[f'{K}:jempol:bandar-seri-jempol']['type'] == 'bandar')
spots = [
 ('jelebu:bandar-kuala-klawang','bandar','jelebu'),
 ('jelebu:pekan-kuala-klawang','pekan','jelebu'),
 ('jelebu:pekan-pertang','pekan','jelebu'),
 ('jelebu:titi','pekan','jelebu'),
 ('jelebu:petaling','pekan','jelebu'),
 ('jelebu:sungai-muntoh','pekan','jelebu'),
 ('kuala-pilah:pekan-johol','pekan','kuala-pilah'),
 ('kuala-pilah:pekan-parit-tinggi','pekan','kuala-pilah'),
 ('kuala-pilah:pekan-juasseh','pekan','kuala-pilah'),
 ('kuala-pilah:dangi','pekan','kuala-pilah'),
 ('kuala-pilah:gunung-pasir','pekan','kuala-pilah'),
 ('kuala-pilah:senaling','pekan','kuala-pilah'),
 ('kuala-pilah:bukit-gelugor','pekan','kuala-pilah'),
 ('kuala-pilah:melang','pekan','kuala-pilah'),
 ('kuala-pilah:air-mawang','pekan','kuala-pilah'),
 ('kuala-pilah:dangi-baru','pekan','kuala-pilah'),
 ('port-dickson:bandar-port-dickson','bandar','port-dickson'),
 ('port-dickson:pekan-port-dickson','pekan','port-dickson'),
 ('port-dickson:teluk-kemang','bandar','port-dickson'),
 ('port-dickson:pekan-teluk-kemang','pekan','port-dickson'),
 ('port-dickson:pekan-pasir-panjang','pekan','port-dickson'),
 ('port-dickson:pengkalan-kempas','pekan','port-dickson'),
 ('port-dickson:chuah','pekan','port-dickson'),
 ('port-dickson:pekan-linggi','pekan','port-dickson'),
 ('port-dickson:bukit-pelanduk','pekan','port-dickson'),
 ('port-dickson:air-kuning','pekan','port-dickson'),
 ('port-dickson:sungai-menyala','pekan','port-dickson'),
 ('port-dickson:bagan-pinang','pekan','port-dickson'),
 ('port-dickson:tanah-merah-utara','pekan','port-dickson'),
 ('port-dickson:tanah-merah-selatan','pekan','port-dickson'),
 ('port-dickson:jemima','pekan','port-dickson'),
 ('rembau:pekan-chengkau','pekan','rembau'),
 ('rembau:pekan-pedas','pekan','rembau'),
 ('rembau:pekan-chembong','pekan','rembau'),
 ('rembau:pekan-rembau','pekan','rembau'),
 ('rembau:kampong-batu','pekan','rembau'),
 ('rembau:lubok-china','pekan','rembau'),
 ('rembau:seri-kota','pekan','rembau'),
 ('rembau:seri-kendong','pekan','rembau'),
 ('rembau:merbau-sembilan','pekan','rembau'),
 ('seremban:bandar-seremban-utama','bandar','seremban'),
 ('seremban:bandar-mantin-utama','bandar','seremban'),
 ('seremban:bandar-baru-kota-sri-mas','bandar','seremban'),
 ('seremban:bandar-nilai-utama','bandar','seremban'),
 ('seremban:bandar-sri-sendayan','bandar','seremban'),
 ('seremban:pekan-labu','pekan','seremban'),
 ('seremban:pekan-lenggeng','pekan','seremban'),
 ('seremban:pekan-rantau','pekan','seremban'),
 ('seremban:pekan-setul','pekan','seremban'),
 ('seremban:broga','pekan','seremban'),
 ('seremban:ulu-beranang','pekan','seremban'),
 ('seremban:mambau','pekan','seremban'),
 ('seremban:pajam','pekan','seremban'),
 ('seremban:tiroi','pekan','seremban'),
 ('seremban:pancor','pekan','seremban'),
 ('seremban:taman-seremban','pekan','seremban'),
 ('seremban:rahang-baru','pekan','seremban'),
 ('seremban:paroi','pekan','seremban'),
 ('seremban:bukit-kepayang','pekan','seremban'),
 ('seremban:dusun-setia','pekan','seremban'),
 ('seremban:sungai-gadut','pekan','seremban'),
 ('seremban:bukti','pekan','seremban'),
 ('seremban:sikamat','pekan','seremban'),
 ('seremban:shah-bandar','pekan','seremban'),
 ('seremban:ulu-temiang','pekan','seremban'),
 ('seremban:paroi-jaya','pekan','seremban'),
 ('seremban:rasah-jaya','pekan','seremban'),
 ('seremban:seremban-jaya','pekan','seremban'),
 ('tampin:bandar-gemas','bandar','tampin'),
 ('tampin:pekan-tampin-tengah','pekan','tampin'),
 ('tampin:pekan-air-kuning','pekan','tampin'),
 ('tampin:pekan-repah','pekan','tampin'),
 ('tampin:air-kuning-selatan','pekan','tampin'),
 ('tampin:batang-melaka','pekan','tampin'),
 ('tampin:gemencheh-bahru','pekan','tampin'),
 ('tampin:pasir-besar','pekan','tampin'),
 ('tampin:repah-jaya','pekan','tampin'),
 ('tampin:repah-permai','pekan','tampin'),
 ('jempol:pekan-bahau','pekan','jempol'),
 ('jempol:pekan-rompin','pekan','jempol'),
 ('jempol:kuala-jelai','pekan','jempol'),
 ('jempol:ladang-geddes','pekan','jempol'),
 ('jempol:mahsan','pekan','jempol'),
 ('jempol:serting-tengah','pekan','jempol'),
 ('jempol:serting','bandar','jempol'),
]
ok = True
for slug, t, parent in spots:
    r = byid.get(f'{K}:{slug}')
    if not r or r['type'] != t or r['parent_source_id'] != f'my:district:negeri-sembilan:{parent}' or r['level'] != '3':
        print('FAIL spot', slug, r); fails.append(f'spot {slug}'); ok = False
if ok: print(f'PASS all {len(spots)} add spots')
moves = [('71600',f'{K}:jelebu:bandar-kuala-klawang'),('71609',f'{K}:jelebu:bandar-kuala-klawang'),
 ('71650',f'{K}:jelebu:bandar-kuala-klawang'),('71659',f'{K}:jelebu:bandar-kuala-klawang'),
 ('73100',f'{K}:kuala-pilah:pekan-johol'),('73109',f'{K}:kuala-pilah:pekan-johol'),
 ('71000',f'{K}:port-dickson:bandar-port-dickson'),('71007',f'{K}:port-dickson:bandar-port-dickson'),
 ('71009',f'{K}:port-dickson:bandar-port-dickson'),('71010',f'{K}:port-dickson:bandar-port-dickson'),
 ('71960',f'{K}:port-dickson:bandar-port-dickson'),('71999',f'{K}:port-dickson:bandar-port-dickson'),
 ('71150',f'{K}:port-dickson:pekan-linggi'),('71159',f'{K}:port-dickson:pekan-linggi'),
 ('71900',f'{K}:seremban:pekan-labu'),('71907',f'{K}:seremban:pekan-labu'),('71909',f'{K}:seremban:pekan-labu'),
 ('71100',f'{K}:seremban:pekan-rantau'),('71109',f'{K}:seremban:pekan-rantau'),
 ('71200',f'{K}:seremban:pekan-rantau'),('71209',f'{K}:seremban:pekan-rantau'),
 ('73400',f'{K}:tampin:bandar-gemas'),('73409',f'{K}:tampin:bandar-gemas'),('73410',f'{K}:tampin:bandar-gemas'),
 ('73420',f'{K}:tampin:bandar-gemas'),('73480',f'{K}:tampin:bandar-gemas'),
 ('73500',f'{K}:jempol:pekan-rompin'),('73507',f'{K}:jempol:pekan-rompin'),('73509',f'{K}:jempol:pekan-rompin')]
for pc, sid in moves:
    hits = [l for l in links if l['postcode']==pc and l['is_primary']=='true']
    check(f'{pc}-primary-{sid.split(":")[-1]}', len(hits)==1 and hits[0]['area_source_id']==sid, str(hits))
# retyped town rows keep their codes
for pc, slug in [('71300','rembau:rembau'),('72100','jempol:bahau'),('72120','jempol:bandar-seri-jempol'),
                 ('70000','seremban:bandar-seremban')]:
    sid = f'{K}:{slug}'
    hits = [l for l in links if l['postcode']==pc and l['is_primary']=='true']
    check(f'{pc}-stays-{slug.split(":")[-1]}', len(hits)==1 and hits[0]['area_source_id']==sid, str(hits))
for slug in ['jelebu:kuala-klawang','kuala-pilah:johol','port-dickson:port-dickson','port-dickson:linggi',
             'seremban:labu','seremban:rantau','tampin:gemas','jempol:rompin']:
    sid = f'{K}:{slug}'
    check(f'{slug}-no-primaries', not [l for l in links if l['area_source_id']==sid and l['is_primary']=='true'])
for pc, slug in [('71600','jelebu:kuala-klawang'),('73100','kuala-pilah:johol'),
                 ('71000','port-dickson:port-dickson'),('71150','port-dickson:linggi'),
                 ('71900','seremban:labu'),('71100','seremban:rantau'),
                 ('73400','tampin:gemas'),('73500','jempol:rompin')]:
    sid = f'{K}:{slug}'
    hits = [l for l in links if l['postcode']==pc and l['area_source_id']==sid]
    check(f'{pc}-secondary-{slug.split(":")[-1]}', len(hits)==1 and hits[0]['is_primary']=='false', str(hits))
# shared-code town secondaries (generated from n9_data.py ADD_LINKS)
for pc, slug in [
 ('71650','jelebu:titi'),
 ('71659','jelebu:titi'),
 ('71650','jelebu:sungai-muntoh'),
 ('71600','jelebu:petaling'),
 ('73100','kuala-pilah:dangi'),
 ('73100','kuala-pilah:air-mawang'),
 ('72000','kuala-pilah:senaling'),
 ('72000','kuala-pilah:melang'),
 ('72000','kuala-pilah:pekan-parit-tinggi'),
 ('72000','kuala-pilah:pekan-juasseh'),
 ('72500','kuala-pilah:pekan-juasseh'),
 ('71550','kuala-pilah:gunung-pasir'),
 ('72200','kuala-pilah:bukit-gelugor'),
 ('71050','port-dickson:teluk-kemang'),
 ('71050','port-dickson:sungai-menyala'),
 ('71150','port-dickson:pengkalan-kempas'),
 ('71960','port-dickson:chuah'),
 ('71000','port-dickson:bagan-pinang'),
 ('71100','port-dickson:jemima'),
 ('71250','port-dickson:pekan-pasir-panjang'),
 ('71400','rembau:pekan-pedas'),
 ('71409','rembau:pekan-pedas'),
 ('71300','rembau:pekan-chembong'),
 ('71400','rembau:pekan-chembong'),
 ('71300','rembau:pekan-chengkau'),
 ('71350','rembau:pekan-chengkau'),
 ('71150','rembau:lubok-china'),
 ('71350','rembau:seri-kendong'),
 ('71400','rembau:merbau-sembilan'),
 ('71800','seremban:bandar-baru-kota-sri-mas'),
 ('71800','seremban:bandar-nilai-utama'),
 ('71950','seremban:bandar-sri-sendayan'),
 ('71750','seremban:pekan-lenggeng'),
 ('71700','seremban:pekan-setul'),
 ('71750','seremban:broga'),
 ('71750','seremban:ulu-beranang'),
 ('70300','seremban:mambau'),
 ('71700','seremban:pajam'),
 ('71900','seremban:tiroi'),
 ('70400','seremban:paroi'),
 ('70200','seremban:bukit-kepayang'),
 ('70100','seremban:dusun-setia'),
 ('71450','seremban:sungai-gadut'),
 ('70400','seremban:bukti'),
 ('70400','seremban:sikamat'),
 ('70400','seremban:shah-bandar'),
 ('70200','seremban:ulu-temiang'),
 ('70400','seremban:paroi-jaya'),
 ('70300','seremban:rasah-jaya'),
 ('70450','seremban:seremban-jaya'),
 ('73000','tampin:pekan-tampin-tengah'),
 ('73200','tampin:pekan-air-kuning'),
 ('73000','tampin:pekan-repah'),
 ('73300','tampin:batang-melaka'),
 ('73200','tampin:gemencheh-bahru'),
 ('73400','tampin:pasir-besar'),
 ('72100','jempol:kuala-jelai'),
 ('72120','jempol:ladang-geddes'),
 ('72100','jempol:mahsan'),
 ('72200','jempol:serting-tengah'),
 ('72120','jempol:serting'),
]:
    sid = f'{K}:{slug}'
    hits = [l for l in links if l['postcode']==pc and l['area_source_id']==sid]
    check(f'{pc}-secondary-{slug.split(":")[-1]}', len(hits)==1 and hits[0]['is_primary']=='false', str(hits))
# linkless new rows (no code evidence, or codes stay on the higher-tier town row)
for slug in ['jelebu:pekan-kuala-klawang','jelebu:pekan-pertang','kuala-pilah:dangi-baru',
             'port-dickson:pekan-port-dickson','port-dickson:pekan-teluk-kemang',
             'port-dickson:bukit-pelanduk','port-dickson:air-kuning',
             'port-dickson:tanah-merah-utara','port-dickson:tanah-merah-selatan',
             'rembau:kampong-batu','rembau:seri-kota','rembau:pekan-rembau',
             'seremban:bandar-seremban-utama','seremban:bandar-mantin-utama','seremban:pancor',
             'seremban:taman-seremban','seremban:rahang-baru','tampin:air-kuning-selatan',
             'tampin:repah-jaya','tampin:repah-permai','jempol:pekan-bahau']:
    sid = f'{K}:{slug}'
    check(f'{slug}-linkless', not [l for l in links if l['area_source_id']==sid])
# district counts pin completion (Seremban 41: UPI lists Bandar Seremban twice)
for d, mbp in [('jelebu',16),('kuala-pilah',23),('port-dickson',21),('rembau',28),('seremban',41),
               ('tampin',18),('jempol',15)]:
    n = sum(1 for r in rows if r['parent_source_id']==f'my:district:negeri-sembilan:{d}' and r['type'] in ('mukim','bandar','pekan'))
    check(f'{d}-count-{mbp}', n==mbp, str(n))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

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
K = 'my:subdistrict:district:perak'
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', not multi, str(multi[:5]))
for slug in ['kerian:simpang-lima','larut-matang:changkat-jering','selama:rantau-panjang']:
    check(f'retype {slug}=pekan', byid[f'{K}:{slug}']['type'] == 'pekan')
spots = [
 ('batang-padang:bandar-bidor','bandar','batang-padang'),
 ('batang-padang:bandar-chenderiang','bandar','batang-padang'),
 ('batang-padang:bandar-sungkai','bandar','batang-padang'),
 ('batang-padang:ayer-kuning','pekan','batang-padang'),
 ('batang-padang:banir','pekan','batang-padang'),
 ('batang-padang:bikam','pekan','batang-padang'),
 ('batang-padang:sungai-lesong','pekan','batang-padang'),
 ('batang-padang:temoh-station','pekan','batang-padang'),
 ('manjung:bandar-lumut','bandar','manjung'),
 ('manjung:pekan-beruas','pekan','manjung'),
 ('manjung:pekan-pengkalan-baharu','pekan','manjung'),
 ('manjung:pekan-sitiawan','pekan','manjung'),
 ('manjung:damar-laut','pekan','manjung'),
 ('manjung:kampong-baharu','pekan','manjung'),
 ('manjung:kampong-koh','pekan','manjung'),
 ('manjung:kampong-sitiawan','pekan','manjung'),
 ('manjung:pasir-bogak','pekan','manjung'),
 ('manjung:gurney','pekan','manjung'),
 ('manjung:segari','pekan','manjung'),
 ('manjung:sungai-pinang-kechil','pekan','manjung'),
 ('kinta:tronoh','mukim','kinta'),
 ('kinta:bandar-tronoh','bandar','kinta'),
 ('kinta:bandar-sungai-raya','bandar','kinta'),
 ('kinta:jelapang','bandar','kinta'),
 ('kinta:menglembu','bandar','kinta'),
 ('kinta:papan','bandar','kinta'),
 ('kinta:seputeh','bandar','kinta'),
 ('kinta:kanthan','pekan','kinta'),
 ('kinta:simpang-pulai','pekan','kinta'),
 ('kinta:pekan-tanjong-tualang','pekan','kinta'),
 ('kerian:bandar-bagan-serai','bandar','kerian'),
 ('kerian:bandar-kuala-kurau','bandar','kerian'),
 ('kerian:bandar-parit-buntar','bandar','kerian'),
 ('kerian:bukit-merah','pekan','kerian'),
 ('kerian:jalan-baru','pekan','kerian'),
 ('kerian:sungai-gedong','pekan','kerian'),
 ('kerian:pekan-tanjong-piandang','pekan','kerian'),
 ('kuala-kangsar:bandar-sungai-siput','bandar','kuala-kangsar'),
 ('kuala-kangsar:pekan-lubok-merbau','pekan','kuala-kangsar'),
 ('kuala-kangsar:gunong-pondok','pekan','kuala-kangsar'),
 ('kuala-kangsar:jerlun','pekan','kuala-kangsar'),
 ('kuala-kangsar:karai','pekan','kuala-kangsar'),
 ('kuala-kangsar:kati','pekan','kuala-kangsar'),
 ('kuala-kangsar:salak','pekan','kuala-kangsar'),
 ('larut-matang:bandar-kamunting','bandar','larut-matang'),
 ('larut-matang:pekan-batu-kurau','pekan','larut-matang'),
 ('larut-matang:pekan-simpang','pekan','larut-matang'),
 ('larut-matang:pekan-terung','pekan','larut-matang'),
 ('larut-matang:pondok-tanjong','pekan','larut-matang'),
 ('hilir-perak:batak-rabit','pekan','hilir-perak'),
 ('hilir-perak:degong','pekan','hilir-perak'),
 ('hulu-perak:temelong','mukim','hulu-perak'),
 ('hulu-perak:temengor','mukim','hulu-perak'),
 ('hulu-perak:bandar-gerik','bandar','hulu-perak'),
 ('hulu-perak:bandar-pengkalan-hulu','bandar','hulu-perak'),
 ('hulu-perak:lawin','bandar','hulu-perak'),
 ('hulu-perak:bandar-lenggong','bandar','hulu-perak'),
 ('selama:bandar-selama','bandar','selama'),
 ('selama:sungai-bayur','pekan','selama'),
 ('perak-tengah:bota-kanan','pekan','perak-tengah'),
 ('perak-tengah:kampong-buloh-akar','pekan','perak-tengah'),
 ('perak-tengah:pekan-kota-setia','pekan','perak-tengah'),
 ('perak-tengah:tanjong-belanja','pekan','perak-tengah'),
 ('kampar:bandar-kampar','bandar','kampar'),
 ('kampar:kota-baharu','pekan','kampar'),
 ('muallim:proton','bandar','muallim'),
 ('muallim:pekan-slim','pekan','muallim'),
 ('bagan-datuk:pekan-bagan-datuk','pekan','bagan-datuk'),
 ('bagan-datuk:pekan-hutan-melintang','pekan','bagan-datuk'),
 ('bagan-datuk:pekan-sungai-sumun','pekan','bagan-datuk'),
 ('bagan-datuk:batu-dua-puloh','pekan','bagan-datuk'),
 ('bagan-datuk:jendarata','pekan','bagan-datuk'),
 ('bagan-datuk:kampong-sungai-haji-mohamed','pekan','bagan-datuk'),
 ('bagan-datuk:simpang-empat','pekan','bagan-datuk'),
 ('bagan-datuk:simpang-tiga','pekan','bagan-datuk'),
]
ok = True
for slug, t, parent in spots:
    r = byid.get(f'{K}:{slug}')
    if not r or r['type'] != t or r['parent_source_id'] != f'my:district:perak:{parent}' or r['level'] != '3':
        print('FAIL spot', slug, r); fails.append(f'spot {slug}'); ok = False
if ok: print(f'PASS all {len(spots)} add spots')
moves = [('35500',f'{K}:batang-padang:bandar-bidor'),('35300',f'{K}:batang-padang:bandar-chenderiang'),
 ('35600',f'{K}:batang-padang:bandar-sungkai'),
 ('32100',f'{K}:manjung:bandar-lumut'),('32200',f'{K}:manjung:bandar-lumut'),
 ('32700',f'{K}:manjung:pekan-beruas'),
 ('32000',f'{K}:manjung:pekan-sitiawan'),('32010',f'{K}:manjung:pekan-sitiawan'),
 ('32020',f'{K}:manjung:pekan-sitiawan'),('32050',f'{K}:manjung:pekan-sitiawan'),
 ('31800',f'{K}:kinta:pekan-tanjong-tualang'),
 ('34300',f'{K}:kerian:bandar-bagan-serai'),('34310',f'{K}:kerian:bandar-bagan-serai'),
 ('34350',f'{K}:kerian:bandar-kuala-kurau'),('34200',f'{K}:kerian:bandar-parit-buntar'),
 ('34250',f'{K}:kerian:pekan-tanjong-piandang'),
 ('31050',f'{K}:kuala-kangsar:bandar-sungai-siput'),('31100',f'{K}:kuala-kangsar:bandar-sungai-siput'),
 ('31120',f'{K}:kuala-kangsar:bandar-sungai-siput'),
 ('34600',f'{K}:larut-matang:bandar-kamunting'),
 ('34500',f'{K}:larut-matang:pekan-batu-kurau'),('34510',f'{K}:larut-matang:pekan-batu-kurau'),
 ('34520',f'{K}:larut-matang:pekan-batu-kurau'),('34700',f'{K}:larut-matang:pekan-simpang'),
 ('34800',f'{K}:larut-matang:pekan-terung'),
 ('33300',f'{K}:hulu-perak:bandar-gerik'),('33310',f'{K}:hulu-perak:bandar-gerik'),
 ('33320',f'{K}:hulu-perak:bandar-gerik'),('33100',f'{K}:hulu-perak:bandar-pengkalan-hulu'),
 ('33400',f'{K}:hulu-perak:bandar-lenggong'),('33410',f'{K}:hulu-perak:bandar-lenggong'),
 ('33420',f'{K}:hulu-perak:bandar-lenggong'),
 ('34100',f'{K}:selama:bandar-selama'),('34120',f'{K}:selama:bandar-selama'),
 ('34130',f'{K}:selama:bandar-selama'),
 ('31900',f'{K}:kampar:bandar-kampar'),('31907',f'{K}:kampar:bandar-kampar'),
 ('31909',f'{K}:kampar:bandar-kampar'),('31910',f'{K}:kampar:bandar-kampar'),
 ('31920',f'{K}:kampar:bandar-kampar'),('31950',f'{K}:kampar:bandar-kampar'),
 ('36100',f'{K}:bagan-datuk:pekan-bagan-datuk'),('36400',f'{K}:bagan-datuk:pekan-hutan-melintang'),
 ('36300',f'{K}:bagan-datuk:pekan-sungai-sumun'),('36307',f'{K}:bagan-datuk:pekan-sungai-sumun'),
 ('36309',f'{K}:bagan-datuk:pekan-sungai-sumun'),
 ('31750',f'{K}:kinta:bandar-tronoh')]
for pc, sid in moves:
    hits = [l for l in links if l['postcode']==pc and l['is_primary']=='true']
    check(f'{pc}-primary-{sid.split(":")[-1]}', len(hits)==1 and hits[0]['area_source_id']==sid, str(hits))
for slug in ['batang-padang:bidor','batang-padang:chenderiang','batang-padang:sungkai','manjung:lumut',
             'manjung:beruas','manjung:sitiawan','kinta:tanjong-tualang','kerian:bagan-serai',
             'kerian:kuala-kurau','kerian:parit-buntar','kerian:tanjong-piandang',
             'kuala-kangsar:sungai-siput','larut-matang:kamunting','larut-matang:batu-kurau',
             'larut-matang:simpang','hulu-perak:gerik','hulu-perak:pengkalan-hulu','hulu-perak:lenggong',
             'selama:selama','kampar:kampar','bagan-datuk:bagan-datuk','bagan-datuk:hutan-melintang',
             'bagan-datuk:sungai-sumun']:
    sid = f'{K}:{slug}'
    check(f'{slug}-no-primaries', not [l for l in links if l['area_source_id']==sid and l['is_primary']=='true'])
for pc, slug in [
 ('35500','batang-padang:bidor'),
 ('35300','batang-padang:chenderiang'),
 ('35600','batang-padang:sungkai'),
 ('32100','manjung:lumut'),
 ('32700','manjung:beruas'),
 ('32000','manjung:sitiawan'),
 ('31800','kinta:tanjong-tualang'),
 ('34300','kerian:bagan-serai'),
 ('34350','kerian:kuala-kurau'),
 ('34200','kerian:parit-buntar'),
 ('34250','kerian:tanjong-piandang'),
 ('31050','kuala-kangsar:sungai-siput'),
 ('34600','larut-matang:kamunting'),
 ('34500','larut-matang:batu-kurau'),
 ('34700','larut-matang:simpang'),
 ('33300','hulu-perak:gerik'),
 ('33100','hulu-perak:pengkalan-hulu'),
 ('33400','hulu-perak:lenggong'),
 ('34100','selama:selama'),
 ('31900','kampar:kampar'),
 ('36100','bagan-datuk:bagan-datuk'),
 ('36400','bagan-datuk:hutan-melintang'),
 ('36300','bagan-datuk:sungai-sumun'),
 ('31900','batang-padang:ayer-kuning'),
 ('35400','batang-padang:banir'),
 ('35600','batang-padang:bikam'),
 ('35350','batang-padang:temoh-station'),
 ('32200','manjung:damar-laut'),
 ('32200','manjung:segari'),
 ('32000','manjung:kampong-koh'),
 ('32000','manjung:kampong-sitiawan'),
 ('32000','manjung:gurney'),
 ('32300','manjung:pasir-bogak'),
 ('32300','manjung:sungai-pinang-kechil'),
 ('34400','kerian:bukit-merah'),
 ('34300','kerian:sungai-gedong'),
 ('33000','kuala-kangsar:jerlun'),
 ('33600','kuala-kangsar:karai'),
 ('33020','kuala-kangsar:kati'),
 ('31100','kuala-kangsar:salak'),
 ('36000','hilir-perak:batak-rabit'),
 ('36700','hilir-perak:degong'),
 ('33300','hulu-perak:lawin'),
 ('32600','perak-tengah:bota-kanan'),
 ('32800','perak-tengah:kampong-buloh-akar'),
 ('32800','perak-tengah:tanjong-belanja'),
 ('35950','muallim:proton'),
 ('36400','bagan-datuk:jendarata'),
 ('36400','bagan-datuk:simpang-empat'),
 ('36400','bagan-datuk:simpang-tiga'),
 ('35800','muallim:pekan-slim'),
 ('36810','perak-tengah:pekan-kota-setia'),
 ('33010','kuala-kangsar:pekan-lubok-merbau'),
 ('31600','kampar:kota-baharu'),
 ('31610','kampar:kota-baharu'),
]:
    sid = f'{K}:{slug}'
    hits = [l for l in links if l['postcode']==pc and l['area_source_id']==sid]
    check(f'{pc}-secondary-{slug.split(":")[-1]}', len(hits)==1 and hits[0]['is_primary']=='false', str(hits))
for slug in ['batang-padang:sungai-lesong','manjung:kampong-baharu','manjung:pekan-pengkalan-baharu',
             'kerian:jalan-baru','kuala-kangsar:gunong-pondok',
             'larut-matang:pondok-tanjong','selama:sungai-bayur','bagan-datuk:batu-dua-puloh',
             'bagan-datuk:kampong-sungai-haji-mohamed','kinta:jelapang','kinta:menglembu','kinta:papan',
             'kinta:seputeh','kinta:kanthan','kinta:simpang-pulai',
             'kinta:bandar-sungai-raya']:
    sid = f'{K}:{slug}'
    check(f'{slug}-linkless', not [l for l in links if l['area_source_id']==sid])
# Tronoh consolidation secondary
sid = f'{K}:kinta:tronoh'
hits = [l for l in links if l['postcode']=='31750' and l['area_source_id']==sid]
check('31750-secondary-tronoh', len(hits)==1 and hits[0]['is_primary']=='false', str(hits))
check('kampar-tronoh-removed', f'{K}:kampar:tronoh' not in byid)
check('trong-removed', f'{K}:larut-matang:trong' not in byid)
for d, mbp in [('batang-padang',15),('manjung',21),('kinta',22),('kerian',17),('kuala-kangsar',19),
               ('larut-matang',20),('hilir-perak',10),('hulu-perak',15),('selama',6),('perak-tengah',18),
               ('kampar',6),('muallim',8),('bagan-datuk',14)]:
    n = sum(1 for r in rows if r['parent_source_id']==f'my:district:perak:{d}' and r['type'] in ('mukim','bandar','pekan'))
    check(f'{d}-count-{mbp}', n==mbp, str(n))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

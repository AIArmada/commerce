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
K = 'my:subdistrict:district:kelantan'
# 1. no dangling links
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
# 2. exactly-one-primary per postcode (global)
from collections import Counter
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', not multi, str(multi[:5]))
# 3. removed row gone, no links reference it
check('bandar-pasir-mas-gone', f'{K}:pasir-mas:bandar-pasir-mas' not in byid)
check('no-links-to-removed', not [l for l in links if 'bandar-pasir-mas' in l['area_source_id']])
# 4. retypes
for slug, t in [('bachok:bandar-bachok','bandar'),('tumpat:bandar-tumpat','bandar'),
  ('pasir-puteh:bandar-pasir-puteh','bandar'),('kuala-krai:bandar-kuala-krai','bandar'),
  ('machang:bandar-machang','bandar'),('gua-musang:bandar-gua-musang','bandar'),
  ('tanah-merah:bandar-tanah-merah','bandar'),('tanah-merah:tanah-merah','mukim')]:
    check(f'retype {slug}={t}', byid[f'{K}:{slug}']['type'] == t)
# 5. Pasir Mas bandar holds 10 primaries, no secondaries
pm = [l for l in links if l['area_source_id'] == f'{K}:pasir-mas:pasir-mas']
check('pasir-mas-10-primaries', sum(1 for l in pm if l['is_primary']=='true') == 10, str(len(pm)))
check('pasir-mas-no-secondary', all(l['is_primary']=='true' for l in pm))
# 6. pekan primaries moved
for pc, sid in [('17200',f'{K}:pasir-mas:pekan-rantau-panjang'),('18400',f'{K}:machang:pekan-temangan'),
  ('16810',f'{K}:pasir-puteh:pekan-selising'),('16070',f'{K}:bachok:jelawat')]:
    hits = [l for l in links if l['postcode']==pc]
    check(f'{pc}-sole-primary-on-{sid.split(":")[-1]}', len(hits)==1 and hits[0]['area_source_id']==sid and hits[0]['is_primary']=='true', str(hits))
# 7. old mukims linkless now
for sid in [f'{K}:pasir-mas:rantau-panjang',f'{K}:machang:temangan',f'{K}:pasir-puteh:selising']:
    check(f'{sid.split(":")[-1]}-linkless', not [l for l in links if l['area_source_id']==sid])
# 8. counts
kb = [r for r in rows if r['parent_source_id']=='my:district:kelantan:kota-bharu' and r['type']=='mukim']
check('kb-89-mukims', len(kb)==89, str(len(kb)))
loj = [r for r in rows if r['parent_source_id']=='my:district:kelantan:lojing']
check('lojing-7-mukims', len(loj)==7, str(len(loj)))
# 9. Tanah Merah swap integrity: bandar 6 primaries, mukim 3 secondaries
btm = [l for l in links if l['area_source_id']==f'{K}:tanah-merah:bandar-tanah-merah']
tm = [l for l in links if l['area_source_id']==f'{K}:tanah-merah:tanah-merah']
check('btm-6-primaries', len(btm)==6 and all(l['is_primary']=='true' for l in btm), str(len(btm)))
check('tm-3-secondaries', len(tm)==3 and all(l['is_primary']=='false' for l in tm), str(len(tm)))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

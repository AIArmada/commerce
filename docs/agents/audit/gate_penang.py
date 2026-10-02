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
K = 'my:subdistrict:district:pulau-pinang'
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
from collections import Counter
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', not multi, str(multi[:5]))
for slug, t, parent in [('seberang-perai-selatan:bandar-sungai-bakap','bandar','seberang-perai-selatan'),
                        ('timur-laut:tanjong-tokong','bandar','timur-laut'),
                        ('timur-laut:tanjong-pinang','bandar','timur-laut')]:
    r = byid.get(f'{K}:{slug}')
    check(f'add {slug}', r and r['type']==t and r['parent_source_id']==f'my:district:pulau-pinang:{parent}' and r['level']=='3', str(r))
# mukim ranges per district (UPI book)
expect = {'seberang-perai-tengah': [f'mukim-{i}' for i in range(1,22)],
          'seberang-perai-utara': [f'mukim-{i}' for i in list(range(1,15))+[16]],
          'seberang-perai-selatan': [f'mukim-{i}' for i in range(1,17)],
          'timur-laut': [f'mukim-{i}' for i in range(13,19)],
          'barat-daya': [f'mukim-{i}' for i in range(1,13)]+[f'mukim-{c}' for c in 'abcdefghij']}
for d, slugs in expect.items():
    have = sorted(r['source_id'].split(':')[-1] for r in rows if r['parent_source_id']==f'my:district:pulau-pinang:{d}' and r['type']=='mukim')
    check(f'{d}-mukims-{len(slugs)}', have==sorted(slugs), str([s for s in slugs if f'{K}:{d}:{s}' not in byid]))
# town entity counts (bandar only; Penang book has no pekan)
for d, n in [('seberang-perai-tengah',2),('seberang-perai-utara',2),
             ('seberang-perai-selatan',2),('timur-laut',9),('barat-daya',2)]:
    have = [r for r in rows if r['parent_source_id']==f'my:district:pulau-pinang:{d}' and r['type']=='bandar']
    check(f'{d}-bandars-{n}', len(have)==n, str([r['source_id'].split(':')[-1] for r in have]))
hits = [l for l in links if l['postcode']=='10470' and l['is_primary']=='true']
check('10470-primary-tanjong-tokong', len(hits)==1 and hits[0]['area_source_id']==f'{K}:timur-laut:tanjong-tokong', str(hits))
hits = [l for l in links if l['postcode']=='10470' and l['area_source_id']==f'{K}:timur-laut:tanjong-pinang']
check('10470-secondary-tanjong-pinang', len(hits)==1 and hits[0]['is_primary']=='false', str(hits))
check('george-town-no-10470', not [l for l in links if l['postcode']=='10470' and l['area_source_id']==f'{K}:timur-laut:bandar-george-town'])
hits = [l for l in links if l['postcode']=='14200']
check('14200-primary-stays-sungai-jawi', [l['area_source_id'] for l in hits if l['is_primary']=='true']==[f'{K}:seberang-perai-selatan:sungai-jawi'], str(hits))
hits = [l for l in links if l['postcode']=='14200' and l['area_source_id']==f'{K}:seberang-perai-selatan:bandar-sungai-bakap']
check('14200-secondary-sungai-bakap', len(hits)==1 and hits[0]['is_primary']=='false', str(hits))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

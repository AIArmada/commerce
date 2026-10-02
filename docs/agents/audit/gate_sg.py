import csv, sys
AREAS = './packages/addressing/resources/geography/singapore-address-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(AREAS)))
byid = {r['source_id']: r for r in rows}
check('total-174', len(rows) == 174, str(len(rows)))
check('regions-5', sum(1 for r in rows if r['type'] == 'region') == 5)
check('cdc-districts-5', sum(1 for r in rows if r['type'] == 'district') == 5)
check('planning-areas-55', sum(1 for r in rows if r['type'] == 'planning_area') == 55)
check('postal-districts-28', sum(1 for r in rows if r['type'] == 'postal_district') == 28)
check('postal-sectors-81', sum(1 for r in rows if r['type'] == 'postal_sector') == 81)
# Full URA-sourced district -> sector table (74 and 83 genuinely unused).
table = {'01': ['01','02','03','04','05','06'], '02': ['07','08'], '03': ['14','15','16'],
 '04': ['09','10'], '05': ['11','12','13'], '06': ['17'], '07': ['18','19'],
 '08': ['20','21'], '09': ['22','23'], '10': ['24','25','26','27'], '11': ['28','29','30'],
 '12': ['31','32','33'], '13': ['34','35','36','37'], '14': ['38','39','40','41'],
 '15': ['42','43','44','45'], '16': ['46','47','48'], '17': ['49','50','81'],
 '18': ['51','52'], '19': ['53','54','55','82'], '20': ['56','57'], '21': ['58','59'],
 '22': ['60','61','62','63','64'], '23': ['65','66','67','68'], '24': ['69','70','71'],
 '25': ['72','73'], '26': ['77','78'], '27': ['75','76'], '28': ['79','80']}
ok = True
for d, secs in table.items():
    have = sorted(r['source_id'].split(':')[-1] for r in rows
                  if r['parent_source_id'] == f'sg:postal-district:{d}' and r['type'] == 'postal_sector')
    if have != sorted(secs):
        print('FAIL district', d, have); fails.append(f'district {d}'); ok = False
if ok: print('PASS all 28 district mappings')
for d, n in [('central',22),('east',6),('north',8),('north-east',7),('west',12)]:
    have = [r for r in rows if r['parent_source_id'] == f'sg:region:{d}' and r['type'] == 'planning_area']
    check(f'{d}-{n}', len(have) == n, str(len(have)))
# Spot names incl. the easily-misplaced water catchment + UPI-style spellings.
for slug, parent in [('central-water-catchment','north'),('western-water-catchment','west'),
                     ('sungei-kadut','north'),('changi-bay','east'),('tengah','west'),
                     ('paya-lebar','east'),('lim-chu-kang','north'),('seletar','north-east')]:
    r = byid.get(f'sg:planning-area:{slug}')
    check(f'{slug}->{parent}', r and r['parent_source_id'] == f'sg:region:{parent}', str(r))
for slug in ['central-singapore','north-east','north-west','south-east','south-west']:
    r = byid.get(f'sg:district:{slug}')
    check(f'cdc-{slug}', r and r['type'] == 'district' and r['parent_source_id'] == '', str(r))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

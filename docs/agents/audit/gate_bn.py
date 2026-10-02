import csv, re, sys
from collections import Counter
A = './packages/addressing/resources/geography/brunei-address-areas.csv'
C = './packages/addressing/resources/geography/brunei-postal-codes.csv'
L = './packages/addressing/resources/geography/brunei-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
byid = {r['source_id']: r for r in rows}
check('areas-43', len(rows) == 43, str(len(rows)))
check('codes-394', len(codes) == 394, str(len(codes)))
check('links-394', len(links) == 394, str(len(links)))
check('districts-4', sum(1 for r in rows if r['type'] == 'district') == 4)
check('mukims-39', sum(1 for r in rows if r['type'] == 'mukim') == 39)
for d, n in [('brunei-muara',18),('belait',8),('tutong',8),('temburong',5)]:
    have = [r for r in rows if r['parent_source_id'] == f'bn:district:{d}' and r['type'] == 'mukim']
    check(f'{d}-{n}', len(have) == n, str(len(have)))
bad = [c['code'] for c in codes if not re.match(r'^[A-Z]{2}\d{4}$', c['code'])]
check('code-format', not bad, str(bad[:3]))
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', not multi, str(multi[:3]))
# every code links exactly once, to a mukim
counts = Counter(l['postcode'] for l in links)
check('one-link-each', all(c == 1 for c in counts.values()))
check('all-to-mukim', all(byid[l['area_source_id']]['type'] == 'mukim' for l in links))
# genuine prefix splits (Brunei Post data): BE across Gadong A/B, BK/BN across Kedayan/Kebun
be = sorted(set(l['area_source_id'].split(':')[-1] for l in links if l['postcode'].startswith('BE')))
check('BE-gadong-split', be == ['gadong-a','gadong-b'], str(be))
bk = sorted(set(l['area_source_id'].split(':')[-1] for l in links if l['postcode'].startswith('BK')))
check('BK-kebun-kedayan-split', bk == ['sungai-kebun','sungai-kedayan'], str(bk))
check('BS8611-kianggeh', [l['area_source_id'] for l in links if l['postcode'] == 'BS8611'] == ['bn:mukim:kianggeh'])
# agency/locked-bag codes deliberately excluded (all other gist codes bundled)
have = {l['postcode'] for l in links}
for pc in ['BS8670','BS8675','BA1710','KB3534','TA1141']:
    check(f'{pc}-excluded', pc not in have)
# BB3713 is dual-listed in the source (kampung + locked bag): bundled as the kampung code
check('BB3713-berakas-a', [l['area_source_id'] for l in links if l['postcode'] == 'BB3713'] == ['bn:mukim:berakas-a'])
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

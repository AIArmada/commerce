import csv, re, sys
from collections import Counter
A = './packages/addressing/resources/geography/palestine-address-areas.csv'
C = './packages/addressing/resources/geography/palestine-postal-codes.csv'
L = './packages/addressing/resources/geography/palestine-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
byid = {r['source_id']: r for r in rows}
check('areas-16', len(rows) == 16, str(len(rows)))
check('codes-603', len(codes) == 603, str(len(codes)))
check('links-603', len(links) == 603, str(len(links)))
check('all-governorate-L1', all(r['type'] == 'governorate' and r['level'] == '1' for r in rows))
check('all-root', all(r['parent_source_id'] == '' for r in rows))
# ISO 3166-2:PS codes + OCHA COD-AB names (2 display shortenings, 2 ISO-vs-OCHA spellings pinned).
names = {'bethlehem': ('Bethlehem', 'BTH'), 'deir-el-balah': ('Deir El Balah', 'DEB'),
 'gaza': ('Gaza', 'GZA'), 'hebron': ('Hebron', 'HBN'), 'jenin': ('Jenin', 'JEN'),
 'jericho': ('Jericho', 'JRH'), 'jerusalem-quds': ('Jerusalem (Quds)', 'JEM'),
 'khan-yunis': ('Khan Yunis', 'KYS'), 'nablus': ('Nablus', 'NBS'),
 'north-gaza': ('North Gaza', 'NGZ'), 'qalqilya': ('Qalqilya', 'QQA'),
 'rafah': ('Rafah', 'RFH'), 'ramallah': ('Ramallah', 'RBH'), 'salfit': ('Salfit', 'SLT'),
 'tubas': ('Tubas', 'TBS'), 'tulkarm': ('Tulkarm', 'TKM')}
for slug, (nm, cd) in names.items():
    r = byid.get(f'ps:governorate:{slug}')
    check(f'name-{slug}', r and r['name'] == nm and r['code'] == cd, str(r))
bad = [c['code'] for c in codes if not re.match(r'^P\d{3}$', c['code'])]
check('code-format-P3', not bad, str(bad[:3]))
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
check('exactly-one-primary', len(prim) == 603 and all(c == 1 for c in prim.values()))
counts = Counter(l['postcode'] for l in links)
check('one-link-each', all(c == 1 for c in counts.values()))
check('all-to-governorate', all(byid[l['area_source_id']]['type'] == 'governorate' for l in links))
check('codes-links-same-set', {c['code'] for c in codes} == set(counts))
# Ministry table (755 rows) per-governorate distinct counts, verified 2026-10-03.
exp = {'bethlehem': 41, 'deir-el-balah': 7, 'gaza': 7, 'hebron': 86, 'jenin': 92,
 'jericho': 20, 'jerusalem-quds': 42, 'khan-yunis': 5, 'nablus': 87,
 'north-gaza': 5, 'qalqilya': 34, 'rafah': 4, 'ramallah': 91, 'salfit': 18,
 'tubas': 29, 'tulkarm': 35}
for slug, n in exp.items():
    have = sum(1 for l in links if l['area_source_id'] == f'ps:governorate:{slug}')
    check(f'count-{slug}-{n}', have == n, str(have))
# Instruction No. 1/2022 Art. 5 legal ranges (Gazette 187, 2022/01/23).
legal = {'jerusalem-quds': (100, 149), 'bethlehem': (150, 199), 'jenin': (200, 299),
 'tulkarm': (300, 339), 'qalqilya': (340, 379), 'salfit': (380, 399),
 'nablus': (400, 499), 'tubas': (500, 549), 'jericho': (550, 599),
 'ramallah': (600, 699), 'hebron': (700, 799), 'north-gaza': (800, 839),
 'gaza': (840, 899), 'deir-el-balah': (900, 929), 'khan-yunis': (930, 969),
 'rafah': (970, 999)}
oor = [l['postcode'] for l in links
       if not (legal[l['area_source_id'].split(':')[-1]][0] <= int(l['postcode'][1:])
               <= legal[l['area_source_id'].split(':')[-1]][1])]
check('all-within-legal-range', not oor, str(oor[:5]))
link = {l['postcode']: l['area_source_id'] for l in links}
# UPU pseFr 05/2025 anchors.
check('P126-jerusalem', link.get('P126') == 'ps:governorate:jerusalem-quds')
check('P144-jerusalem', link.get('P144') == 'ps:governorate:jerusalem-quds')
check('P610-ramallah', link.get('P610') == 'ps:governorate:ramallah')
# P149 is Jerusalem, not Bethlehem: ministry lists Beit Safafa + Sharafat under
# Jerusalem, legal range P100-P149 is Jerusalem, Mapanet itself votes 2-1 Jerusalem
# (Al Walaja is the lone Bethlehem row). No Bethlehem secondary.
check('P149-jerusalem', link.get('P149') == 'ps:governorate:jerusalem-quds')
# Range-boundary pins (adjacent codes across governorate edges).
for pc, slug in [('P150', 'bethlehem'), ('P339', 'tulkarm'), ('P340', 'qalqilya'),
                 ('P399', 'salfit'), ('P400', 'nablus'), ('P929', 'deir-el-balah'),
                 ('P930', 'khan-yunis'), ('P969', 'khan-yunis'), ('P970', 'rafah'),
                 ('P100', 'jerusalem-quds'), ('P999', 'rafah')]:
    check(f'{pc}-{slug}', link.get(pc) == f'ps:governorate:{slug}', link.get(pc, 'MISSING'))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

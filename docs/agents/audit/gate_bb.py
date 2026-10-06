import csv, re, sys
from collections import Counter, defaultdict
# Barbados gate. B4 revisit: verify-only, zero data changes. Live BPS
# finder JS re-pull 2026-10-03 (2866 district rows, postalcodes.js):
# 1161/1161 BB+5 codes match bundled link sets exactly; 15 x00 office
# bases verified against the 18-office table (BB26000 is in the finder
# itself as "St. Peter Post Office"); BB190215 6-digit Todds Land row
# held out (no single-digit twin carries the label: BB19021 has other
# districts, BB19215 absent). Run from repo root:
# python3 docs/agents/audit/gate_bb.py
A = './packages/addressing/resources/geography/barbados-address-areas.csv'
C = './packages/addressing/resources/geography/barbados-postal-codes.csv'
L = './packages/addressing/resources/geography/barbados-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A, newline='')))
byid = {r['source_id']: r for r in rows}
check('areas-11', len(rows) == 11, str(len(rows)))
check('all-parish-L1-root', all(r['type'] == 'parish' and r['level'] == '1'
      and r['parent_source_id'] == '' and r['country_code'] == 'BB' for r in rows))
# ISO 3166-2:BB defines BB-01..BB-11, exact name mapping (Christ
# Church first, then Saints alphabetical).
iso = {'bb:parish:christ-church': ('Christ Church', '01'),
       'bb:parish:saint-andrew': ('Saint Andrew', '02'),
       'bb:parish:saint-george': ('Saint George', '03'),
       'bb:parish:saint-james': ('Saint James', '04'),
       'bb:parish:saint-john': ('Saint John', '05'),
       'bb:parish:saint-joseph': ('Saint Joseph', '06'),
       'bb:parish:saint-lucy': ('Saint Lucy', '07'),
       'bb:parish:saint-michael': ('Saint Michael', '08'),
       'bb:parish:saint-peter': ('Saint Peter', '09'),
       'bb:parish:saint-philip': ('Saint Philip', '10'),
       'bb:parish:saint-thomas': ('Saint Thomas', '11')}
for sid, (nm, cd) in iso.items():
    r = byid.get(sid)
    check(f'iso-{cd}', bool(r) and r['name'] == nm and r['code'] == cd, str(r))
codes = [r['code'] for r in csv.DictReader(open(C, newline=''))]
links = list(csv.DictReader(open(L, newline='')))
check('codes-1176', len(codes) == 1176, str(len(codes)))
check('links-1185', len(links) == 1185, str(len(links)))
check('distinct-1176', len(set(codes)) == 1176)
bad = [c for c in codes if not re.fullmatch(r'BB\d{5}', c)]
check('code-format-BB5', not bad, str(bad[:3]))
check('range-BB11000-BB27193', min(codes) == 'BB11000' and max(codes) == 'BB27193',
      f'{min(codes)}-{max(codes)}')
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
check('all-served-by', all(l['relationship_type'] == 'served_by' for l in links))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
check('exactly-one-primary', len(prim) == 1176 and all(c == 1 for c in prim.values()))
check('codes-primaries-same-set', set(codes) == set(prim))
bycode = defaultdict(list)
for l in links:
    bycode[l['postcode']].append(l)
# Per-block code counts (finder + office bases per block).
blocks = {'11': 130, '12': 68, '13': 1, '14': 36, '15': 123, '16': 1,
          '17': 104, '18': 76, '19': 186, '20': 31, '21': 42, '22': 57,
          '23': 35, '24': 32, '25': 54, '26': 95, '27': 105}
have = Counter(c[2:4] for c in codes)
for b, n in blocks.items():
    check(f'block-{b}-{n}', have[b] == n, str(have[b]))
# Block purity: every block is single-parish except BB23 (finder
# sub-labels: St. Michael 1/2/3 = blocks 11/12/14, Christ Church
# 1/2 = blocks 17/15; blocks 13/16 are office-base-only).
pure = {'11': 'saint-michael', '12': 'saint-michael', '13': 'saint-michael',
        '14': 'saint-michael', '15': 'christ-church', '16': 'christ-church',
        '17': 'christ-church', '18': 'saint-philip', '19': 'saint-george',
        '20': 'saint-john', '21': 'saint-joseph', '22': 'saint-thomas',
        '24': 'saint-james', '25': 'saint-andrew', '26': 'saint-peter',
        '27': 'saint-lucy'}
for b, parish in pure.items():
    got = {l['area_source_id'] for l in links
           if l['postcode'][2:4] == b}
    check(f'pure-{b}-{parish}', got == {f'bb:parish:{parish}'}, str(got))
# Office base codes (18 district post offices share 16 x00 codes:
# BB11000 GPO + Cruise Terminal, BB24000 Holetown + West Terrace).
office = {'BB11000': 'saint-michael', 'BB12000': 'saint-michael',
          'BB13000': 'saint-michael', 'BB14000': 'saint-michael',
          'BB15000': 'christ-church', 'BB16000': 'christ-church',
          'BB17000': 'christ-church', 'BB18000': 'saint-philip',
          'BB19000': 'saint-george', 'BB20000': 'saint-john',
          'BB21000': 'saint-joseph', 'BB22000': 'saint-thomas',
          'BB24000': 'saint-james', 'BB25000': 'saint-andrew',
          'BB26000': 'saint-peter', 'BB27000': 'saint-lucy'}
for pc, parish in office.items():
    ls = bycode.get(pc, [])
    check(f'office-{pc}', len(ls) == 1 and ls[0]['area_source_id'] == f'bb:parish:{parish}'
          and ls[0]['is_primary'] == 'true', str(ls))
# BB23 full primary table (St James series with St Michael / St
# Thomas border legs; finder district counts decide primaries).
J = 'bb:parish:saint-james'
M = 'bb:parish:saint-michael'
T = 'bb:parish:saint-thomas'
b23 = {'BB23001': J, 'BB23002': J, 'BB23003': J, 'BB23004': J,
       'BB23006': J, 'BB23007': J, 'BB23008': J, 'BB23010': J,
       'BB23014': J, 'BB23015': J, 'BB23016': J, 'BB23017': J,
       'BB23018': J, 'BB23019': J, 'BB23020': J, 'BB23021': J,
       'BB23022': J, 'BB23024': J, 'BB23025': T, 'BB23026': T,
       'BB23027': J, 'BB23028': M, 'BB23029': M, 'BB23030': M,
       'BB23031': J, 'BB23032': M, 'BB23033': M, 'BB23034': M,
       'BB23035': J, 'BB23036': J, 'BB23037': M, 'BB23038': M,
       'BB23039': M, 'BB23040': J, 'BB23042': J}
for pc, sid in b23.items():
    ls = bycode.get(pc, [])
    ps = [l for l in ls if l['is_primary'] == 'true']
    check(f'b23-{pc}', len(ps) == 1 and ps[0]['area_source_id'] == sid, str(ls))
# The 9 dual splits with finder district-count rationale (JvM / JvT
# votes; BB23027 is a 1v1 tie broken by block context: BB23 is a St
# James series, 24 of 35 primaries St James).
dual = {'BB23006': (J, T, '10v1'), 'BB23017': (J, M, '2v1'),
        'BB23021': (J, M, '4v2'), 'BB23024': (J, M, '2v1'),
        'BB23027': (J, M, 'tie1v1'), 'BB23031': (J, M, '2v1'),
        'BB23035': (J, M, '2v1'), 'BB23037': (M, J, '4v2'),
        'BB23040': (J, M, '2v1')}
for pc, (p, s, vote) in dual.items():
    ls = sorted(bycode.get(pc, []), key=lambda l: l['is_primary'], reverse=True)
    check(f'dual-{pc}-{vote}', len(ls) == 2 and ls[0]['area_source_id'] == p
          and ls[0]['is_primary'] == 'true' and ls[1]['area_source_id'] == s
          and ls[1]['is_primary'] == 'false', str(ls))
check('only-9-duals', sum(1 for ls in bycode.values() if len(ls) > 1) == 9)
# Held out: BB190215 (6-digit Todds Land typo; BB19021 carries other
# districts and BB19215 does not exist, so no safe retarget).
check('held-out-BB190215', 'BB190215' not in set(codes))
# Per-parish primary counts (finder rows + office bases).
exp = {'saint-michael': 244, 'christ-church': 228, 'saint-philip': 76,
       'saint-george': 186, 'saint-john': 31, 'saint-joseph': 42,
       'saint-thomas': 59, 'saint-james': 56, 'saint-andrew': 54,
       'saint-peter': 95, 'saint-lucy': 105}
havep = Counter(l['area_source_id'].split(':')[-1] for l in links
                if l['is_primary'] == 'true')
for parish, n in exp.items():
    check(f'count-{parish}-{n}', havep[parish] == n, str(havep[parish]))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

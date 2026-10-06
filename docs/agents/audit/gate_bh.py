import csv, re, sys
from collections import Counter
# Bahrain gate. Block number = postcode (UPU BHR profile). M1 revisit:
# verify-only, zero data changes. Run from repo root:
# python3 docs/agents/audit/gate_bh.py
A = './packages/addressing/resources/geography/bahrain-address-areas.csv'
C = './packages/addressing/resources/geography/bahrain-postal-codes.csv'
L = './packages/addressing/resources/geography/bahrain-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
byid = {r['source_id']: r for r in rows}
check('areas-4', len(rows) == 4, str(len(rows)))
check('all-governorate-L1-root', all(r['type'] == 'governorate' and r['level'] == '1'
      and r['parent_source_id'] == '' for r in rows))
# ISO 3166-2:BH (Central BH-16 abolished 2014; 4 governorates remain).
iso = {'bh:governorate:capital': ('Capital', '13'),
       'bh:governorate:southern': ('Southern', '14'),
       'bh:governorate:muharraq': ('Muharraq', '15'),
       'bh:governorate:northern': ('Northern', '17')}
for sid, (nm, cd) in iso.items():
    r = byid.get(sid)
    check(f'iso-{cd}', r and r['name'] == nm and r['code'] == cd, str(r))
check('codes-479', len(codes) == 479, str(len(codes)))
check('links-479', len(links) == 479, str(len(links)))
check('distinct-479', len({c['code'] for c in codes}) == 479)
bad = [c['code'] for c in codes if not re.match(r'^\d{3,4}$', c['code'])]
check('code-format-34d', not bad, str(bad[:3]))
nums = sorted(int(c['code']) for c in codes)
check('upu-range-1xx-12xx', nums[0] >= 100 and nums[-1] <= 1299,
      f'{nums[0]}-{nums[-1]}')
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
check('exactly-one-primary', len(prim) == 479 and all(c == 1 for c in prim.values()))
check('all-served-by', all(l['relationship_type'] == 'served_by' for l in links))
check('codes-links-same-set', {c['code'] for c in codes} == set(prim))
# Per-governorate block counts (youbianku governorate block table).
exp = {'capital': 121, 'muharraq': 74, 'northern': 156, 'southern': 128}
have = Counter(l['area_source_id'].split(':')[-1] for l in links)
for gov, n in exp.items():
    check(f'count-{gov}-{n}', have[gov] == n, str(have[gov]))
link = {l['postcode']: l['area_source_id'] for l in links}
# UPU BHR profile anchors: AL-MANAMAH 317, RIFFA 926.
check('upu-317-capital', link.get('317') == 'bh:governorate:capital')
check('upu-926-southern', link.get('926') == 'bh:governorate:southern')
# Addressed-sighting anchors: Sanabis 408, Riffa 915, Nasfa 733, Sanad 743.
for pc, gov in [('408', 'capital'), ('915', 'southern'), ('733', 'capital'),
                ('743', 'capital')]:
    check(f'sight-{pc}-{gov}', link.get(pc) == f'bh:governorate:{gov}',
          str(link.get(pc)))
# A'ali-area three-way split (town straddles boundaries; Sanad/Nasfa legs
# are Capital per Works Ministry project pages, not A'ali/Northern).
split = {'732': 'northern', '733': 'capital', '734': 'northern',
         '736': 'northern', '738': 'northern', '740': 'northern',
         '742': 'northern', '743': 'capital', '744': 'northern',
         '745': 'capital', '746': 'southern', '748': 'southern'}
for pc, gov in split.items():
    check(f'aali-{pc}-{gov}', link.get(pc) == f'bh:governorate:{gov}',
          str(link.get(pc)))
# Synthetic hundred-base aggregates stay out (Mapanet hundred codes).
for pc in ['300', '500', '600', '700', '900', '1000', '1200']:
    check(f'no-base-{pc}', pc not in link)
# 2nd signal (not pinned: external): Mapanet full pull re-verified
# 2026-10-03, 106/106 certain-town blocks agree, 0 disagree.
# Gaps: 573 (single Mapanet Janabiyah row) uncovered, not filled.
# Weak: 479 vs SLRB 478 off-by-one unresolved (no SLRB block list).
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

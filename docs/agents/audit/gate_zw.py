import csv, os, sys
# Zimbabwe gate. B10 revisit: verify-only. Tree 10 provinces
# (ISO 3166-2:ZW) + 64 districts pinned vs the en-wp Districts
# list (exact), Statoids yzw (60/64; the 4 new splits postdate
# it), and the ZimStat census via citypopulation (Mbire,
# Mhondoro-Ngezi, Sanyati as census Districts; Vungu via the
# official Vungu RDC). The WP Harare section's 15 extra suburb
# entries (Glen View, Budiriro, ...) are pollution: the
# article's own lede says 64, and neither Statoids nor census
# lists them — bundled Harare keeps 3. No postcode system:
# UPU do-not-require list + GeoNames ZW.zip 404.
# Run from repo root:
# python3 docs/agents/audit/gate_zw.py
A = './packages/addressing/resources/geography/zimbabwe-address-areas.csv'
C = './packages/addressing/resources/geography/zimbabwe-postal-codes.csv'
L = './packages/addressing/resources/geography/zimbabwe-postal-code-areas.csv'
PROV = {'zw:province:bulawayo': ('Bulawayo', 'BU'),
        'zw:province:harare': ('Harare', 'HA'),
        'zw:province:manicaland': ('Manicaland', 'MA'),
        'zw:province:mashonaland-central': ('Mashonaland Central', 'MC'),
        'zw:province:mashonaland-east': ('Mashonaland East', 'ME'),
        'zw:province:mashonaland-west': ('Mashonaland West', 'MW'),
        'zw:province:masvingo': ('Masvingo', 'MV'),
        'zw:province:matabeleland-north': ('Matabeleland North', 'MN'),
        'zw:province:matabeleland-south': ('Matabeleland South', 'MS'),
        'zw:province:midlands': ('Midlands', 'MI')}
DIST = {
 'zw:province:bulawayo': ['Bulawayo'],
 'zw:province:harare': ['Harare', 'Chitungwiza', 'Epworth'],
 'zw:province:manicaland': ['Buhera', 'Chimanimani', 'Chipinge',
                            'Makoni', 'Mutare', 'Mutasa', 'Nyanga'],
 'zw:province:mashonaland-central': ['Bindura', 'Guruve', 'Mazowe',
                                     'Mbire', 'Mount Darwin',
                                     'Muzarabani', 'Rushinga', 'Shamva'],
 'zw:province:mashonaland-east': ['Chikomba', 'Goromonzi', 'Marondera',
                                  'Mudzi', 'Murehwa', 'Mutoko', 'Seke',
                                  'UMP', 'Wedza'],
 'zw:province:mashonaland-west': ['Chegutu', 'Hurungwe', 'Kariba',
                                  'Makonde', 'Mhondoro-Ngezi', 'Sanyati',
                                  'Zvimba'],
 'zw:province:masvingo': ['Bikita', 'Chiredzi', 'Chivi', 'Gutu',
                          'Masvingo', 'Mwenezi', 'Zaka'],
 'zw:province:matabeleland-north': ['Binga', 'Bubi', 'Hwange', 'Lupane',
                                    'Nkayi', 'Tsholotsho', 'Umguza'],
 'zw:province:matabeleland-south': ['Beitbridge', 'Bulilima', 'Gwanda',
                                    'Insiza', 'Mangwe', 'Matobo',
                                    'Umzingwane'],
 'zw:province:midlands': ['Chirumhanzu', 'Gokwe North', 'Gokwe South',
                          'Vungu', 'Kwekwe', 'Mberengwa', 'Shurugwi',
                          'Zvishavane']}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


raw_a = open(A, 'rb').read()
check('areas-lf', b'\r' not in raw_a)
check('areas-trailing-nl', raw_a.endswith(b'\n'))
areas = list(csv.DictReader(open(A, encoding='utf-8')))
check('areas-74', len(areas) == 74, str(len(areas)))
pr = [r for r in areas if r['type'] == 'province']
got_pr = {r['source_id']: (r['name'], r['code']) for r in pr}
check('prov-10', got_pr == PROV,
      str({k for k in PROV if got_pr.get(k) != PROV[k]}))
di = [r for r in areas if r['type'] == 'district']
check('dist-64', len(di) == 64, str(len(di)))
got = {}
for r in di:
    got.setdefault(r['parent_source_id'], []).append(r['name'])
bad = {k for k in DIST if sorted(got.get(k, [])) != sorted(DIST[k])}
check('dist-xmap', not bad, str(sorted(bad)))
check('no-harare-suburbs', not any(r['name'] in
      ('Glen View', 'Budiriro', 'Borrowdale', 'Mabvuku') for r in di))
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else 'FAILURES: ' + ','.join(fails))
sys.exit(1 if fails else 0)

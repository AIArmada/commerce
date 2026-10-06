import csv, re, sys
from collections import Counter, defaultdict
# Senegal gate. M2 revisit: 14 link fixes (12 drops + 1 retarget + 1 add).
# Pins the CORRECTED state: 163 codes / 182 links / 19 multi codes.
# FAILS on the pre-fix CSVs (193 links / 28 multis) until verdict.json
# data_changes are applied. Run from repo root:
# python3 docs/agents/audit/gate_sn.py
A = './packages/addressing/resources/geography/senegal-address-areas.csv'
C = './packages/addressing/resources/geography/senegal-postal-codes.csv'
L = './packages/addressing/resources/geography/senegal-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
byid = {r['source_id']: r for r in rows}
# --- tree: 14 regions + 46 departments (Departments of Senegal oracle) ---
check('areas-60', len(rows) == 60, str(len(rows)))
check('regions-14', sum(1 for r in rows if r['type'] == 'region') == 14)
check('departments-46', sum(1 for r in rows if r['type'] == 'department') == 46)
iso = {'dakar': 'DK', 'diourbel': 'DB', 'fatick': 'FK', 'kaffrine': 'KA',
       'kaolack': 'KL', 'kedougou': 'KE', 'kolda': 'KD', 'louga': 'LG',
       'matam': 'MT', 'saint-louis': 'SL', 'sedhiou': 'SE',
       'tambacounda': 'TC', 'thies': 'TH', 'ziguinchor': 'ZG'}
for slug, cd in iso.items():
    r = byid.get(f'sn:region:{slug}')
    check(f'iso-{cd}', r and r['code'] == cd, str(r))
exp_par = {'dakar': 5, 'diourbel': 3, 'fatick': 3, 'kaffrine': 4, 'kaolack': 3,
           'kedougou': 3, 'kolda': 3, 'louga': 3, 'matam': 3, 'saint-louis': 3,
           'sedhiou': 3, 'tambacounda': 4, 'thies': 3, 'ziguinchor': 3}
for reg, n in exp_par.items():
    have = [r for r in rows if r['parent_source_id'] == f'sn:region:{reg}']
    check(f'depts-{reg}-{n}', len(have) == n, str(len(have)))
# Keur Massar 2021 split (28 May 2021, 46th department).
r = byid.get('sn:department:keur-massar')
check('keur-massar-split', r and r['parent_source_id'] == 'sn:region:dakar'
      and r['name'] == 'Keur Massar', str(r))
# --- codes / links ---
check('codes-163', len(codes) == 163, str(len(codes)))
check('links-182', len(links) == 182, str(len(links)))
check('distinct-163', len({c['code'] for c in codes}) == 163)
bad = [c['code'] for c in codes if not re.match(r'^\d{5}$', c['code'])]
check('code-format-5d', not bad, str(bad[:3]))
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
check('exactly-one-primary', len(prim) == 163 and all(c == 1 for c in prim.values()))
check('all-served-by', all(l['relationship_type'] == 'served_by' for l in links))
check('codes-links-same-set', {c['code'] for c in codes} == set(prim))
by_code = defaultdict(list)
for l in links:
    by_code[l['postcode']].append(l)
multi = {c: v for c, v in by_code.items() if len(v) > 1}
check('multi-19', len(multi) == 19, str(sorted(multi)))
# --- corrected multi map: primary + secondaries, all 19 ---
exp_multi = {
    '11500': ('dakar', ['pikine']),            # Dalifort bureau (Pikine)
    '16000': ('pikine', ['dakar']),            # LIBERTE 5 quartier (Dakar)
    '17000': ('pikine', ['keur-massar']),      # KM/Malika bureau + Yeumbeul quartiers
    '20600': ('thies', ['tivaouane']),         # F7: Mbayene CR (Tivaouane)
    '21001': ('thies', ['tivaouane']),         # Mont Rolland (Tivaouane)
    '24000': ('kaolack', ['guinguineo']),      # Mbadakhoune/Khelcom Birane
    '24025': ('foundiougne', ['fatick']),      # 15q vs 8q
    '24200': ('nioro-du-rip', ['kaolack']),    # Nioro 15-list vs Dinguiraye/Ndiaffate
    '24600': ('kaffrine', ['malem-hoddar']),   # Delbi (MH)
    '26000': ('tambacounda', ['goudiry']),     # Goumbayel (Goudiry)
    '26015': ('nioro-du-rip', ['velingara']),  # Medina Sabakh 13q vs Medina Gounass
    '26020': ('goudiry', ['bakel']),           # Sadatou (Bakel)
    '26022': ('bakel', ['goudiry']),           # Koussan (Goudiry); Bele is Bakel
    '27022': ('sedhiou', ['goudomp']),         # Karantaba+Simbandi (Goudomp); F2
    '27026': ('sedhiou', ['goudomp']),         # Kolibantang (Goudomp)
    '27200': ('bignona', ['oussouye']),        # Elinkine (Mlomp CR, Oussouye)
    '27400': ('kolda', ['medina-yoro-foulah']),  # 21q vs 9q; F13
    '31011': ('linguere', ['kebemer']),        # Kambe (Kebemer)
    '32800': ('dagana', ['podor']),            # 9-9 tie; bureau-name judgment
}
for pc, (p, secs) in exp_multi.items():
    got_p = [l['area_source_id'].split(':')[-1] for l in by_code.get(pc, [])
             if l['is_primary'] == 'true']
    got_s = sorted(l['area_source_id'].split(':')[-1] for l in by_code.get(pc, [])
                   if l['is_primary'] != 'true')
    check(f'multi-{pc}', got_p == [p] and got_s == sorted(secs),
          f'{got_p}/{got_s}')
# --- the 12 dropped secondaries + 1 retarget must be gone ---
gone = [('24018', 'gossas'), ('27022', 'bounkiling'), ('23200', 'thies'),
        ('31000', 'kebemer'), ('24030', 'nioro-du-rip'),
        ('26018', 'tambacounda'), ('27406', 'kolda'),
        ('27009', 'ziguinchor'), ('30600', 'louga'),
        ('25400', 'tambacounda'), ('27400', 'velingara'),
        ('22100', 'diourbel')]
linkset = {(l['postcode'], l['area_source_id'].split(':')[-1]) for l in links}
for pc, dept in gone:
    check(f'dropped-{pc}-{dept}', (pc, dept) not in linkset)
check('retarget-24027-fatick', ('24027', 'fatick') in linkset
      and ('24027', 'foundiougne') not in linkset)
# --- per-department link counts (corrected) ---
exp_counts = {'bakel': 10, 'bambey': 1, 'bignona': 6, 'birkilane': 1,
              'bounkiling': 1, 'dagana': 4, 'dakar': 14, 'diourbel': 2,
              'fatick': 6, 'foundiougne': 8, 'gossas': 1, 'goudiry': 4,
              'goudomp': 5, 'guediawaye': 1, 'guinguineo': 3, 'kaffrine': 1,
              'kanel': 8, 'kaolack': 7, 'kebemer': 7, 'kedougou': 2,
              'keur-massar': 1, 'kolda': 1, 'koumpentoum': 1, 'koungheul': 1,
              'linguere': 3, 'louga': 4, 'm-bour': 6, 'malem-hoddar': 2,
              'matam': 9, 'mbacke': 2, 'medina-yoro-foulah': 1,
              'nioro-du-rip': 2, 'oussouye': 3, 'pikine': 4, 'podor': 14,
              'ranerou-ferlo': 1, 'rufisque': 3, 'saint-louis': 2,
              'salemata': 1, 'saraya': 2, 'sedhiou': 3, 'tambacounda': 4,
              'thies': 5, 'tivaouane': 9, 'velingara': 3, 'ziguinchor': 3}
have = Counter(l['area_source_id'].split(':')[-1] for l in links)
for dept, n in exp_counts.items():
    check(f'count-{dept}-{n}', have[dept] == n, str(have[dept]))
# --- anchors: official La Poste bureau table + UPU profile ---
link1 = {l['postcode']: l['area_source_id'] for l in links
         if l['is_primary'] == 'true'}
for pc, dept in [('10200', 'dakar'), ('16500', 'pikine'),  # official bureau rows
                 ('10000', 'dakar'), ('27000', 'ziguinchor'),  # UPU examples
                 ('22300', 'mbacke'), ('24000', 'kaolack'),   # official bureaus
                 ('32000', 'saint-louis'), ('21000', 'thies'),
                 ('20000', 'rufisque'), ('23000', 'm-bour'),
                 ('16000', 'pikine'), ('11500', 'dakar')]:
    check(f'anchor-{pc}-{dept}',
          link1.get(pc) == f'sn:department:{dept}', str(link1.get(pc)))
# --- region block table (2-digit prefixes per region, corrected links) ---
preg = {r['source_id']: r['name'] for r in rows if r['level'] == '1'}
dreg = {r['source_id']: preg[r['parent_source_id']] for r in rows
        if r['level'] == '2'}
rb = defaultdict(set)
for l in links:
    rb[dreg[l['area_source_id']]].add(l['postcode'][:2])
exp_blocks = {'Dakar': ['10', '11', '12', '13', '14', '15', '16', '17', '20'],
              'Diourbel': ['21', '22'], 'Fatick': ['23', '24'],
              'Kaffrine': ['24', '25'], 'Kaolack': ['23', '24', '26'],
              'Kolda': ['26', '27'], 'Kédougou': ['26'], 'Louga': ['30', '31'],
              'Matam': ['36'], 'Saint-Louis': ['32', '33', '34', '35'],
              'Sédhiou': ['27'], 'Tambacounda': ['25', '26'],
              'Thiès': ['20', '21', '22', '23', '30'], 'Ziguinchor': ['27']}
for reg, prefs in exp_blocks.items():
    check(f'block-{reg}', sorted(rb[reg]) == prefs, str(sorted(rb[reg])))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

import csv, sys
# Vanuatu gate. B10 revisit: fix-and-fill (69->73 areas).
# Penama Bangan-Vanua/Lungei-Tagaro -> East Ambae/North Maewo,
# Shefa Yarsu -> South Epi (citypopulation VNSO-census + UN OCHA
# COD-AB + Statoids' own variant table, 3 signals each), Tafea
# Whitesands Tanna -> Whitesands (census + COD + WP-Provinces),
# Torba 3 directional -> 7 island councils (census + COD; the
# bundled/Statoids roster is pre-2008 vintage per Statoids'
# own caveat). WP's uncited boilerplate admin sections are the
# outlier everywhere except Malampa/Penama names. HASC codes
# kept stable on renames; new Torba rows codeless (Lenakel
# precedent). No postcode system (overlay `none`).
# Run from repo root:
# python3 docs/agents/audit/gate_vu.py
A = './packages/addressing/resources/geography/vanuatu-address-areas.csv'
PROV = {'vu:province:malampa': ('Malampa', 'MAP'),
        'vu:province:penama': ('Penama', 'PAM'),
        'vu:province:sanma': ('Sanma', 'SAM'),
        'vu:province:shefa': ('Shefa', 'SEE'),
        'vu:province:tafea': ('Tafea', 'TAE'),
        'vu:province:torba': ('Torba', 'TOB')}
L2 = {
 'vu:province:malampa': ['Central Malekula', 'North Ambrym',
                         'North East Malekula', 'North West Malekula',
                         'Paama', 'South East Ambrym',
                         'South East Malekula', 'South Malekula',
                         'South West Malekula', 'West Ambrym'],
 'vu:province:penama': ['Central Pentecost 1', 'Central Pentecost 2',
                        'East Ambae', 'North Ambae', 'North Maewo',
                        'North Pentecost', 'South Ambae', 'South Maewo',
                        'South Pentecost', 'West Ambae'],
 'vu:province:sanma': ['Canal-Fanafo', 'East Malo', 'East Santo',
                       'Luganville', 'North Santo', 'North West Santo',
                       'South East Santo', 'South Santo', 'West Malo',
                       'West Santo'],
 'vu:province:shefa': ['Emau', 'Erakor', 'Eratap', 'Eton', 'Ifira',
                       'Makimae', 'Malorua', 'Mele', 'Nguna',
                       'North Efate', 'North Tongoa', 'Pango',
                       'Port Vila', 'South Epi', 'Tongariki', 'Varisu',
                       'Vermali', 'Vermaul'],
 'vu:province:tafea': ['Aneityum', 'Aniwa', 'Futuna',
                       'Middle Bush Tanna', 'North Erromango',
                       'North Tanna', 'South Erromango', 'South Tanna',
                       'South West Tanna', 'West Tanna', 'Whitesands',
                       'Lenakel'],
 'vu:province:torba': ['Gaua', 'Merelava', 'Mota', 'Motalava', 'Torres',
                       'Ureparapara', 'Vanua Lava']}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


raw_a = open(A, 'rb').read()
check('areas-lf', b'\r' not in raw_a)
check('areas-trailing-nl', raw_a.endswith(b'\n'))
areas = list(csv.DictReader(open(A, encoding='utf-8')))
check('areas-73', len(areas) == 73, str(len(areas)))
pr = [r for r in areas if r['type'] == 'province']
got_pr = {r['source_id']: (r['name'], r['code']) for r in pr}
check('prov-6', got_pr == PROV,
      str({k for k in PROV if got_pr.get(k) != PROV[k]}))
l2 = [r for r in areas if r['level'] == '2']
check('l2-67', len(l2) == 67, str(len(l2)))
got = {}
for r in l2:
    got.setdefault(r['parent_source_id'], []).append(r['name'])
bad = {k for k in L2 if sorted(got.get(k, [])) != sorted(L2[k])}
check('l2-xmap', not bad, str(sorted(bad)))
by_id = {r['source_id']: r for r in areas}
check('east-ambae', by_id.get('vu:area_council:east-ambae', {}).get('code') == 'VU.PM.LT')
check('north-maewo', by_id.get('vu:area_council:north-maewo', {}).get('code') == 'VU.PM.BV')
check('south-epi', by_id.get('vu:area_council:south-epi', {}).get('code') == 'VU.SE.YA')
check('whitesands', by_id.get('vu:area_council:whitesands-tanna', {}).get('name') == 'Whitesands')
check('no-stale-penama', 'vu:area_council:bangan-vanua' not in by_id
      and 'vu:area_council:lungei-tagaro' not in by_id)
check('no-directional-torba', not any('torba' in k and k != 'vu:province:torba' and 'gaua' not in k for k in by_id if k.startswith('vu:area_council:') and ('central-torba' in k or 'northern-torba' in k or 'southern-torba' in k)))
check('torba-codeless', all(by_id.get('vu:area_council:' + s, {}).get('code') == '' for s in
      ['gaua', 'merelava', 'mota', 'motalava', 'torres', 'ureparapara', 'vanua-lava']))
check('canal-hyphen', by_id.get('vu:area_council:canal-fanafo', {}).get('name') == 'Canal-Fanafo')
print('ALL PASS' if not fails else 'FAILURES: ' + ','.join(fails))
sys.exit(1 if fails else 0)

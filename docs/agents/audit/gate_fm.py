import csv, sys
# Micronesia gate. B10 revisit: verify-only. Tree 4 states
# (ISO 3166-2:FM TRK/KSA/PNI/YAP) + 75 municipalities (Chuuk
# 40, Kosrae 4, Pohnpei 11, Yap 20) pinned name-by-name vs the
# en-wp admin-divisions table; 4 US ZIPs pinned vs GeoNames
# FM.zip (96941 PNI, 96942 TRK, 96943 YAP, 96944 KSA).
# Holds: Tol stays municipality (WP-bold "city" is a single
# unexplained signal; bundle city = capital towns Weno/Kolonia
# only); Utwe spelling kept (dedicated article "Utwe (or Utwa)"
# + Statoids Utwe; the WP table display "Utwa" is a link quirk).
# Run from repo root:
# python3 docs/agents/audit/gate_fm.py
A = './packages/addressing/resources/geography/micronesia-address-areas.csv'
C = './packages/addressing/resources/geography/micronesia-postal-codes.csv'
L = './packages/addressing/resources/geography/micronesia-postal-code-areas.csv'
STATES = {'fm:state:chuuk': ('Chuuk', 'TRK'),
          'fm:state:kosrae': ('Kosrae', 'KSA'),
          'fm:state:pohnpei': ('Pohnpei', 'PNI'),
          'fm:state:yap': ('Yap', 'YAP')}
MUNI = {
 'fm:state:chuuk': ['Eot', 'Ettal', 'Fananu', 'Fanapanges', 'Fefen',
                    'Fono', 'Houk', 'Kutu', 'Losap', 'Lukunoch', 'Makur',
                    'Moch', 'Murilo', 'Namoluk', 'Nema', 'Nomwin', 'Oneop',
                    'Onou', 'Onoun', 'Paata', 'Parem', 'Piherarh',
                    'Piis-emmwar', 'Piis-paneu', 'Pollap', 'Polle',
                    'Polowat', 'Ramanum', 'Ruo', 'Satawan', 'Tsis', 'Ta',
                    'Tamatam', 'Tol', 'Tonoas', 'Udot', 'Uman', 'Unanu',
                    'Weno', 'Wonei'],
 'fm:state:kosrae': ['Malem', 'Tafunsak', 'Utwe', 'Lelu'],
 'fm:state:pohnpei': ['Kapingamarangi', 'Mwoakilloa', 'Nukuoro',
                      'Pingelap', 'Kitti', 'Kolonia', 'Madolenihmw',
                      'Nett', 'Sokehs', 'U', 'Sapwuahfik'],
 'fm:state:yap': ['Eauripik', 'Elato', 'Fais', 'Faraulap', 'Ifalik',
                  'Lamotrek', 'Ngulu', 'Satawal', 'Ulithi', 'Woleai',
                  'Dalipebinau', 'Fanif', 'Gagil', 'Gilman', 'Kanifay',
                  'Maap', 'Rull', 'Rumung', 'Tomil', 'Weloy']}
ZIPS = {'96941': 'fm:state:pohnpei', '96942': 'fm:state:chuuk',
        '96943': 'fm:state:yap', '96944': 'fm:state:kosrae'}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


raw_a = open(A, 'rb').read()
check('areas-lf', b'\r' not in raw_a)
check('areas-trailing-nl', raw_a.endswith(b'\n'))
areas = list(csv.DictReader(open(A, encoding='utf-8')))
check('areas-79', len(areas) == 79, str(len(areas)))
st = [r for r in areas if r['type'] == 'state']
got_st = {r['source_id']: (r['name'], r['code']) for r in st}
check('states-4', got_st == STATES,
      str({k for k in STATES if got_st.get(k) != STATES[k]}))
l2 = [r for r in areas if r['level'] == '2']
check('l2-75', len(l2) == 75, str(len(l2)))
got = {}
for r in l2:
    got.setdefault(r['parent_source_id'], []).append(r['name'])
bad = {k for k in MUNI if sorted(got.get(k, [])) != sorted(MUNI[k])}
check('muni-xmap', not bad, str(sorted(bad)))
by_id = {r['source_id']: r for r in areas}
check('weno-city', by_id['fm:city:weno']['type'] == 'city')
check('tol-municipality', by_id['fm:municipality:tol']['type'] == 'municipality')
check('kolonia-city', by_id['fm:city:kolonia']['type'] == 'city')
check('utwe-spelling', by_id['fm:municipality:utwe']['name'] == 'Utwe')
codes = [r['code'] for r in csv.DictReader(open(C, encoding='utf-8'))]
check('codes-4', codes == ['96941', '96942', '96943', '96944'], str(codes))
links = [(r['postcode'], r['area_source_id'], r['is_primary']) for r in csv.DictReader(open(L, encoding='utf-8'))]
check('links-4', sorted((p, a) for p, a, _ in links) == sorted(ZIPS.items()))
check('all-primary', all(t == 'true' for _, _, t in links))
print('ALL PASS' if not fails else 'FAILURES: ' + ','.join(fails))
sys.exit(1 if fails else 0)

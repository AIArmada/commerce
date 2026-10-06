import csv, sys
# Marshall Islands gate. B6 revisit: verify-only, zero data
# changes. Tree 26/26 vs ISO 3166-2:MH (chains L/T + 24
# municipalities with trigram codes). Overlay 2/2: 96960 Majuro
# + 96970 Ebeye (Kwajalein) vs the UPU mhl range + GeoNames MH
# localities (GN admin2 "Ailinginae" is centroid noise); outer
# atolls route via the hubs per the overlay row. Run from repo
# root: python3 docs/agents/audit/gate_mh.py
A = './packages/addressing/resources/geography/marshall-islands-address-areas.csv'
C = './packages/addressing/resources/geography/marshall-islands-postal-codes.csv'
L = './packages/addressing/resources/geography/marshall-islands-postal-code-areas.csv'
ISO = {'ALL': 'Ailinglaplap', 'ALK': 'Ailuk', 'ARN': 'Arno',
       'AUR': 'Aur', 'KIL': 'Bikini & Kili', 'EBO': 'Ebon',
       'ENI': 'Enewetak & Ujelang', 'JAB': 'Jabat', 'JAL': 'Jaluit',
       'KWA': 'Kwajalein', 'LAE': 'Lae', 'LIB': 'Lib',
       'LIK': 'Likiep', 'MAJ': 'Majuro', 'MAL': 'Maloelap',
       'MEJ': 'Mejit', 'MIL': 'Mili', 'NMK': 'Namdrik',
       'NMU': 'Namu', 'RON': 'Rongelap', 'UJA': 'Ujae',
       'UTI': 'Utrik', 'WTH': 'Wotho', 'WTJ': 'Wotje'}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


areas = list(csv.DictReader(open(A, newline='')))
check('areas-26', len(areas) == 26, str(len(areas)))
chains = {r['code']: r['name'] for r in areas if r['type'] == 'chain'}
check('chains-2', chains == {'L': 'Ralik', 'T': 'Ratak'}, str(chains))
muns = [r for r in areas if r['type'] == 'municipality']
check('municipalities-24', len(muns) == 24, str(len(muns)))
got = {r['code']: r['name'] for r in muns}
check('iso-24', got == ISO, str({k for k in ISO if got.get(k) != ISO[k]}))
codes = [r['code'] for r in csv.DictReader(open(C, newline=''))]
check('codes-2', set(codes) == {'96960', '96970'}, str(codes))
links = list(csv.DictReader(open(L, newline='')))
check('links-2', len(links) == 2, str(len(links)))
by = {r['postcode']: r['area_source_id'] for r in links}
check('hubs', by == {'96960': 'mh:municipality:majuro',
                     '96970': 'mh:municipality:kwajalein'}, str(by))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES: {fails}')
sys.exit(1 if fails else 0)

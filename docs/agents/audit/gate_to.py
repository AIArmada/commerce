import csv, os, sys
# Tonga gate. B7 revisit: verify-only, zero data changes.
# Tree 28/28: 5 divisions vs ISO 3166-2:TO (TO-01..05; Statoids
# primary name Niuas with Ongo Niua as variant; okina forms match
# WP division titles) + 23 districts vs WP Administrative divisions
# of Tonga district table incl. TO-011..TO-056 codes (WP duplicates
# TO-024 for Ha'ano, a typo; bundled TO-025 sequential is correct).
# No postcode system: UPU ton profile is contact-only with no
# addressing example and GeoNames ships no TO.zip, so no postal
# files exist. Run from repo root:
# python3 docs/agents/audit/gate_to.py
A = './packages/addressing/resources/geography/tonga-address-areas.csv'
C = './packages/addressing/resources/geography/tonga-postal-codes.csv'
L = './packages/addressing/resources/geography/tonga-postal-code-areas.csv'
DIVS = {'01': 'ʻEua', '02': 'Haʻapai', '03': 'Niuas',
        '04': 'Tongatapu', '05': 'Vavaʻu'}
DISTS = {'TO-011': 'ʻEua Motuʻa', 'TO-012': 'ʻEua Foʻou',
         'TO-021': 'Lifuka', 'TO-022': 'Foa', 'TO-023': 'Lulunga',
         'TO-024': 'Muʻomuʻa', 'TO-025': 'Haʻano', 'TO-026': 'ʻUiha',
         'TO-031': 'Niua Toputapu', 'TO-032': 'Niua Foʻou',
         'TO-041': 'Kolofoʻou', 'TO-042': 'Kolomotuʻa', 'TO-043': 'Vaini',
         'TO-044': 'Tatakamotonga', 'TO-045': 'Lapaha',
         'TO-046': 'Nukunuku', 'TO-047': 'Kolovai',
         'TO-051': 'Neiafu', 'TO-052': 'Pangaimotu', 'TO-053': 'Hahake',
         'TO-054': 'Leimatuʻa', 'TO-055': 'Hihifo', 'TO-056': 'Motu'}
PARENTS = {'TO-011': 'to:division:eua', 'TO-012': 'to:division:eua',
           'TO-021': 'to:division:haapai', 'TO-022': 'to:division:haapai',
           'TO-023': 'to:division:haapai', 'TO-024': 'to:division:haapai',
           'TO-025': 'to:division:haapai', 'TO-026': 'to:division:haapai',
           'TO-031': 'to:division:niuas', 'TO-032': 'to:division:niuas',
           'TO-041': 'to:division:tongatapu', 'TO-042': 'to:division:tongatapu',
           'TO-043': 'to:division:tongatapu', 'TO-044': 'to:division:tongatapu',
           'TO-045': 'to:division:tongatapu', 'TO-046': 'to:division:tongatapu',
           'TO-047': 'to:division:tongatapu',
           'TO-051': 'to:division:vavau', 'TO-052': 'to:division:vavau',
           'TO-053': 'to:division:vavau', 'TO-054': 'to:division:vavau',
           'TO-055': 'to:division:vavau', 'TO-056': 'to:division:vavau'}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


raw = open(A, 'rb').read()
check('areas-lf', b'\r' not in raw)
check('areas-trailing-nl', raw.endswith(b'\n'))
areas = list(csv.DictReader(open(A, newline='')))
check('areas-28', len(areas) == 28, str(len(areas)))
divs = {r['code']: r['name'] for r in areas if r['level'] == '1'}
check('divisions-5', divs == DIVS, str({k for k in DIVS if divs.get(k) != DIVS[k]}))
dists = {r['code']: r['name'] for r in areas if r['level'] == '2'}
check('districts-23', dists == DISTS, str({k for k in DISTS if dists.get(k) != DISTS[k]}))
pars = {r['code']: r['parent_source_id'] for r in areas if r['level'] == '2'}
check('parents-23', pars == PARENTS, str({k for k in PARENTS if pars.get(k) != PARENTS[k]}))
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES: {fails}')
sys.exit(1 if fails else 0)

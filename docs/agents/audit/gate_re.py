import csv, sys
# Reunion gate. B7 revisit: fix-and-fill, 97428 phantom out,
# 97490 Sainte-Clotilde in (37 codes / 37 links before and after).
# Hexasmal (La Poste, current) has no 97428 and maps 97490 to
# ST DENIS; GeoNames RE.txt likewise lacks 97428 and carries
# 97490 Saint-Denis; BAN returns zero addresses for 97428 and
# live 97490 Saint-Denis addresses. The WP communes table's
# single 97428 code for Saint-Paul is stale. All 37 links
# primary, exact Hexasmal mapping. Run from repo root:
# python3 docs/agents/audit/gate_re.py
A = './packages/addressing/resources/geography/reunion-address-areas.csv'
C = './packages/addressing/resources/geography/reunion-postal-codes.csv'
L = './packages/addressing/resources/geography/reunion-postal-code-areas.csv'
MAP = {'97400': 're:commune:saint-denis',
       '97410': 're:commune:saint-pierre',
       '97411': 're:commune:saint-paul',
       '97412': 're:commune:bras-panon',
       '97413': 're:commune:cilaos',
       '97414': 're:commune:entre-deux',
       '97416': 're:commune:saint-leu',
       '97417': 're:commune:saint-denis',
       '97418': 're:commune:le-tampon',
       '97419': 're:commune:la-possession',
       '97420': 're:commune:le-port',
       '97421': 're:commune:saint-louis',
       '97422': 're:commune:saint-paul',
       '97423': 're:commune:saint-paul',
       '97424': 're:commune:saint-leu',
       '97425': 're:commune:les-avirons',
       '97426': 're:commune:les-trois-bassins',
       '97427': 're:commune:l-etang-sale',
       '97429': 're:commune:petite-ile',
       '97430': 're:commune:le-tampon',
       '97431': 're:commune:la-plaine-des-palmistes',
       '97432': 're:commune:saint-pierre',
       '97433': 're:commune:salazie',
       '97434': 're:commune:saint-paul',
       '97435': 're:commune:saint-paul',
       '97436': 're:commune:saint-leu',
       '97437': 're:commune:saint-benoit',
       '97438': 're:commune:sainte-marie',
       '97439': 're:commune:sainte-rose',
       '97440': 're:commune:saint-andre',
       '97441': 're:commune:sainte-suzanne',
       '97442': 're:commune:saint-philippe',
       '97450': 're:commune:saint-louis',
       '97460': 're:commune:saint-paul',
       '97470': 're:commune:saint-benoit',
       '97480': 're:commune:saint-joseph',
       '97490': 're:commune:saint-denis'}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


for label, path in (('areas', A), ('codes', C), ('links', L)):
    raw = open(path, 'rb').read()
    check(f'{label}-lf', b'\r' not in raw)
    check(f'{label}-trailing-nl', raw.endswith(b'\n'))
areas = list(csv.DictReader(open(A, newline='')))
check('areas-28', len(areas) == 28, str(len(areas)))
check('districts-4', sum(1 for r in areas if r['level'] == '1') == 4)
check('communes-24', sum(1 for r in areas if r['level'] == '2') == 24)
codes = [r['code'] for r in csv.DictReader(open(C, newline=''))]
check('codes-37', len(codes) == 37, str(len(codes)))
check('codes-set', set(codes) == set(MAP), str(set(codes) ^ set(MAP)))
check('no-97428', '97428' not in codes)
check('has-97490', '97490' in codes)
links = list(csv.DictReader(open(L, newline='')))
check('links-37', len(links) == 37, str(len(links)))
got = {r['postcode']: r['area_source_id'] for r in links}
check('per-code-mapping', got == MAP, str({k for k in MAP if got.get(k) != MAP[k]}))
check('all-primary', all(r['is_primary'] == 'true' for r in links))
covers = {r['area_source_id'] for r in links}
check('communes-covered-24', len(covers) == 24, str(len(covers)))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES: {fails}')
sys.exit(1 if fails else 0)

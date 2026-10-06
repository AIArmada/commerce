import csv, sys
# Martinique gate. B8 revisit: verify-only, zero data changes.
# 30 codes / 35 links: 27 single primaries + 3 shared codes
# exact vs Hexasmal distinct legs (97218 Basse-Pointe/
# Grand-Rivière/Macouba, 97222 Bellefontaine/Case-Pilote,
# 97250 Fonds-Saint-Denis/Le Prêcheur/Saint-Pierre) and
# GeoNames MQ.txt 30/30; WP commune table 34/34 INSEE+names.
# Primaries (Basse-Pointe, Bellefontaine, Saint-Pierre) kept
# per the stability rule — Hexasmal defines no primary.
# Run from repo root:
# python3 docs/agents/audit/gate_mq.py
A = './packages/addressing/resources/geography/martinique-address-areas.csv'
C = './packages/addressing/resources/geography/martinique-postal-codes.csv'
L = './packages/addressing/resources/geography/martinique-postal-code-areas.csv'
MAP = {'97200': [('mq:commune:fort-de-france', 'true')],
       '97211': [('mq:commune:riviere-pilote', 'true')],
       '97212': [('mq:commune:saint-joseph', 'true')],
       '97213': [('mq:commune:gros-morne', 'true')],
       '97214': [('mq:commune:le-lorrain', 'true')],
       '97215': [('mq:commune:riviere-salee', 'true')],
       '97216': [('mq:commune:l-ajoupa-bouillon', 'true')],
       '97217': [('mq:commune:les-anses-d-arlet', 'true')],
       '97218': [('mq:commune:basse-pointe', 'true'),
                 ('mq:commune:grand-riviere', 'false'),
                 ('mq:commune:macouba', 'false')],
       '97220': [('mq:commune:la-trinite', 'true')],
       '97221': [('mq:commune:le-carbet', 'true')],
       '97222': [('mq:commune:bellefontaine', 'true'),
                 ('mq:commune:case-pilote', 'false')],
       '97223': [('mq:commune:le-diamant', 'true')],
       '97224': [('mq:commune:ducos', 'true')],
       '97225': [('mq:commune:le-marigot', 'true')],
       '97226': [('mq:commune:le-morne-vert', 'true')],
       '97227': [('mq:commune:sainte-anne', 'true')],
       '97228': [('mq:commune:sainte-luce', 'true')],
       '97229': [('mq:commune:les-trois-ilets', 'true')],
       '97230': [('mq:commune:sainte-marie', 'true')],
       '97231': [('mq:commune:le-robert', 'true')],
       '97232': [('mq:commune:le-lamentin', 'true')],
       '97233': [('mq:commune:schoelcher', 'true')],
       '97234': [('mq:commune:fort-de-france', 'true')],
       '97240': [('mq:commune:le-francois', 'true')],
       '97250': [('mq:commune:fonds-saint-denis', 'false'),
                 ('mq:commune:le-precheur', 'false'),
                 ('mq:commune:saint-pierre', 'true')],
       '97260': [('mq:commune:le-morne-rouge', 'true')],
       '97270': [('mq:commune:saint-esprit', 'true')],
       '97280': [('mq:commune:le-vauclin', 'true')],
       '97290': [('mq:commune:le-marin', 'true')]}
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
check('areas-38', len(areas) == 38, str(len(areas)))
check('districts-4', sum(1 for r in areas if r['level'] == '1') == 4)
check('communes-34', sum(1 for r in areas if r['level'] == '2') == 34)
codes = [r['code'] for r in csv.DictReader(open(C, newline=''))]
check('codes-30', len(codes) == 30, str(len(codes)))
check('codes-set', set(codes) == set(MAP), str(set(codes) ^ set(MAP)))
links = list(csv.DictReader(open(L, newline='')))
check('links-35', len(links) == 35, str(len(links)))
got = {}
for r in links:
    got.setdefault(r['postcode'], []).append((r['area_source_id'], r['is_primary']))
check('per-code-mapping', got == MAP, str({k for k in MAP if got.get(k) != MAP[k]}))
check('primaries-30', sum(1 for r in links if r['is_primary'] == 'true') == 30)
print('ALL PASS' if not fails else f'{len(fails)} FAILURES: {fails}')
sys.exit(1 if fails else 0)

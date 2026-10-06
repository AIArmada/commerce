import csv, os, sys
# Timor-Leste gate. B10 revisit: areas verify-only (14 L1 incl.
# 2022 Atauro split + 67 admin posts incl. the 3 posts created
# 2024-01-01: Loes, Quelicai Antiga, Matebian); postal stays
# admin-ready (no CSVs): UPU lists TL+5 as live (require-list,
# length 7, format TL99999) but no public allocation table
# exists (prior 2026-09-25 recheck stands; GeoNames TL.zip 404).
# WP's "70 posts" lede is stale — its own table lists 67.
# Atauro correctly has zero admin posts (WP row empty).
# Run from repo root:
# python3 docs/agents/audit/gate_tl.py
A = './packages/addressing/resources/geography/timor-leste-address-areas.csv'
C = './packages/addressing/resources/geography/timor-leste-postal-codes.csv'
L = './packages/addressing/resources/geography/timor-leste-postal-code-areas.csv'
L1 = {'tl:municipality:aileu': ('Aileu', 'AL'),
      'tl:municipality:ainaro': ('Ainaro', 'AN'),
      'tl:municipality:baucau': ('Baucau', 'BA'),
      'tl:municipality:bobonaro': ('Bobonaro', 'BO'),
      'tl:municipality:cova-lima': ('Cova Lima', 'CO'),
      'tl:municipality:dili': ('Dili', 'DI'),
      'tl:municipality:ermera': ('Ermera', 'ER'),
      'tl:municipality:lautem': ('Lautém', 'LA'),
      'tl:municipality:liquica': ('Liquiçá', 'LI'),
      'tl:municipality:manatuto': ('Manatuto', 'MT'),
      'tl:municipality:manufahi': ('Manufahi', 'MF'),
      'tl:special_administrative_region:oecusse': ('Oecusse', 'OE'),
      'tl:municipality:viqueque': ('Viqueque', 'VI'),
      'tl:municipality:atauro': ('Atauro', 'AT')}
POSTS = {
 'tl:municipality:aileu': ['Aileu', 'Laulara', 'Lequidoe', 'Remexio'],
 'tl:municipality:ainaro': ['Ainaro', 'Hato-Udo', 'Hato-Builico', 'Maubisse'],
 'tl:municipality:baucau': ['Baguia', 'Baucau', 'Laga', 'Matebian',
                            'Quelicai', 'Quelicai Antiga', 'Vemasse',
                            'Venilale'],
 'tl:municipality:bobonaro': ['Atabae', 'Balibó', 'Bobonaro', 'Cailaco',
                              'Lolotoe', 'Maliana'],
 'tl:municipality:cova-lima': ['Fatululic', 'Fatumean', 'Fohorem',
                               'Maucatar', 'Suai', 'Tilomar', 'Zumalai'],
 'tl:municipality:dili': ['Cristo Rei', 'Dom Aleixo', 'Metinaro',
                          'Nain Feto', 'Vera Cruz'],
 'tl:municipality:ermera': ['Atsabe', 'Ermera', 'Hatólia', 'Letefoho',
                            'Railaco'],
 'tl:municipality:lautem': ['Iliomar', 'Lautém', 'Lospalos', 'Luro',
                            'Tutuala'],
 'tl:municipality:liquica': ['Bazartete', 'Liquiçá', 'Loes', 'Maubara'],
 'tl:municipality:manatuto': ['Barique', 'Laclo', 'Laclubar', 'Laleia',
                              'Manatuto', 'Soibada'],
 'tl:municipality:manufahi': ['Alas', 'Fatuberlio', 'Same', 'Turiscai'],
 'tl:municipality:viqueque': ['Lacluta', 'Ossu', 'Uato-Lari',
                              'Uatucarbau', 'Viqueque'],
 'tl:special_administrative_region:oecusse': ['Nitibe', 'Oesilo',
                                              'Pante Macassar', 'Passabe']}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


raw_a = open(A, 'rb').read()
check('areas-lf', b'\r' not in raw_a)
check('areas-trailing-nl', raw_a.endswith(b'\n'))
areas = list(csv.DictReader(open(A, encoding='utf-8')))
check('areas-81', len(areas) == 81, str(len(areas)))
l1 = [r for r in areas if r['level'] == '1']
got_l1 = {r['source_id']: (r['name'], r['code']) for r in l1}
check('l1-14', got_l1 == L1,
      str({k for k in L1 if got_l1.get(k) != L1[k]}))
po = [r for r in areas if r['type'] == 'administrative_post']
check('posts-67', len(po) == 67, str(len(po)))
got = {}
for r in po:
    got.setdefault(r['parent_source_id'], []).append(r['name'])
bad = {k for k in POSTS if sorted(got.get(k, [])) != sorted(POSTS[k])}
check('posts-xmap', not bad, str(sorted(bad)))
check('atauro-childless', 'tl:municipality:atauro' not in got)
check('2024-posts', all(any(r['name'] == n for r in po) for n in
      ['Loes', 'Quelicai Antiga', 'Matebian']))
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else 'FAILURES: ' + ','.join(fails))
sys.exit(1 if fails else 0)

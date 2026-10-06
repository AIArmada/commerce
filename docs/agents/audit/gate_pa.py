import csv, os, sys
# Panama gate. B12 revisit: verify-only — tree 10 provinces + 4
# comarcas (incl. Naso Tjer Di, Law 2020, childless like Guna
# Yala) + 81 districts exact vs WP Districts of Panama (name +
# parent + spelling, zero diffs). Postal `none`: UPU PAN sheet
# 02/2015 shows no delivery postcode (home-delivery example
# codeless; 4-digit 0815/0832 numbers are PO Box agency prefixes,
# kept in the street line per doc05) — no CSVs is correct.
# Run from repo root:
# python3 docs/agents/audit/gate_pa.py
A = './packages/addressing/resources/geography/panama-address-areas.csv'
C = './packages/addressing/resources/geography/panama-postal-codes.csv'
L = './packages/addressing/resources/geography/panama-postal-code-areas.csv'
L1 = {'pa:province:bocas-del-toro': ('Bocas del Toro', '1'),
      'pa:province:chiriqui-province': ('Chiriquí Province', '4'),
      'pa:province:cocle': ('Coclé', '2'),
      'pa:province:colon': ('Colón', '3'),
      'pa:province:darien': ('Darién', '5'),
      'pa:province:herrera': ('Herrera', '6'),
      'pa:province:los-santos': ('Los Santos', '7'),
      'pa:province:panama': ('Panamá', '8'),
      'pa:province:panama-oeste': ('Panamá Oeste', '10'),
      'pa:province:veraguas': ('Veraguas', '9'),
      'pa:indigenous_region:embera-wounaan-comarca':
          ('Emberá-Wounaan Comarca', 'EM'),
      'pa:indigenous_region:guna-yala': ('Guna Yala', 'KY'),
      'pa:indigenous_region:naso-tjer-di': ('Naso Tjër Di', 'NT'),
      'pa:indigenous_region:ngabe-bugle-comarca':
          ('Ngäbe-Buglé Comarca', 'NB')}
COUNTS = {'pa:province:bocas-del-toro': 4,
          'pa:province:chiriqui-province': 14,
          'pa:province:cocle': 6,
          'pa:province:colon': 6,
          'pa:province:darien': 3,
          'pa:indigenous_region:embera-wounaan-comarca': 2,
          'pa:province:herrera': 7,
          'pa:province:los-santos': 7,
          'pa:indigenous_region:ngabe-bugle-comarca': 9,
          'pa:province:panama': 6,
          'pa:province:panama-oeste': 5,
          'pa:province:veraguas': 12}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


raw_a = open(A, 'rb').read()
check('areas-lf', b'\r' not in raw_a)
check('areas-trailing-nl', raw_a.endswith(b'\n'))
areas = list(csv.DictReader(open(A, encoding='utf-8')))
check('areas-95', len(areas) == 95, str(len(areas)))
l1 = [r for r in areas if r['level'] == '1']
got_l1 = {r['source_id']: (r['name'], r['code']) for r in l1}
check('l1-14', got_l1 == L1,
      str({k for k in L1 if got_l1.get(k) != L1[k]}))
l2 = [r for r in areas if r['level'] == '2']
check('l2-81', len(l2) == 81, str(len(l2)))
from collections import Counter
cc = Counter(r['parent_source_id'] for r in l2)
check('parent-counts', dict(cc) == COUNTS, str(dict(cc)))
check('guna-childless',
      'pa:indigenous_region:guna-yala' not in set(cc))
check('naso-childless',
      'pa:indigenous_region:naso-tjer-di' not in set(cc))
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else 'FAILURES: ' + ','.join(fails))
sys.exit(1 if fails else 0)

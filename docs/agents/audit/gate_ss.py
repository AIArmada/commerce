import csv, os, sys
# South Sudan gate. B12 revisit: fix-and-record — tree 10 states +
# 84 counties exact vs WP Counties of South Sudan + COD-AB SSD v03
# + commissioner-appointment press + WHO IDSR/OCHA bulletins.
# Deleted 4 rows: See-also scrape junk (Districts of Sudan, States
# of South Sudan), Lopa (Lafon/Lopa is one county per EES govt
# commissioner list + COD-8), Adior (Yirol East payam per 2012
# consultation + 2020 placemat "8 counties" + COD-8).
# Renamed 2: Vertet County -> Verteth (GPAA commissioner list +
# WHO IDSR; suffix dropped per convention), Panrieng -> Pariang
# (COD + HSBA/press; WP self-split Panrieng/Panriang).
# Kept vs oracles: Makal (2021 county/municipality resolution),
# Akoka (WHO-2025/OCHA-2026/commissioner), Bor (article
# present-tense), Raga (WP redirect target), Nasir short form,
# Center spellings, GPAA/Ruweng counties under Jonglei/Unity
# (matches COD-AB admin1 folding; AAs recorded in doc05).
# Postal `none`: WP List of postal codes "no codes" + UPU
# General-Addressing-Issues doc — no CSVs is correct.
# Run from repo root:
# python3 docs/agents/audit/gate_ss.py
A = './packages/addressing/resources/geography/south-sudan-address-areas.csv'
C = './packages/addressing/resources/geography/south-sudan-postal-codes.csv'
L = './packages/addressing/resources/geography/south-sudan-postal-code-areas.csv'
COUNTS = {'ss:state:central-equatoria': 6,
          'ss:state:eastern-equatoria': 8,
          'ss:state:jonglei': 16,
          'ss:state:lakes': 8,
          'ss:state:northern-bahr-el-ghazal': 5,
          'ss:state:unity': 9,
          'ss:state:upper-nile': 13,
          'ss:state:warrap': 6,
          'ss:state:western-bahr-el-ghazal': 3,
          'ss:state:western-equatoria': 10}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


raw_a = open(A, 'rb').read()
check('areas-lf', b'\r' not in raw_a)
check('areas-trailing-nl', raw_a.endswith(b'\n'))
areas = list(csv.DictReader(open(A, encoding='utf-8')))
check('areas-94', len(areas) == 94, str(len(areas)))
l1 = [r for r in areas if r['level'] == '1']
check('l1-10', len(l1) == 10, str(len(l1)))
l2 = [r for r in areas if r['level'] == '2']
check('l2-84', len(l2) == 84, str(len(l2)))
from collections import Counter
cc = Counter(r['parent_source_id'] for r in l2)
check('parent-counts', dict(cc) == COUNTS, str(dict(cc)))
names = {r['name'] for r in l2}
ids = {r['source_id'] for r in l2}
check('no-seealso-junk', 'Districts of Sudan' not in names
      and 'States of South Sudan' not in names)
check('no-lopa-adior', 'Lopa' not in names
      and 'Adior' not in names)
check('verteth', 'Verteth' in names
      and 'Vertet County' not in names
      and 'ss:county:verteth' in ids)
check('pariang', 'Pariang' in names
      and 'Panrieng' not in names
      and 'ss:county:pariang' in ids)
check('makal-akoka', 'Makal' in names and 'Akoka' in names
      and 'Malakal' not in names)
check('bor-raga-nasir', 'Bor' in names and 'Raga' in names
      and 'Nasir' in names and 'Bor South' not in names
      and 'Raja' not in names)
check('pigi-pochalla', 'Pigi' in names
      and 'Pochalla North' in names
      and 'Pochalla South' in names)
check('gpaa-under-jonglei', {'Pibor', 'Gumuruk', 'Lekuangole',
      'Jebel Boma', 'Verteth'} <=
      {r['name'] for r in l2
       if r['parent_source_id'] == 'ss:state:jonglei'})
check('ruweng-under-unity', {'Abiemnom', 'Pariang'} <=
      {r['name'] for r in l2
       if r['parent_source_id'] == 'ss:state:unity'})
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else 'FAILURES: ' + ','.join(fails))
sys.exit(1 if fails else 0)

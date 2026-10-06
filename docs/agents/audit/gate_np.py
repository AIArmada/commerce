import csv, sys
# Nepal gate. B11 revisit: verify-only — tree 7 provinces + 77
# districts exact vs WP per-province roster (federal splits:
# Parasi-as-Nawalparasi-West + Nawalpur, Eastern + Western Rukum);
# postal 753/753 exact vs the OFFICIAL GPO table
# (gpo.gov.np/pages/postal-code-1259614658, 753 rows, per-prefix
# code sets identical, counts identical, transliterations match).
# 2025 federal palika system confirmed live (WP Postal codes in
# Nepal: 1991 system superseded); postcodenepal.com district pages
# agree (6/6 spot blocks). Areas LF; postal files CRLF.
# Run from repo root:
# python3 docs/agents/audit/gate_np.py
A = './packages/addressing/resources/geography/nepal-address-areas.csv'
C = './packages/addressing/resources/geography/nepal-postal-codes.csv'
L = './packages/addressing/resources/geography/nepal-postal-code-areas.csv'
L1 = {'np:province:koshi': ('Koshi', 'P1'),
      'np:province:madhesh': ('Madhesh', 'P2'),
      'np:province:bagmati': ('Bagmati', 'P3'),
      'np:province:gandaki': ('Gandaki', 'P4'),
      'np:province:lumbini': ('Lumbini', 'P5'),
      'np:province:karnali': ('Karnali', 'P6'),
      'np:province:sudurpashchim': ('Sudurpashchim', 'P7')}
COUNTS = {'np:province:koshi': 14, 'np:province:madhesh': 8,
          'np:province:bagmati': 13, 'np:province:gandaki': 11,
          'np:province:lumbini': 12, 'np:province:karnali': 10,
          'np:province:sudurpashchim': 9}
# 3-digit prefix -> (district source_id, GPO code count)
PX = {'101': ('np:district:taplejung', 9),
      '102': ('np:district:sankhuwasabha', 10),
      '103': ('np:district:solukhumbu', 8),
      '104': ('np:district:okhaldhunga', 8),
      '105': ('np:district:khotang', 10),
      '106': ('np:district:bhojpur', 9),
      '107': ('np:district:dhankuta', 7),
      '108': ('np:district:tehrathum', 6),
      '109': ('np:district:panchthar', 8),
      '110': ('np:district:ilam', 10),
      '111': ('np:district:jhapa', 15),
      '112': ('np:district:morang', 17),
      '113': ('np:district:sunsari', 12),
      '114': ('np:district:udayapur', 8),
      '201': ('np:district:saptari', 18),
      '202': ('np:district:siraha', 17),
      '203': ('np:district:dhanusha', 18),
      '204': ('np:district:mahottari', 15),
      '205': ('np:district:sarlahi', 20),
      '206': ('np:district:rautahat', 18),
      '207': ('np:district:bara', 16),
      '208': ('np:district:parsa', 14),
      '301': ('np:district:dolakha', 9),
      '302': ('np:district:sindhupalchok', 12),
      '303': ('np:district:rasuwa', 5),
      '304': ('np:district:dhading', 13),
      '305': ('np:district:nuwakot', 12),
      '306': ('np:district:kathmandu', 11),
      '307': ('np:district:bhaktapur', 4),
      '308': ('np:district:lalitpur', 6),
      '309': ('np:district:kavrepalanchok', 13),
      '310': ('np:district:ramechhap', 8),
      '311': ('np:district:sindhuli', 9),
      '312': ('np:district:makwanpur', 10),
      '313': ('np:district:chitwan', 7),
      '401': ('np:district:gorkha', 11),
      '402': ('np:district:manang', 4),
      '403': ('np:district:mustang', 5),
      '404': ('np:district:myagdi', 6),
      '405': ('np:district:kaski', 5),
      '406': ('np:district:lamjung', 8),
      '407': ('np:district:tanahun', 10),
      '408': ('np:district:nawalpur', 8),
      '409': ('np:district:syangja', 11),
      '410': ('np:district:parbat', 7),
      '411': ('np:district:baglung', 10),
      '501': ('np:district:eastern-rukum', 3),
      '502': ('np:district:rolpa', 10),
      '503': ('np:district:pyuthan', 9),
      '504': ('np:district:gulmi', 12),
      '505': ('np:district:arghakhanchi', 6),
      '506': ('np:district:palpa', 10),
      '507': ('np:district:nawalparasi-west-of-bardaghat-susta', 7),
      '508': ('np:district:rupandehi', 16),
      '509': ('np:district:kapilvastu', 10),
      '510': ('np:district:dang', 10),
      '511': ('np:district:banke', 8),
      '512': ('np:district:bardiya', 8),
      '601': ('np:district:dolpa', 8),
      '602': ('np:district:mugu', 4),
      '603': ('np:district:humla', 7),
      '604': ('np:district:jumla', 8),
      '605': ('np:district:kalikot', 9),
      '606': ('np:district:dailekh', 11),
      '607': ('np:district:jajarkot', 7),
      '608': ('np:district:western-rukum', 6),
      '609': ('np:district:salyan', 10),
      '610': ('np:district:surkhet', 9),
      '701': ('np:district:bajura', 9),
      '702': ('np:district:bajhang', 12),
      '703': ('np:district:darchula', 9),
      '704': ('np:district:baitadi', 10),
      '705': ('np:district:dadeldhura', 7),
      '706': ('np:district:doti', 9),
      '707': ('np:district:achham', 10),
      '708': ('np:district:kailali', 13),
      '709': ('np:district:kanchanpur', 9)}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


raw_a = open(A, 'rb').read()
raw_c = open(C, 'rb').read()
raw_l = open(L, 'rb').read()
check('areas-lf', b'\r' not in raw_a)
check('codes-crlf', raw_c.count(b'\r\n') == 754)
check('links-crlf', raw_l.count(b'\r\n') == 754)
areas = list(csv.DictReader(open(A, encoding='utf-8')))
check('areas-84', len(areas) == 84, str(len(areas)))
l1 = [r for r in areas if r['level'] == '1']
got_l1 = {r['source_id']: (r['name'], r['code']) for r in l1}
check('l1-7', got_l1 == L1,
      str({k for k in L1 if got_l1.get(k) != L1[k]}))
l2 = [r for r in areas if r['level'] == '2']
check('l2-77', len(l2) == 77, str(len(l2)))
from collections import Counter
cc = Counter(r['parent_source_id'] for r in l2)
check('prov-counts', dict(cc) == COUNTS, str(dict(cc)))
codes = list(csv.DictReader(open(C, encoding='utf-8')))
links = list(csv.DictReader(open(L, encoding='utf-8')))
check('codes-753', len(codes) == 753, str(len(codes)))
check('links-753', len(links) == 753, str(len(links)))
check('links-primary',
      all(r['is_primary'] == 'true' for r in links))
check('codes-link-match',
      {r['code'] for r in codes} == {r['postcode'] for r in links})
dprov = {r['source_id']: r['parent_source_id'] for r in l2}
pcode = {r['source_id']: r['code'] for r in l1}
check('digit1-province',
      all(r['postcode'][0] == pcode[dprov[r['area_source_id']]][1]
          for r in links))
got = {}
for r in links:
    got.setdefault(r['postcode'][:3], []).append(r)
bad = [p for p in PX
       if {r['area_source_id'] for r in got.get(p, [])} != {PX[p][0]}
       or len(got.get(p, [])) != PX[p][1]
       or sorted(r['postcode'] for r in got[p]) !=
       [p + '%02d' % i for i in range(1, PX[p][1] + 1)]]
check('prefix-xmap', not bad, str(bad))
check('prefix-count-77', set(got) == set(PX),
      str(set(got) ^ set(PX)))
print('ALL PASS' if not fails else 'FAILURES: ' + ','.join(fails))
sys.exit(1 if fails else 0)

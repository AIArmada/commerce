import csv, sys
from collections import Counter
# India gate. Pins the B20 pass (822 areas: 36 L1 + 786 L2;
# 19238 codes / 19488 legs; areas pure CRLF, postal LF):
# L1 36/36 exact vs ISO 3166-2:IN (DH merger, JK/LA split,
# 2023 codes); L2 vintage ~Jan 2026, ahead of Wikipedia in
# 7 confirmed spots (NL Meluri, DL 13, AP 28, HR Hansi, GA
# Kushavati, KA Bengaluru South, RJ post-rollback 41);
# tree ZERO changes — the 5 notified Ladakh districts are
# HELD OUT per the documented LGD-keying policy (bundle
# keys districts by official LGD code; no LGD codes exist
# yet for Nubra/Sham/Changthang/Zanskar/Drass — worker's
# add verdict overruled, gazette is real but ids would be
# invented). Postal L1 sweep
# 19139/19155 (99.92%): 11 L1 fixes (2 AP/NTR, 9 TN
# restructure) + keeps/HOLD 160014. District legs: 195
# pins / 214 legs replaced (KA Bengaluru 110, AP Godavari
# 16, AP north 11, AS Nagaon 14, 28 singles/multis) +
# 83 missing pins added (81-pin Karimnagar 505 block +
# 605502/607403) = GN universe 19238. Signals: GN vote +
# postpincode/1min office mirrors + mandal maps (pypinindia
# rejected as GN-identical). HOLDS: Delhi full re-leg
# (~103 pins, legs predate Jan-2026 scheme), stale-leg
# pattern (81 zero-leg districts, pre-bifurcation
# parents), single pins (160014 etc.), KA Rural->North
# rename watch.
# Asserts verified state; run from repo root:
# python3 docs/agents/audit/gate_in.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
G = f'{ROOT}/packages/addressing/resources/geography'
A = f'{G}/india-address-areas.csv'
C = f'{G}/india-postal-codes.csv'
L = f'{G}/india-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
raw_a = open(A, 'rb').read()
check('areas-pure-CRLF', raw_a.count(b'\r\n') == raw_a.count(b'\n') == 823, str(raw_a.count(b'\r\n')))
for tag, p, n in [('codes', C, 19239), ('legs', L, 19489)]:
    raw = open(p, 'rb').read()
    check(f'{tag}-LF-only', b'\r' not in raw)
    check(f'{tag}-lines-{n}', raw.count(b'\n') == n, str(raw.count(b'\n')))
    check(f'{tag}-trailing-LF', raw.endswith(b'\n'))
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
check('areas-822', len(rows) == 822, str(len(rows)))
check('source-ids-unique', len(byid) == len(rows))
check('L1-36', sum(1 for r in rows if r['level'] == '1') == 36)
check('L2-786', sum(1 for r in rows if r['level'] == '2') == 786)
check('parents-resolve', all(r['parent_source_id'] in byid for r in rows if r['level'] == '2'))
check('parents-are-L1', all(byid[r['parent_source_id']]['level'] == '1' for r in rows if r['level'] == '2'))
check('ladakh-2-held', sum(1 for r in rows if r.get('parent_source_id') == 'in:union_territory:ladakh') == 2)
check('ladakh-5-absent', not any(r['name'] in ('Nubra', 'Sham', 'Changthang', 'Zanskar', 'Drass') for r in rows))
codes = [r['code'] for r in csv.DictReader(open(C, newline='', encoding='utf-8'))]
legs = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
check('codes-19238', len(codes) == 19238, str(len(codes)))
check('codes-unique', len(set(codes)) == len(codes))
check('legs-19488', len(legs) == 19488, str(len(legs)))
check('legs-resolve', all(r['area_source_id'] in byid for r in legs))
prim = Counter(r['postcode'] for r in legs if r['is_primary'] == 'true')
check('every-code-linked', set(codes) == {r['postcode'] for r in legs})
check('one-primary-each', all(prim.get(c) == 1 for c in codes))
bycode = {}
for r in legs:
    bycode.setdefault(r['postcode'], []).append((r['area_source_id'], r['is_primary']))
check('l1-521178', bycode['521178'] == [('in:district:749', 'true')])
check('l1-605101', bycode['605101'] == [('in:district:596', 'true')])
check('l1-605106', sorted(bycode['605106']) == [('in:district:570', 'false'), ('in:district:596', 'false'), ('in:district:600', 'true')])
check('l1-605007', sorted(bycode['605007']) == [('in:district:570', 'false'), ('in:district:600', 'true')])
check('ka-560001', bycode['560001'] == [('in:district:525', 'true')])
check('ka-560060', sorted(bycode['560060']) == [('in:district:525', 'true'), ('in:district:526', 'false')])
check('ap-534001', bycode['534001'] == [('in:district:748', 'true')])
check('as-782002', bycode['782002'] == [('in:district:297', 'true')])
check('up-244102', sorted(bycode['244102']) == [('in:district:154', 'false'), ('in:district:171', 'false'), ('in:district:659', 'true')])
check('add-505001', bycode['505001'] == [('in:district:508', 'true')])
check('add-505101', sorted(bycode['505101']) == [('in:district:508', 'false'), ('in:district:686', 'true')])
check('add-605502', sorted(bycode['605502']) == [('in:district:596', 'false'), ('in:district:600', 'true')])
check('dl-110001', bycode['110001'] == [('in:district:79', 'true')])
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

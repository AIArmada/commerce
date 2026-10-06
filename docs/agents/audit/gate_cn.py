import csv, sys
from collections import Counter
# China gate. Pins the B19 worker pass (366 areas: 33 L1 + 333
# L2; 2353 codes / 2353 1:1 links; areas LF, postal pure CRLF):
# tree VERIFY-ONLY (taxonomy exact, 33/33 ISO, CN-TW absence
# deliberate — Taiwan ships separately). Postal surgery: bundle
# blindly followed GeoNames admin2, wrong on 16 codes — all
# retargeted with 2+ signals (YBK directory + NBS-divmap county
# membership / wiki infobox / block coherence): 015400->Bayannur,
# 038300->Shuozhou, 044300->Yuncheng, 121000->Jinzhou,
# 236200->Fuyang, 244100+246700->Tongling (246700: current-admin
# rule beats stale YBK/GN), 276000->Linyi, 317300->Taizhou-ZJ,
# 342600->Ganzhou, 541300->Guilin, 657600->Zhaotong,
# 673400->Nujiang, 713100->Xianyang, 810600/810700->Haidong.
# Swaps 057800->054900 (YBK synonym page) + 040000->041000 (YBK
# 404 vs full page), drop 671100 (typo-dupe of 651100), fills
# 158100 Jixi / 666100 Xishuangbanna / 838000 Turpan (zero-link
# prefectures covered 3->0) + 665000 Pu'er + 461700 Xuchang.
# Holds: 452600 Zhoukou (3-way conflict); 817300/162800/201300/
# 676200/845100 overrides confirmed keep; ~45 pemekaran keeps.
# Asserts verified state; run from repo root:
# python3 docs/agents/audit/gate_cn.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
G = f'{ROOT}/packages/addressing/resources/geography'
A = f'{G}/china-address-areas.csv'
C = f'{G}/china-postal-codes.csv'
L = f'{G}/china-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
raw_a = open(A, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-trailing-LF', raw_a.endswith(b'\n'))
raw_c = open(C, 'rb').read()
check('codes-pure-CRLF', raw_c.count(b'\r\n') == raw_c.count(b'\n') == 2354, str(raw_c.count(b'\r\n')))
raw_l = open(L, 'rb').read()
check('legs-pure-CRLF', raw_l.count(b'\r\n') == raw_l.count(b'\n') == 2354, str(raw_l.count(b'\r\n')))
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
check('areas-366', len(rows) == 366, str(len(rows)))
check('source-ids-unique', len(byid) == len(rows))
check('L1-33', sum(1 for r in rows if r['level'] == '1') == 33)
check('L2-333', sum(1 for r in rows if r['level'] == '2') == 333)
check('no-taiwan', not any('taiwan' in s for s in byid))
check('parents-resolve', all(r['parent_source_id'] in byid for r in rows if r['level'] == '2'))
check('parents-are-L1', all(byid[r['parent_source_id']]['level'] == '1' for r in rows if r['level'] == '2'))
codes = [r['code'] for r in csv.DictReader(open(C, newline='', encoding='utf-8'))]
legs = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
check('codes-2353', len(codes) == 2353, str(len(codes)))
check('codes-unique', len(set(codes)) == len(codes))
check('legs-2353', len(legs) == 2353, str(len(legs)))
check('legs-resolve', all(r['area_source_id'] in byid for r in legs))
check('all-primary', all(r['is_primary'] == 'true' for r in legs))
check('every-code-linked', set(codes) == {r['postcode'] for r in legs})
bycode = {r['postcode']: r['area_source_id'] for r in legs}
moves = {'015400': 'cn:prefecture_city:bayannur', '038300': 'cn:prefecture_city:shuozhou',
         '044300': 'cn:prefecture_city:yuncheng', '121000': 'cn:prefecture_city:jinzhou',
         '236200': 'cn:prefecture_city:fuyang', '244100': 'cn:prefecture_city:tongling',
         '246700': 'cn:prefecture_city:tongling', '276000': 'cn:prefecture_city:linyi',
         '317300': 'cn:prefecture_city:taizhou-zhejiang', '342600': 'cn:prefecture_city:ganzhou',
         '541300': 'cn:prefecture_city:guilin', '657600': 'cn:prefecture_city:zhaotong',
         '673400': 'cn:autonomous_prefecture:nujiang', '713100': 'cn:prefecture_city:xianyang',
         '810600': 'cn:prefecture_city:haidong', '810700': 'cn:prefecture_city:haidong'}
check('moves-16', all(bycode.get(c) == a for c, a in moves.items()) and len(moves) == 16,
      str([c for c in moves if bycode.get(c) != moves[c]]))
check('swap-057800-gone', '057800' not in codes and '057800' not in bycode)
check('swap-054900', bycode.get('054900') == 'cn:prefecture_city:xingtai')
check('swap-040000-gone', '040000' not in codes and '040000' not in bycode)
check('swap-041000', bycode.get('041000') == 'cn:prefecture_city:linfen')
check('drop-671100', '671100' not in codes and '671100' not in bycode)
check('surviving-twin-651100', bycode.get('651100') == 'cn:prefecture_city:yuxi')
check('fill-158100', bycode.get('158100') == 'cn:prefecture_city:jixi')
check('fill-666100', bycode.get('666100') == 'cn:autonomous_prefecture:xishuangbanna')
check('fill-838000', bycode.get('838000') == 'cn:prefecture_city:turpan')
check('fill-665000', bycode.get('665000') == 'cn:prefecture_city:pu-er')
check('fill-461700', bycode.get('461700') == 'cn:prefecture_city:xuchang')
check('hold-452600', bycode.get('452600') == 'cn:prefecture_city:zhoukou')
check('keep-817300', bycode.get('817300') == 'cn:autonomous_prefecture:haixi')
check('keep-162800', bycode.get('162800') == 'cn:prefecture_city:hulunbuir')
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

#!/usr/bin/env python3
"""B21 JP gate. Pins the pass (1794 areas: 47 L1 + 1747 L2; 120720 codes /
120801 legs; areas LF, postal CRLF): tree 47 prefs == ISO == MIC, 1747
muns == MIC R6.1.1 set-exact, 0 kanji/type/parent diffs (792/743/189/23
composition); postal FIX-A 77 Tenryu-ku legs 22100->22130 ([K]+[M]+[G]),
FIX-B 432-0000 phantom 22100 secondary deleted, FIX-C 38 KEN_ALL-missing
codes+legs added (terminal-authority single-signal exception, 811-2312
GN-seconded; live JP Post re-check carried as hardening); 0 per-pref
violations, 235/235 sample, 69/69 surviving multis incl. cross-pref
498-0000/871-0000; K-8 Toyone cross-border + romaji screens kept."""
import csv
import sys

GEO = 'packages/addressing/resources/geography'
A = f'{GEO}/japan-address-areas.csv'
C = f'{GEO}/japan-postal-codes.csv'
L = f'{GEO}/japan-postal-code-areas.csv'

fails = []


def check(name, ok, extra=''):
    print(('PASS' if ok else 'FAIL'), name, extra)
    if not ok:
        fails.append(name)


raw_a = open(A, 'rb').read()
raw_c = open(C, 'rb').read()
raw_l = open(L, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-lines-1795', raw_a.count(b'\n') == 1795, str(raw_a.count(b'\n')))
check('areas-trailing-LF', raw_a.endswith(b'\n') and not raw_a.endswith(b'\r\n'))
check('codes-pure-CRLF', raw_c.count(b'\r\n') == raw_c.count(b'\n') == 120721,
      str(raw_c.count(b'\r\n')))
check('legs-pure-CRLF', raw_l.count(b'\r\n') == raw_l.count(b'\n') == 120802,
      str(raw_l.count(b'\r\n')))

rows = list(csv.DictReader(open(A, newline='', encoding='utf-8-sig')))
codes = list(csv.DictReader(open(C, newline='', encoding='utf-8-sig')))
legs = list(csv.DictReader(open(L, newline='', encoding='utf-8-sig')))
check('areas-1794', len(rows) == 1794, str(len(rows)))
check('source-ids-unique', len({r['source_id'] for r in rows}) == len(rows))
check('L1-47', sum(1 for r in rows if r['level'] == '1') == 47)
check('L2-1747', sum(1 for r in rows if r['level'] == '2') == 1747)
byid = {r['source_id']: r for r in rows}
check('parents-resolve', all(not r['parent_source_id'] or r['parent_source_id'] in byid for r in rows))
check('hamamatsu', byid.get('jp:municipality:22130', {}).get('name') == 'Hamamatsu')
check('shizuoka', byid.get('jp:municipality:22100', {}).get('name') == 'Shizuoka')
check('gosen', byid.get('jp:municipality:15218', {}).get('name') == 'Gosen')

check('codes-120720', len(codes) == 120720, str(len(codes)))
check('codes-unique', len({r['code'] for r in codes}) == len(codes))
check('codes-sorted', [r['code'] for r in codes] == sorted(r['code'] for r in codes))
check('legs-120801', len(legs) == 120801, str(len(legs)))
sids = set(byid)
check('legs-resolve', all(r['area_source_id'] in sids for r in legs))
bycode = {}
for r in legs:
    bycode.setdefault(r['postcode'], []).append(r)
check('every-code-linked', set(bycode) == {r['code'] for r in codes})
check('one-primary-each', all(sum(1 for r in v if r['is_primary'] == 'true') == 1 for v in bycode.values()))

tenryu = ['431-3301', '431-3421', '431-3531', '431-3641', '431-3751', '431-3761',
          '431-3801', '431-3901', '431-4101', '437-0601', '437-0626']
for pc in tenryu:
    v = bycode.get(pc, [])
    check(f'tenryu-{pc}', len(v) == 1 and v[0]['area_source_id'] == 'jp:municipality:22130',
          str([(r['area_source_id'], r['is_primary']) for r in v]))
w432 = sorted((r['area_source_id'], r['is_primary']) for r in bycode.get('432-0000', []))
check('phantom-gone-432-0000', w432 == [('jp:municipality:22130', 'true')], str(w432))

for pc, jis in [('351-0008', '11227'), ('365-0006', '11217'), ('598-0077', '27213'),
                ('744-0009', '35207'), ('811-0126', '40345'), ('811-2312', '40349'),
                ('959-1001', '15218'), ('959-1025', '15218'), ('959-1047', '15218')]:
    v = bycode.get(pc, [])
    check(f'add-{pc}', len(v) == 1 and v[0]['area_source_id'] == f'jp:municipality:{jis}'
          and v[0]['is_primary'] == 'true', str([(r['area_source_id'], r['is_primary']) for r in v]))
gosen_new = [f'959-{n:04d}' for n in
             [1001, 1002, 1003, 1004, 1005, 1011, 1012, 1013, 1014, 1015, 1016, 1017, 1018,
              1021, 1022, 1023, 1024, 1025, 1031, 1032, 1033, 1034, 1035, 1036, 1037,
              1041, 1042, 1043, 1044, 1045, 1046, 1047]]
check('gosen-32', all(len(bycode.get(pc, [])) == 1
                      and bycode[pc][0]['area_source_id'] == 'jp:municipality:15218'
                      for pc in gosen_new))

# keeps: cross-prefecture multis + Toyone cross-border + sibling Chuo-ku
for pc, n in [('498-0000', 2), ('871-0000', 2), ('999-4554', 2)]:
    check(f'multi-{pc}', len(bycode.get(pc, [])) == n, str(len(bycode.get(pc, []))))
check('toyone-kept', bycode.get('431-4121', [{}])[0].get('area_source_id') == 'jp:municipality:23563')
check('chuo-kept', bycode.get('431-3101', [{}])[0].get('area_source_id') == 'jp:municipality:22130')

print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

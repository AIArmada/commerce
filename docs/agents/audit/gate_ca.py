#!/usr/bin/env python3
"""B21 CA gate. Pins the pass (5041 areas; 1663 codes / 1673 legs; areas
LF, postal CRLF): tree L2 == reconstructed SGC 2024 exactly (5028/5028,
zero presence diffs; NB 2023 reform + IRI incorporations + QC code
changes adopted; 18/20 renames adopted; Peigan parse artifact
vindicated), T1 Sambaa K'e U+0092->U+2019 corruption repair; postal
FSA granularity confirmed: G0B + H4Z removed (wiki retired/unassigned
+ StatCan-2021 absent; GN staleness demonstrated), 14 FSA adds (StatCan
pop + wiki-active; legs: R5J Taché via MB news release, R5L/R5M via
opened Wikipedia, R5N/R5P + V7Z-secondary via StatCan DPL parents,
R5R/R5T cell-explicit + homepage/block, J5N/S7A named-target, L3L/T6Y/
S7B/S7C/V7Z cell-lead + sibling unanimity); S7B Corman Park secondary
HELD (MLS snippets only); 14 wiki-only + SGC-2025 delta held."""
import csv
import sys

GEO = 'packages/addressing/resources/geography'
A = f'{GEO}/canada-address-areas.csv'
C = f'{GEO}/canada-postal-codes.csv'
L = f'{GEO}/canada-postal-code-areas.csv'

fails = []


def check(name, ok, extra=''):
    print(('PASS' if ok else 'FAIL'), name, extra)
    if not ok:
        fails.append(name)


raw_a = open(A, 'rb').read()
raw_c = open(C, 'rb').read()
raw_l = open(L, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-lines-5042', raw_a.count(b'\n') == 5042, str(raw_a.count(b'\n')))
check('areas-trailing-LF', raw_a.endswith(b'\n') and not raw_a.endswith(b'\r\n'))
check('no-U+0092', 'Sambaa K\u0092e'.encode('utf-8') not in raw_a)
check('codes-pure-CRLF', raw_c.count(b'\r\n') == raw_c.count(b'\n') == 1664,
      str(raw_c.count(b'\r\n')))
check('legs-pure-CRLF', raw_l.count(b'\r\n') == raw_l.count(b'\n') == 1674,
      str(raw_l.count(b'\r\n')))

rows = list(csv.DictReader(open(A, newline='', encoding='utf-8-sig')))
codes = list(csv.DictReader(open(C, newline='', encoding='utf-8-sig')))
legs = list(csv.DictReader(open(L, newline='', encoding='utf-8-sig')))
check('areas-5041', len(rows) == 5041, str(len(rows)))
check('source-ids-unique', len({r['source_id'] for r in rows}) == len(rows))
check('L1-13', sum(1 for r in rows if r['level'] == '1') == 13)
check('L2-5028', sum(1 for r in rows if r['level'] == '2') == 5028)
byid = {r['source_id']: r for r in rows}
check('parents-resolve', all(not r['parent_source_id'] or r['parent_source_id'] in byid for r in rows))
check('sambaa', byid.get('ca:municipality:6104006', {}).get('name') == 'Sambaa K\u2019e')

check('codes-1663', len(codes) == 1663, str(len(codes)))
check('codes-unique', len({r['code'] for r in codes}) == len(codes))
check('codes-sorted', [r['code'] for r in codes] == sorted(r['code'] for r in codes))
check('legs-1673', len(legs) == 1673, str(len(legs)))
sids = set(byid)
check('legs-resolve', all(r['area_source_id'] in sids for r in legs))
bycode = {}
for r in legs:
    bycode.setdefault(r['postcode'], []).append(r)
check('every-code-linked', set(bycode) == {r['code'] for r in codes})
check('one-primary-each', all(sum(1 for r in v if r['is_primary'] == 'true') == 1 for v in bycode.values()))
check('G0B-gone', 'G0B' not in bycode and 'G0B' not in {r['code'] for r in codes})
check('H4Z-gone', 'H4Z' not in bycode and 'H4Z' not in {r['code'] for r in codes})

singles = {'J5N': 'ca:municipality:2473035', 'L3L': 'ca:municipality:3519028',
           'R5J': 'ca:municipality:4602069', 'R5L': 'ca:municipality:4612047',
           'R5M': 'ca:municipality:4612047', 'R5N': 'ca:municipality:4612047',
           'R5P': 'ca:municipality:4612047', 'R5R': 'ca:municipality:4612047',
           'R5T': 'ca:municipality:4612047', 'S7A': 'ca:municipality:4715019',
           'S7B': 'ca:municipality:4711066', 'S7C': 'ca:municipality:4711066',
           'T6Y': 'ca:municipality:4811061'}
for pc, tgt in singles.items():
    v = bycode.get(pc, [])
    check(f'add-{pc}', len(v) == 1 and v[0]['area_source_id'] == tgt
          and v[0]['is_primary'] == 'true',
          str([(r['area_source_id'], r['is_primary']) for r in v]))
w = sorted((r['area_source_id'], r['is_primary']) for r in bycode.get('V7Z', []))
check('add-V7Z', w == [('ca:municipality:5929011', 'true'),
                       ('ca:municipality:5929022', 'false')], str(w))

print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

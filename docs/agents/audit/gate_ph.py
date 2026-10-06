#!/usr/bin/env python3
"""B22 PH gate. Pins the pass (43750 areas: 83 L1 + 1656 L2 + 42011 L3;
1919 codes / 1919 legs; all LF): 3 tree renames (PSGC-literal Santo
Nino x2 + Barangay ng mga Mangingisda); postal 15 drops (0905 big-user
+ 4 old-Cebu + 4532 stale + 9 retired DavOro 81xx) + 2 relegs
(9610/9612 MDN->MDS) + 2222 Subic ADD; H1 forward-delta held
(San Rafael present, H1b old forms kept); H2/H3 sub-office keeps;
8016 scope quirk pinned; NCR legs in 1000-1899."""
import csv
import sys

GEO = 'packages/addressing/resources/geography'
A = f'{GEO}/philippines-address-areas.csv'
C = f'{GEO}/philippines-postal-codes.csv'
L = f'{GEO}/philippines-postal-code-areas.csv'

fails = []


def check(name, ok, extra=''):
    print(('PASS' if ok else 'FAIL'), name, extra)
    if not ok:
        fails.append(name)


raw_a = open(A, 'rb').read()
raw_c = open(C, 'rb').read()
raw_l = open(L, 'rb').read()
for nm, raw, n in (('areas', raw_a, 43751), ('codes', raw_c, 1920), ('legs', raw_l, 1920)):
    check(f'{nm}-LF-only', b'\r' not in raw)
    check(f'{nm}-lines-{n}', raw.count(b'\n') == n, str(raw.count(b'\n')))
    check(f'{nm}-trailing-LF', raw.endswith(b'\n'))

rows = list(csv.DictReader(open(A, newline='', encoding='utf-8-sig')))
codes = list(csv.DictReader(open(C, newline='', encoding='utf-8-sig')))
legs = list(csv.DictReader(open(L, newline='', encoding='utf-8-sig')))
check('areas-43750', len(rows) == 43750, str(len(rows)))
check('source-ids-unique', len({r['source_id'] for r in rows}) == len(rows))
check('L1-83', sum(1 for r in rows if r['level'] == '1') == 83)
check('L2-1656', sum(1 for r in rows if r['level'] == '2') == 1656)
check('L3-42011', sum(1 for r in rows if r['level'] == '3') == 42011)
byid = {r['source_id']: r for r in rows}
check('parents-resolve', all(not r['parent_source_id'] or r['parent_source_id'] in byid for r in rows))

for sid, nm in [
        ('ph:barangay:araceli:sto-nino', 'Santo Niño'),
        ('ph:barangay:abra-de-ilog:sta-maria', 'Santa Maria'),
        ('ph:barangay:mangingisda', 'Barangay ng mga Mangingisda')]:
    check(f'ren-{sid.split(":")[-1][:16]}', byid.get(sid, {}).get('name') == nm, sid)

check('codes-1919', len(codes) == 1919, str(len(codes)))
check('codes-unique', len({r['code'] for r in codes}) == len(codes))
check('codes-sorted', [r['code'] for r in codes] == sorted(r['code'] for r in codes))
check('legs-1919', len(legs) == 1919, str(len(legs)))
sids = set(byid)
check('legs-resolve', all(r['area_source_id'] in sids for r in legs))
bycode = {}
for r in legs:
    bycode.setdefault(r['postcode'], []).append(r)
ccodes = {r['code'] for r in codes}
check('legs-cover-all', set(bycode) == ccodes)
check('one-leg-each', all(len(v) == 1 for v in bycode.values()))
check('all-primary-served', all(v[0]['is_primary'] == 'true' and v[0]['relationship_type'] == 'served_by'
                                for v in bycode.values()))

for pc in ['0905', '1099', '2566', '4532', '6088', '6099', '8103', '8108',
            '8109', '8110', '8111', '8114', '8115', '8116', '8117']:
    check(f'drop-{pc}', pc not in ccodes and pc not in bycode)
for pc, tgt in [('9610', 'ph:province:maguindanao-del-sur'),
                ('9612', 'ph:province:maguindanao-del-sur'),
                ('2222', 'ph:province:zambales')]:
    v = bycode.get(pc, [])
    check(f'leg-{pc}', len(v) == 1 and v[0]['area_source_id'] == tgt, tgt)
    check(f'code-{pc}', pc in ccodes)

# Holds: H1 forward-delta (bundle stays 1Q2025-vintage).
check('H1a-san-rafael-present',
      byid.get('ph:barangay:city-of-calaca:san-rafael', {}).get('name') == 'San Rafael')
for sid, nm in [('ph:barangay:lubusan', 'Lubusan'),
                ('ph:barangay:pinagsakahan', 'Pinagsakahan'),
                ('ph:municipality:sanchez-mira', 'Sanchez-Mira'),
                ('ph:barangay:ambuclao', 'Ambuclao'),
                ('ph:barangay:daclan', 'Daclan')]:
    check(f'H1b-old-{sid.split(":")[-1][:14]}', byid.get(sid, {}).get('name') == nm, sid)
# H2/H3 sub-office keeps + H6 scope quirk.
for pc, tgt in [('1045', 'ph:region:national-capital-region'),
                ('1820', 'ph:province:rizal'),
                ('2333', 'ph:province:tarlac'),
                ('2334', 'ph:province:tarlac'),
                ('5456', 'ph:province:antique'),
                ('4133', 'ph:province:cavite'),
                ('6267', 'ph:province:negros-oriental'),
                ('8016', 'ph:province:davao-del-sur')]:
    v = bycode.get(pc, [])
    check(f'keep-{pc}', len(v) == 1 and v[0]['area_source_id'] == tgt and pc in ccodes)
# All NCR legs within 1000-1899.
ncr = [pc for pc, v in bycode.items() if v[0]['area_source_id'] == 'ph:region:national-capital-region']
check('ncr-range', all('1000' <= pc <= '1899' for pc in ncr), str(len(ncr)))

print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

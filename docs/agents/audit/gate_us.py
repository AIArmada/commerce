#!/usr/bin/env python3
"""B21 US gate. Pins the verify-only pass (3199 areas: 56 L1 + 3143 L2;
40977 codes / 40977 legs; areas LF, postal CRLF): tree 3143/3143 GEOIDs
identical to Census 2024 gazetteer 50-state set, 0 name/type/parent
defects (CT 9 planning regions, VA 38 independent cities, AK 30 incl.
Chugach/Copper River + Kusilvak, Oglala Lakota, LaSalle/De Baca
gazetteer spellings); postal 0 orphans (bundle subset of GN exactly),
0/40977 state-join mismatches, 33226/33642 county-join (residual = CT
legacy vintage + DC L1-design, vindicated); 0 fixes, 8 holds (HUD file
404, USPS Akamai-walled, territory/military/MH scope, 71 county-
ambiguous single-signal, CT town mapping hardening)."""
import csv
import sys

GEO = 'packages/addressing/resources/geography'
A = f'{GEO}/united-states-address-areas.csv'
C = f'{GEO}/united-states-postal-codes.csv'
L = f'{GEO}/united-states-postal-code-areas.csv'

fails = []


def check(name, ok, extra=''):
    print(('PASS' if ok else 'FAIL'), name, extra)
    if not ok:
        fails.append(name)


raw_a = open(A, 'rb').read()
raw_c = open(C, 'rb').read()
raw_l = open(L, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-lines-3200', raw_a.count(b'\n') == 3200, str(raw_a.count(b'\n')))
check('areas-trailing-LF', raw_a.endswith(b'\n') and not raw_a.endswith(b'\r\n'))
check('codes-pure-CRLF', raw_c.count(b'\r\n') == raw_c.count(b'\n') == 40978,
      str(raw_c.count(b'\r\n')))
check('legs-pure-CRLF', raw_l.count(b'\r\n') == raw_l.count(b'\n') == 40978,
      str(raw_l.count(b'\r\n')))

rows = list(csv.DictReader(open(A, newline='', encoding='utf-8-sig')))
codes = list(csv.DictReader(open(C, newline='', encoding='utf-8-sig')))
legs = list(csv.DictReader(open(L, newline='', encoding='utf-8-sig')))
check('areas-3199', len(rows) == 3199, str(len(rows)))
check('source-ids-unique', len({r['source_id'] for r in rows}) == len(rows))
check('L1-56', sum(1 for r in rows if r['level'] == '1') == 56)
check('L2-3143', sum(1 for r in rows if r['level'] == '2') == 3143)
byid = {r['source_id']: r for r in rows}
check('parents-resolve', all(not r['parent_source_id'] or r['parent_source_id'] in byid for r in rows))
check('parents-are-L1', all(byid[r['parent_source_id']]['level'] == '1'
                            for r in rows if r['parent_source_id']))
check('ct-9', sum(1 for r in rows if r['type'] == 'planning_region') == 9)
check('va-133', sum(1 for r in rows if r.get('parent_source_id') == 'us:state:virginia') == 133)
check('tx-254', sum(1 for r in rows if r.get('parent_source_id') == 'us:state:texas') == 254)
check('chugach', byid.get('us:census_area:02063', {}).get('name') == 'Chugach')
check('copper-river', byid.get('us:census_area:02066', {}).get('name') == 'Copper River')
check('kusilvak', byid.get('us:census_area:02158', {}).get('name') == 'Kusilvak')
check('oglala', byid.get('us:county:46102', {}).get('name') == 'Oglala Lakota')
check('lasalle', byid.get('us:parish:22059', {}).get('name') == 'LaSalle')
check('debaca', byid.get('us:county:35011', {}).get('name') == 'De Baca')
check('no-bedford', 'us:city:51515' not in byid)
check('no-dc-county', 'us:county:11001' not in byid)

check('codes-40977', len(codes) == 40977, str(len(codes)))
check('codes-unique', len({r['code'] for r in codes}) == len(codes))
check('legs-40977', len(legs) == 40977, str(len(legs)))
sids = set(byid)
check('legs-resolve', all(r['area_source_id'] in sids for r in legs))
bycode = {}
for r in legs:
    bycode.setdefault(r['postcode'], []).append(r)
check('every-code-linked', set(bycode) == {r['code'] for r in codes})
check('one-primary-each', all(sum(1 for r in v if r['is_primary'] == 'true') == 1 for v in bycode.values()))
check('single-leg-each', all(len(v) == 1 for v in bycode.values()))

for pc, tgt in [('00501', 'us:county:36103'), ('90210', 'us:county:06037'),
                ('99501', 'us:municipality:02020'),
                ('20001', 'us:district:district-of-columbia'),
                ('06001', 'us:planning_region:09110'), ('88134', 'us:county:35011')]:
    got = bycode[pc][0]['area_source_id']
    check(f'anchor-{pc}', got == tgt, got)
for pc in ['00601', '00801', '96799', '96960', '96970', '09001']:
    check(f'scope-absent-{pc}', pc not in bycode)

print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

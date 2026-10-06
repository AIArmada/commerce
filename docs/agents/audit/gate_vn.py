#!/usr/bin/env python3
"""B21 VN gate (verify-only, zero changes). Pins the pass (3355 areas:
34 L1 = 25 province + 9 municipality, 3321 L2 = 2599 commune + 709
ward + 13 special_zone; 3320 codes / 3320 legs; areas LF, postal
CRLF): L1 34/34 vs MOST TOC + Res202 + openapi; L2 counts 34/34 vs
true MOST recount (3321 incl #VALUE!); 3320/3320 legs MOST-correct;
68/68 hand sample. Holds: H1 22 wards (2026 conversions, live
openapi 33/66 vs bundle 45/54 BN), H2 05127 absent + Nghi Duong
unlegged, H3 Hiep Hoa 2-2 spelling, K1 15221 + K8 lam-ong slug."""
import csv
import sys

GEO = 'packages/addressing/resources/geography'
A = f'{GEO}/vietnam-address-areas.csv'
C = f'{GEO}/vietnam-postal-codes.csv'
L = f'{GEO}/vietnam-postal-code-areas.csv'

fails = []


def check(name, ok, extra=''):
    print(('PASS' if ok else 'FAIL'), name, extra)
    if not ok:
        fails.append(name)


raw_a = open(A, 'rb').read()
raw_c = open(C, 'rb').read()
raw_l = open(L, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-lines-3356', raw_a.count(b'\n') == 3356, str(raw_a.count(b'\n')))
check('areas-trailing-LF', raw_a.endswith(b'\n') and not raw_a.endswith(b'\r\n'))
for nm, raw, n in (('codes', raw_c, 3321), ('legs', raw_l, 3321)):
    check(f'{nm}-CRLF-all', raw.count(b'\r\n') == n, str(raw.count(b'\r\n')))
    check(f'{nm}-lines-{n}', raw.count(b'\n') == n, str(raw.count(b'\n')))
    check(f'{nm}-trailing-CRLF', raw.endswith(b'\r\n'))

rows = list(csv.DictReader(open(A, newline='', encoding='utf-8-sig')))
codes = list(csv.DictReader(open(C, newline='', encoding='utf-8-sig')))
legs = list(csv.DictReader(open(L, newline='', encoding='utf-8-sig')))
check('areas-3355', len(rows) == 3355, str(len(rows)))
check('source-ids-unique', len({r['source_id'] for r in rows}) == len(rows))
check('L1-34', sum(1 for r in rows if r['level'] == '1') == 34)
check('L1-prov-25', sum(1 for r in rows if r['level'] == '1' and r['type'] == 'province') == 25)
check('L1-muni-9', sum(1 for r in rows if r['level'] == '1' and r['type'] == 'municipality') == 9)
check('L2-3321', sum(1 for r in rows if r['level'] == '2') == 3321)
check('L2-comm-2599', sum(1 for r in rows if r['type'] == 'commune') == 2599)
check('L2-ward-709', sum(1 for r in rows if r['type'] == 'ward') == 709)
check('L2-spezone-13', sum(1 for r in rows if r['type'] == 'special_zone') == 13)
byid = {r['source_id']: r for r in rows}
check('parents-resolve', all(not r['parent_source_id'] or r['parent_source_id'] in byid for r in rows))
check('L2-parents-L1', all(byid[r['parent_source_id']]['level'] == '1'
                           for r in rows if r['level'] == '2'))

check('codes-3320', len(codes) == 3320, str(len(codes)))
check('codes-unique', len({r['code'] for r in codes}) == len(codes))
check('codes-sorted', [r['code'] for r in codes] == sorted(r['code'] for r in codes))
check('codes-5digit', all(len(r['code']) == 5 and r['code'].isdigit() for r in codes))
check('legs-3320', len(legs) == 3320, str(len(legs)))
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
# Legs point at L2 only.
check('legs-L2-only', all(byid[v[0]['area_source_id']]['level'] == '2' for v in bycode.values()))

# K-pins: Hue-26 inheritance, lam-ong slug kept, 15221 keep, Tam Duong Bac.
check('K7-hue-26', byid.get('vn:municipality:hue', {}).get('code') == '26')
check('K8-lam-ong-slug', byid.get('vn:province:lam-ong', {}).get('name') == 'Lâm Đồng')
check('K1-15221', bycode.get('15221', [{}])[0].get('area_source_id') == 'vn:commune:tam-duong-bac')
# H2: 05127 absent, Nghi Duong present but unlegged.
check('H2-05127-absent', '05127' not in ccodes and '05127' not in bycode)
check('H2-nghi-duong-unlegged',
      'vn:commune:nghi-duong' in sids
      and all(v[0]['area_source_id'] != 'vn:commune:nghi-duong' for v in bycode.values()))
# H3: Hiep Hoa 2-2 split keeps bundle oà.
check('H3-hiep-hoa', byid.get('vn:ward:bac-ninh:hiep-hoa', {}).get('name') == 'Hiệp Hoà')
# H1: the 22 provisionally-kept 2026 wards stay wards (spot: BN 12 + DN 10 counts).
bn_wards = [r for r in rows if r['parent_source_id'] == 'vn:municipality:bac-ninh' and r['type'] == 'ward']
dn_wards = [r for r in rows if r['parent_source_id'] == 'vn:municipality:dong-nai' and r['type'] == 'ward']
check('H1-bn-45w', len(bn_wards) == 45, str(len(bn_wards)))
check('H1-dn-33w', len(dn_wards) == 33, str(len(dn_wards)))
for sid in ['vn:ward:bac-ninh:hiep-hoa', 'vn:ward:bo-ha', 'vn:ward:kep']:
    if sid in sids:
        check(f'H1-ward-{sid.split(":")[-1][:12]}', byid[sid]['type'] == 'ward')

print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

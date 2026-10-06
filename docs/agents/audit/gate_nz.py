#!/usr/bin/env python3
"""B21 NZ gate. Pins the pass (1313 areas: 17 L1 + 67 L2 + 1229 L3;
1769 codes / 1769 legs; all LF): tree L1 17/17 ISO (incl. CIT), L2
67/67 TA (53 district + 12 city + 2 council); 6 renames (Manawatu-
Whanganui macron, Chatham Islands Territory, Whanganui x2, Feilding/
Hastings case) + 28 L3 appends (13 homonym splits + 15 suburbs);
postal 32 onzp-street adds (0 drops; wiki ~87 lobby gap held) + 23
retargets (21 homonym side-fixes + Nukuhau phantom + doubly-wrong
Waipawa); 4180 code-only HELD (no 2-signal leg); 1081 dual kept;
3384 Wairakei over Oruanui 10-10 tie recorded; K1 homonym keeps."""
import csv
import sys

GEO = 'packages/addressing/resources/geography'
A = f'{GEO}/new-zealand-address-areas.csv'
C = f'{GEO}/new-zealand-postal-codes.csv'
L = f'{GEO}/new-zealand-postal-code-areas.csv'

fails = []


def check(name, ok, extra=''):
    print(('PASS' if ok else 'FAIL'), name, extra)
    if not ok:
        fails.append(name)


raw_a = open(A, 'rb').read()
raw_c = open(C, 'rb').read()
raw_l = open(L, 'rb').read()
for nm, raw, n in (('areas', raw_a, 1314), ('codes', raw_c, 1770), ('legs', raw_l, 1770)):
    check(f'{nm}-LF-only', b'\r' not in raw)
    check(f'{nm}-lines-{n}', raw.count(b'\n') == n, str(raw.count(b'\n')))
    check(f'{nm}-trailing-LF', raw.endswith(b'\n'))

rows = list(csv.DictReader(open(A, newline='', encoding='utf-8-sig')))
codes = list(csv.DictReader(open(C, newline='', encoding='utf-8-sig')))
legs = list(csv.DictReader(open(L, newline='', encoding='utf-8-sig')))
check('areas-1313', len(rows) == 1313, str(len(rows)))
check('source-ids-unique', len({r['source_id'] for r in rows}) == len(rows))
check('L1-17', sum(1 for r in rows if r['level'] == '1') == 17)
check('L2-67', sum(1 for r in rows if r['level'] == '2') == 67)
check('L3-1229', sum(1 for r in rows if r['level'] == '3') == 1229)
byid = {r['source_id']: r for r in rows}
check('parents-resolve', all(not r['parent_source_id'] or r['parent_source_id'] in byid for r in rows))

for sid, nm in [
        ('nz:region:manawatu-whanganui', 'Manawatū-Whanganui'),
        ('nz:special_island_authority:chatham-islands', 'Chatham Islands Territory'),
        ('nz:locality:wanganui', 'Whanganui'),
        ('nz:locality:wanganui-east', 'Whanganui East'),
        ('nz:locality:feilding', 'Feilding'),
        ('nz:locality:hastings', 'Hastings')]:
    check(f'ren-{sid.split(":")[-1][:14]}', byid.get(sid, {}).get('name') == nm, sid)
for sid, par in [
        ('nz:locality:woodhill-whangarei', 'nz:district:whangarei'),
        ('nz:locality:kensington-whangarei', 'nz:district:whangarei'),
        ('nz:locality:meremere-waikato', 'nz:district:waikato'),
        ('nz:locality:hillsborough-new-plymouth', 'nz:district:new-plymouth'),
        ('nz:locality:hillsborough-christchurch', 'nz:city:christchurch'),
        ('nz:locality:tokomaru-horowhenua', 'nz:district:horowhenua'),
        ('nz:locality:waverley-south-taranaki', 'nz:district:south-taranaki'),
        ('nz:locality:waverley-invercargill', 'nz:city:invercargill'),
        ('nz:locality:richmond-tasman', 'nz:district:tasman'),
        ('nz:locality:avondale-marlborough', 'nz:district:marlborough'),
        ('nz:locality:sunnyvale-dunedin', 'nz:city:dunedin'),
        ('nz:locality:ngapuna-dunedin', 'nz:city:dunedin'),
        ('nz:locality:parkvale-carterton', 'nz:district:carterton'),
        ('nz:locality:one-tree-point', 'nz:district:whangarei'),
        ('nz:locality:flat-bush', 'nz:council:auckland'),
        ('nz:locality:hamurana', 'nz:district:rotorua'),
        ('nz:locality:te-puna', 'nz:district:western-bay-of-plenty'),
        ('nz:locality:minden', 'nz:district:western-bay-of-plenty'),
        ('nz:locality:tauwhare', 'nz:district:waikato'),
        ('nz:locality:acacia-bay', 'nz:district:taupo'),
        ('nz:locality:linton', 'nz:city:palmerston-north'),
        ('nz:locality:swannanoa', 'nz:district:waimakariri'),
        ('nz:locality:eyrewell', 'nz:district:waimakariri'),
        ('nz:locality:pegasus', 'nz:district:waimakariri'),
        ('nz:locality:west-melton', 'nz:district:selwyn'),
        ('nz:locality:levels', 'nz:district:timaru'),
        ('nz:locality:tora', 'nz:district:south-wairarapa'),
        ('nz:locality:chatham-islands', 'nz:council:chatham-islands')]:
    check(f'add-{sid.split(":")[-1][:16]}', byid.get(sid, {}).get('parent_source_id') == par, sid)

check('codes-1769', len(codes) == 1769, str(len(codes)))
check('codes-unique', len({r['code'] for r in codes}) == len(codes))
check('codes-sorted', [r['code'] for r in codes] == sorted(r['code'] for r in codes))
check('legs-1769', len(legs) == 1769, str(len(legs)))
sids = set(byid)
check('legs-resolve', all(r['area_source_id'] in sids for r in legs))
bycode = {}
for r in legs:
    bycode.setdefault(r['postcode'], []).append(r)
check('legs-cover-minus-4180', set(bycode) == {r['code'] for r in codes} - {'4180'})
check('4180-legless', '4180' not in bycode and '4180' in {r['code'] for r in codes})
check('one-primary-each', all(sum(1 for r in v if r['is_primary'] == 'true') == 1 for v in bycode.values()))

for pc, tgt in [
        ('0110', 'nz:locality:woodhill-whangarei'), ('0145', 'nz:locality:kensington-whangarei'),
        ('0147', 'nz:locality:woodhill-whangarei'), ('0148', 'nz:locality:woodhill-whangarei'),
        ('2441', 'nz:locality:meremere-waikato'), ('4372', 'nz:locality:hillsborough-new-plymouth'),
        ('4474', 'nz:locality:tokomaru-horowhenua'), ('4510', 'nz:locality:waverley-south-taranaki'),
        ('4544', 'nz:locality:waverley-south-taranaki'), ('4591', 'nz:locality:waverley-south-taranaki'),
        ('4864', 'nz:locality:tokomaru-horowhenua'), ('5782', 'nz:locality:tora'),
        ('5792', 'nz:locality:parkvale-carterton'), ('7020', 'nz:locality:richmond-tasman'),
        ('7050', 'nz:locality:richmond-tasman'), ('7081', 'nz:locality:richmond-tasman'),
        ('7276', 'nz:locality:avondale-marlborough'), ('8023', 'nz:locality:hillsborough-christchurch'),
        ('8246', 'nz:locality:hillsborough-christchurch'), ('9018', 'nz:locality:sunnyvale-dunedin'),
        ('9596', 'nz:locality:ngapuna-dunedin'), ('9598', 'nz:locality:ngapuna-dunedin'),
        ('9810', 'nz:locality:waverley-invercargill')]:
    v = bycode.get(pc, [])
    check(f'r-{pc}', len(v) == 1 and v[0]['area_source_id'] == tgt)
for pc, tgt in [
        ('0118', 'nz:locality:one-tree-point'), ('0204', 'nz:locality:haruru'),
        ('0616', 'nz:locality:hobsonville'), ('0816', 'nz:locality:waitakere'),
        ('0994', 'nz:locality:puhoi'), ('2019', 'nz:locality:flat-bush'),
        ('2124', 'nz:locality:paerata'), ('2402', 'nz:locality:pokeno'),
        ('3096', 'nz:locality:hamurana'), ('3097', 'nz:locality:hamurana'),
        ('3174', 'nz:locality:te-puna'), ('3179', 'nz:locality:minden'),
        ('3180', 'nz:locality:whakamarama'), ('3181', 'nz:locality:whakamarama'),
        ('3286', 'nz:locality:newstead'), ('3287', 'nz:locality:tauwhare'),
        ('3384', 'nz:locality:wairakei'), ('3385', 'nz:locality:acacia-bay'),
        ('4472', 'nz:locality:linton'), ('5582', 'nz:locality:te-horo'),
        ('5583', 'nz:locality:manakau'), ('7475', 'nz:locality:swannanoa'),
        ('7476', 'nz:locality:eyrewell'), ('7477', 'nz:locality:sefton'),
        ('7612', 'nz:locality:pegasus'), ('7615', 'nz:locality:rolleston'),
        ('7618', 'nz:locality:west-melton'), ('7677', 'nz:locality:burnham'),
        ('7678', 'nz:locality:rolleston'), ('7975', 'nz:locality:levels'),
        ('8016', 'nz:locality:chatham-islands')]:
    v = bycode.get(pc, [])
    check(f'a-{pc}', len(v) == 1 and v[0]['area_source_id'] == tgt and v[0]['is_primary'] == 'true')
w = sorted((r['area_source_id'], r['is_primary']) for r in bycode.get('1081', []))
check('dual-1081', w == [('nz:locality:ostend', 'true'), ('nz:locality:surfdale', 'false')], str(w))

print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

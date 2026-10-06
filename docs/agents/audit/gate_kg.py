import csv, re, sys
from collections import Counter
# Kyrgyzstan gate. M2 revisit 2026-10-03: pins the CORRECTED state
# (Aitmatov rename + Jalal-Abad-city retarget + Toguz-Toro/Toktogul fill).
# FAILS on the shipped CSVs until the M2 data changes are applied.
# Run from repo root: python3 docs/agents/audit/gate_kg.py
A = './packages/addressing/resources/geography/kyrgyzstan-address-areas.csv'
C = './packages/addressing/resources/geography/kyrgyzstan-postal-codes.csv'
L = './packages/addressing/resources/geography/kyrgyzstan-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
byid = {r['source_id']: r for r in rows}
check('areas-53', len(rows) == 53, str(len(rows)))
check('L1-9', sum(1 for r in rows if r['level'] == '1') == 9)
check('L2-44', sum(1 for r in rows if r['level'] == '2') == 44)
check('types-7-2-44', sum(1 for r in rows if r['type'] == 'region') == 7
      and sum(1 for r in rows if r['type'] == 'city') == 2
      and sum(1 for r in rows if r['type'] == 'district') == 44)
# ISO 3166-2:KG first level.
iso = {'kg:region:batken': ('Batken', 'B'), 'kg:city:bishkek': ('Bishkek', 'GB'),
       'kg:region:chuy': ('Chuy', 'C'), 'kg:region:issyk-kul': ('Issyk-Kul', 'Y'),
       'kg:region:jalal-abad': ('Jalal-Abad', 'J'), 'kg:region:naryn': ('Naryn', 'N'),
       'kg:city:osh': ('Osh', 'GO'), 'kg:region:osh': ('Osh', 'O'),
       'kg:region:talas': ('Talas', 'T')}
for sid, (nm, cd) in iso.items():
    r = byid.get(sid)
    check(f'iso-{cd}', r and r['name'] == nm and r['code'] == cd
          and r['parent_source_id'] == '', str(r))
# Per-parent district counts (Districts of Kyrgyzstan oracle).
exp_par = {'kg:city:bishkek': 4, 'kg:region:chuy': 8, 'kg:region:issyk-kul': 5,
           'kg:region:naryn': 5, 'kg:region:talas': 4, 'kg:region:batken': 3,
           'kg:region:jalal-abad': 8, 'kg:region:osh': 7}
for par, n in exp_par.items():
    have = [r for r in rows if r['parent_source_id'] == par and r['type'] == 'district']
    check(f'kids-{par.split(":")[-1]}-{n}', len(have) == n, str(len(have)))
check('all-L2-parented', all(r['parent_source_id'] in byid for r in rows if r['level'] == '2'))
# Law-enacted rename: Kara-Buura -> Aitmatov (Law KR 2023-04-10 No. 82).
check('no-kara-buura', 'kg:district:kara-buura' not in byid)
r = byid.get('kg:district:aitmatov')
check('aitmatov', r and r['name'] == 'Aitmatov'
      and r['parent_source_id'] == 'kg:region:talas', str(r))
# Diacritic spellings from the oracle.
for sid, nm in [('kg:district:alamudun', 'Alamüdün'), ('kg:district:chuy', 'Chüy'),
                ('kg:district:jeti-oguz', 'Jeti-Ögüz'), ('kg:district:tup', 'Tüp'),
                ('kg:district:ozgon', 'Özgön')]:
    check(f'name-{sid.split(":")[-1]}', byid.get(sid, {}).get('name') == nm,
          str(byid.get(sid)))
# Postal files: 919 codes / 919 links after the M2 fill.
check('codes-919', len(codes) == 919, str(len(codes)))
check('links-919', len(links) == 919, str(len(links)))
check('distinct-919', len({c['code'] for c in codes}) == 919)
bad = [c['code'] for c in codes if not re.match(r'^72[0-5]\d{3}$', c['code'])]
check('code-format-72xxxx', not bad, str(bad[:3]))
nums = sorted(int(c['code']) for c in codes)
check('range-720000-725032', nums[0] == 720000 and nums[-1] == 725032,
      f'{nums[0]}-{nums[-1]}')
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
check('exactly-one-primary', len(prim) == 919 and all(c == 1 for c in prim.values()))
check('all-served_by', all(l['relationship_type'] == 'served_by' for l in links))
check('codes-links-same-set', {c['code'] for c in codes} == set(prim))
# Per-area block counts (Mapanet leaf table, corrected).
exp = {'kg:city:bishkek': 59, 'kg:city:osh': 12, 'kg:district:ak-suu': 31,
       'kg:district:ak-talaa': 16, 'kg:district:aksy': 17, 'kg:district:ala-buka': 12,
       'kg:district:alamudun': 32, 'kg:district:alay': 16, 'kg:district:aravan': 9,
       'kg:district:at-bashy': 20, 'kg:district:bakay-ata': 9, 'kg:district:batken': 19,
       'kg:district:bazar-korgon': 14, 'kg:district:chatkal': 6,
       'kg:district:chong-alay': 4, 'kg:district:chuy': 27,
       'kg:district:issyk-kul': 29, 'kg:district:jayyl': 27,
       'kg:district:jeti-oguz': 27, 'kg:district:jumgal': 17,
       'kg:district:kadamjay': 31, 'kg:district:aitmatov': 13,
       'kg:district:kara-kulja': 16, 'kg:district:kara-suu': 31,
       'kg:district:kemin': 22, 'kg:district:kochkor': 19, 'kg:district:leylek': 30,
       'kg:district:manas': 6, 'kg:district:moskva': 25, 'kg:district:naryn': 30,
       'kg:district:nookat': 20, 'kg:district:nooken': 19, 'kg:district:ozgon': 26,
       'kg:district:panfilov': 19, 'kg:district:sokuluk': 33, 'kg:district:suzak': 31,
       'kg:district:talas': 18, 'kg:district:toguz-toro': 7,
       'kg:district:toktogul': 22, 'kg:district:tong': 15,
       'kg:district:tup': 19, 'kg:district:ysyk-ata': 33,
       'kg:region:issyk-kul': 7, 'kg:region:jalal-abad': 24}
have = Counter(l['area_source_id'] for l in links)
for sid, n in exp.items():
    check(f'count-{sid.split(":")[-1]}-{n}', have.get(sid, 0) == n,
          str(have.get(sid, 0)))
check('no-kara-buura-links',
      not [l for l in links if l['area_source_id'] == 'kg:district:kara-buura'])
link = {l['postcode']: l['area_source_id'] for l in links}
# Seat anchors (Mapanet leaf + archive.kg + RU-wiki, see verdict.json).
anchors = [('720000', 'kg:city:bishkek'), ('723500', 'kg:city:osh'),
           ('720100', 'kg:district:batken'), ('720200', 'kg:district:kadamjay'),
           ('720400', 'kg:district:leylek'), ('720600', 'kg:district:aksy'),
           ('720700', 'kg:district:ala-buka'), ('720800', 'kg:district:bazar-korgon'),
           ('720900', 'kg:region:jalal-abad'), ('720910', 'kg:region:jalal-abad'),
           ('721000', 'kg:region:jalal-abad'), ('721100', 'kg:district:nooken'),
           ('721200', 'kg:district:nooken'), ('721300', 'kg:district:suzak'),
           ('721400', 'kg:region:jalal-abad'), ('721500', 'kg:district:toguz-toro'),
           ('721600', 'kg:district:toktogul'), ('721700', 'kg:district:chatkal'),
           ('721800', 'kg:district:ak-suu'), ('721900', 'kg:region:issyk-kul'),
           ('722000', 'kg:district:jeti-oguz'), ('722100', 'kg:district:issyk-kul'),
           ('722200', 'kg:district:ak-suu'), ('722300', 'kg:district:tong'),
           ('722400', 'kg:district:tup'), ('722500', 'kg:district:ak-talaa'),
           ('722600', 'kg:district:at-bashy'), ('722700', 'kg:district:jumgal'),
           ('722800', 'kg:district:kochkor'), ('722900', 'kg:district:naryn'),
           ('723000', 'kg:district:alay'), ('723100', 'kg:district:aravan'),
           ('723200', 'kg:district:kara-kulja'), ('723300', 'kg:district:kara-suu'),
           ('723400', 'kg:district:nookat'), ('723600', 'kg:district:ozgon'),
           ('723700', 'kg:district:chong-alay'), ('723800', 'kg:district:bakay-ata'),
           ('723900', 'kg:district:aitmatov'), ('724000', 'kg:district:manas'),
           ('724100', 'kg:district:talas'), ('724200', 'kg:district:chuy'),
           ('724300', 'kg:district:alamudun'), ('724400', 'kg:district:jayyl'),
           ('724500', 'kg:district:kemin'), ('724600', 'kg:district:moskva'),
           ('724700', 'kg:district:panfilov'), ('724800', 'kg:district:sokuluk'),
           ('724915', 'kg:district:chuy'), ('725000', 'kg:district:ysyk-ata')]
for pc, sid in anchors:
    check(f'anchor-{pc}', link.get(pc) == sid, str(link.get(pc)))
# Full runs that must be complete (no holes).
check('toguz-toro-run-721500-06',
      all(link.get(f'72150{i}') == 'kg:district:toguz-toro' for i in range(7)))
check('toktogul-run-721600-21',
      all(link.get(f'7216{i:02d}') == 'kg:district:toktogul' for i in range(22)))
check('jalal-abad-city-run-720900-10',
      all(link.get(f'7209{i:02d}') == 'kg:region:jalal-abad' for i in range(11)))
# Documented gaps stay out (single-source or stale).
for pc in ['715300', '715500', '715511', '722040', '722220', '722620', '722720']:
    check(f'no-stale-{pc}', pc not in link)
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

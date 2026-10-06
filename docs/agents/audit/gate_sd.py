import csv, re, sys
from collections import Counter
# Sudan gate. M6 revisit: 13315 River Nile secondary dropped (SCC
# directory lists 13315 Khartoum-only; OSM boundary at ~16.42N +
# GeoNames admin1 put the whole 16.0-16.3N cluster in Khartoum, and
# the alleged north-side villages are unfound) -> 90 codes / 97
# links, 7 duals. 63314 keeps its Central Darfur secondary
# (Fongfong geocodes Central Darfur). Tree: 18 ISO states + 188
# OCHA districts (Abyei PCA excluded, West Kordofan-parented Abyei
# district ships); East Darfur codeless. Run from repo root:
# python3 docs/agents/audit/gate_sd.py
A = './packages/addressing/resources/geography/sudan-address-areas.csv'
C = './packages/addressing/resources/geography/sudan-postal-codes.csv'
L = './packages/addressing/resources/geography/sudan-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
byid = {r['source_id']: r for r in rows}
# --- tree: 18 states + 188 districts ---
check('areas-206', len(rows) == 206, str(len(rows)))
check('states-18', sum(1 for r in rows if r['type'] == 'state') == 18)
check('districts-188', sum(1 for r in rows if r['type'] == 'district') == 188)
iso = {'sd:state:red-sea': ('Red Sea', 'RS'),
 'sd:state:al-jazirah': ('Al Jazirah', 'GZ'),
 'sd:state:khartoum': ('Khartoum', 'KH'),
 'sd:state:al-qadarif': ('Al Qadarif', 'GD'),
 'sd:state:white-nile': ('White Nile', 'NW'),
 'sd:state:blue-nile': ('Blue Nile', 'NB'),
 'sd:state:northern': ('Northern', 'NO'),
 'sd:state:river-nile': ('River Nile', 'NR'),
 'sd:state:sennar': ('Sennar', 'SI'),
 'sd:state:north-kordofan': ('North Kordofan', 'KN'),
 'sd:state:south-kordofan': ('South Kordofan', 'KS'),
 'sd:state:west-kordofan': ('West Kordofan', 'GK'),
 'sd:state:north-darfur': ('North Darfur', 'DN'),
 'sd:state:south-darfur': ('South Darfur', 'DS'),
 'sd:state:west-darfur': ('West Darfur', 'DW'),
 'sd:state:east-darfur': ('East Darfur', 'DE'),
 'sd:state:central-darfur': ('Central Darfur', 'DC'),
 'sd:state:kassala': ('Kassala', 'KA')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1', str(r))
expect = {'sd:state:al-jazirah': 8, 'sd:state:al-qadarif': 12,
 'sd:state:blue-nile': 7, 'sd:state:central-darfur': 9,
 'sd:state:east-darfur': 9, 'sd:state:kassala': 11,
 'sd:state:khartoum': 7, 'sd:state:north-darfur': 17,
 'sd:state:north-kordofan': 8, 'sd:state:northern': 7,
 'sd:state:red-sea': 10, 'sd:state:river-nile': 7,
 'sd:state:sennar': 7, 'sd:state:south-darfur': 21,
 'sd:state:south-kordofan': 17, 'sd:state:west-darfur': 8,
 'sd:state:west-kordofan': 14, 'sd:state:white-nile': 9}
have = Counter(r['parent_source_id'] for r in rows if r['level'] == '2')
for sid, n in expect.items():
    check(f'districts-{sid.split(":")[-1]}-{n}', have[sid] == n,
          str(have[sid]))
# Abyei PCA row excluded as disputed; the West Kordofan-parented
# Abyei district row ships.
check('abyei-district-ships',
      any(r['name'] == 'Abyei'
          and r['parent_source_id'] == 'sd:state:west-kordofan'
          for r in rows))
check('no-abyei-pca', not any('PCA' in r['name'] for r in rows))
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# --- postal: 90 codes / 97 links, all L1 ---
check('codes-90', len(codes) == 90, str(len(codes)))
check('links-97', len(links) == 97, str(len(links)))
bad = [c['code'] for c in codes if not re.match(r'^\d{5}$', c['code'])]
check('code-format-5n', not bad, str(bad[:3]))
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
l1 = all(byid[l['area_source_id']]['level'] == '1' for l in links)
check('all-links-l1', l1)
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', len(prim) == 90 and not multi,
      f'primaries={len(prim)} multi={multi[:3]}')
counts = Counter(l['postcode'] for l in links)
multis = sorted(pc for pc, c in counts.items() if c > 1)
check('multis-7', multis == ['21115', '25514', '31116', '51111',
      '51113', '52221', '63314'], str(multis))
# --- full dual map (SCC-corroborated primaries) ---
exp = {'21115': [('sd:state:al-jazirah', 'true'),
                 ('sd:state:sennar', 'false')],
 '25514': [('sd:state:blue-nile', 'true'), ('sd:state:sennar', 'false')],
 '31116': [('sd:state:al-qadarif', 'true'),
           ('sd:state:kassala', 'false')],
 '51111': [('sd:state:north-kordofan', 'true'),
           ('sd:state:west-kordofan', 'false')],
 '51113': [('sd:state:north-kordofan', 'true'),
           ('sd:state:west-kordofan', 'false')],
 '52221': [('sd:state:north-kordofan', 'true'),
           ('sd:state:south-kordofan', 'false')],
 '63314': [('sd:state:west-darfur', 'true'),
           ('sd:state:central-darfur', 'false')]}
for pc, legs in exp.items():
    got = sorted((l['area_source_id'], l['is_primary']) for l in links
                 if l['postcode'] == pc)
    check(f'dual-{pc}', got == sorted(legs), str(got))
# M6 fix: 13315 is Khartoum-single (UPU/SCC anchor 11111 Khartoum).
got13315 = sorted((l['area_source_id'], l['is_primary']) for l in links
                  if l['postcode'] == '13315')
check('13315-khartoum-single', got13315 == [('sd:state:khartoum', 'true')],
      str(got13315))
check('upu-11111-khartoum',
      [l['area_source_id'] for l in links if l['postcode'] == '11111']
      == ['sd:state:khartoum'])
# East Darfur codeless in every source; other 17 states covered.
linked = {l['area_source_id'] for l in links}
check('east-darfur-codeless', 'sd:state:east-darfur' not in linked)
check('states-covered-17', len(linked) == 17, str(len(linked)))
# --- per-state primary counts ---
pexp = {'sd:state:al-jazirah': 11, 'sd:state:al-qadarif': 7,
 'sd:state:blue-nile': 5, 'sd:state:kassala': 7,
 'sd:state:khartoum': 6, 'sd:state:north-darfur': 3,
 'sd:state:north-kordofan': 7, 'sd:state:northern': 8,
 'sd:state:red-sea': 8, 'sd:state:river-nile': 4,
 'sd:state:sennar': 5, 'sd:state:south-darfur': 3,
 'sd:state:south-kordofan': 4, 'sd:state:west-darfur': 3,
 'sd:state:west-kordofan': 6, 'sd:state:white-nile': 3}
phave = Counter(l['area_source_id'] for l in links
                if l['is_primary'] == 'true')
for sid, n in pexp.items():
    check(f'primaries-{sid.split(":")[-1]}-{n}', phave[sid] == n,
          str(phave[sid]))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

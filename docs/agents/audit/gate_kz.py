import csv, re, sys
from collections import Counter
# Kazakhstan gate. M6 revisit: 9 Almaty-region districts reparented
# kz:city:almaty -> kz:region:almaty (Districts-of-Kazakhstan list +
# GeoNames ADM2 admin1=01 Almaty Oblysy; the name collision hid the
# misparenting) + 070209 Tarbagatai east-kazakhstan -> abai (Ayagoz
# 0702-block singleton; WPC Ayagoz page + GN PPL admin1 Abai). Tree
# 17 regions + 3 cities + 170 districts; postal 2525/2525 L1
# singletons. Full WPC re-scrape (4,172 codes, 185 towns) confirms
# the shipped set; artefact 70/71/72/79 series stay out; Shymkent
# city + Zhezkazgan/Satpayev gaps documented, not filled. Run from
# repo root: python3 docs/agents/audit/gate_kz.py
A = './packages/addressing/resources/geography/kazakhstan-address-areas.csv'
C = './packages/addressing/resources/geography/kazakhstan-postal-codes.csv'
L = './packages/addressing/resources/geography/kazakhstan-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
byid = {r['source_id']: r for r in rows}
# --- tree: 17 regions + 3 cities + 170 districts ---
check('areas-190', len(rows) == 190, str(len(rows)))
check('regions-17', sum(1 for r in rows if r['type'] == 'region') == 17)
check('cities-3', sum(1 for r in rows if r['type'] == 'city') == 3)
check('districts-170', sum(1 for r in rows if r['type'] == 'district') == 170)
iso = {'kz:region:abai': ('Abai', '10'),
 'kz:region:akmola': ('Akmola', '11'),
 'kz:region:aktobe': ('Aktobe', '15'),
 'kz:region:almaty': ('Almaty', '19'),
 'kz:city:almaty': ('Almaty', '75'),
 'kz:city:astana': ('Astana', '71'),
 'kz:region:atyrau': ('Atyrau', '23'),
 'kz:region:east-kazakhstan': ('East Kazakhstan', '63'),
 'kz:region:jambyl': ('Jambyl', '31'),
 'kz:region:jetisu': ('Jetisu', '33'),
 'kz:region:karaganda': ('Karaganda', '35'),
 'kz:region:kostanay': ('Kostanay', '39'),
 'kz:region:kyzylorda': ('Kyzylorda', '43'),
 'kz:region:mangystau': ('Mangystau', '47'),
 'kz:region:north-kazakhstan': ('North Kazakhstan', '59'),
 'kz:region:pavlodar': ('Pavlodar', '55'),
 'kz:city:shymkent': ('Shymkent', '79'),
 'kz:region:turkistan': ('Turkistan', '61'),
 'kz:region:ulytau': ('Ulytau', '62'),
 'kz:region:west-kazakhstan': ('West Kazakhstan', '27')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1', str(r))
# Per-parent district counts by source_id (M6 fix pins: the region
# holds 9, the city holds none — a name-keyed diff cannot see this).
pexp = {'kz:region:abai': 10, 'kz:region:akmola': 17,
 'kz:region:aktobe': 12, 'kz:region:almaty': 9, 'kz:city:almaty': 0,
 'kz:city:astana': 0, 'kz:region:atyrau': 7,
 'kz:region:east-kazakhstan': 11, 'kz:region:jambyl': 10,
 'kz:region:jetisu': 8, 'kz:region:karaganda': 7,
 'kz:region:kostanay': 16, 'kz:region:kyzylorda': 7,
 'kz:region:mangystau': 5, 'kz:region:north-kazakhstan': 13,
 'kz:region:pavlodar': 10, 'kz:city:shymkent': 0,
 'kz:region:turkistan': 14, 'kz:region:ulytau': 2,
 'kz:region:west-kazakhstan': 12}
phave = Counter(r['parent_source_id'] for r in rows if r['level'] == '2')
for sid, n in pexp.items():
    tag = sid.replace('kz:', '').replace(':', '-')
    check(f'districts-{tag}-{n}', phave[sid] == n, str(phave[sid]))
for sid in ['kz:district:balkhash', 'kz:district:enbekshikazakh',
            'kz:district:ile', 'kz:district:karasay',
            'kz:district:kegen', 'kz:district:raiymbek',
            'kz:district:talgar', 'kz:district:uygur',
            'kz:district:zhambyl']:
    check(f'reparent-{sid.split(":")[-1]}',
          byid[sid]['parent_source_id'] == 'kz:region:almaty',
          byid[sid]['parent_source_id'])
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# --- postal: 2525 codes / 2525 links, all L1 singletons ---
check('codes-2525', len(codes) == 2525, str(len(codes)))
check('links-2525', len(links) == 2525, str(len(links)))
bad = [c['code'] for c in codes if not re.match(r'^\d{6}$', c['code'])]
check('code-format-6n', not bad, str(bad[:3]))
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
l1 = all(byid[l['area_source_id']]['level'] == '1' for l in links)
check('all-links-l1', l1)
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', len(prim) == 2525 and not multi,
      f'primaries={len(prim)} multi={multi[:3]}')
counts = Counter(l['postcode'] for l in links)
check('zero-multilink', all(c == 1 for c in counts.values()))
# Kazpost PP prefix system: each 2-digit block maps to one legacy
# region; splits only at the 2022 seams.
block = {}
for l in links:
    block.setdefault(l['postcode'][:2], set()).add(l['area_source_id'])
exp = {'01': {'kz:city:astana'}, '02': {'kz:region:akmola'},
 '03': {'kz:region:aktobe'},
 '04': {'kz:region:almaty', 'kz:region:jetisu'},
 '05': {'kz:city:almaty'}, '06': {'kz:region:atyrau'},
 '07': {'kz:region:abai', 'kz:region:east-kazakhstan'},
 '08': {'kz:region:jambyl'}, '09': {'kz:region:west-kazakhstan'},
 '10': {'kz:region:karaganda', 'kz:region:ulytau'},
 '11': {'kz:region:kostanay'}, '12': {'kz:region:kyzylorda'},
 '13': {'kz:region:mangystau'}, '14': {'kz:region:pavlodar'},
 '15': {'kz:region:north-kazakhstan'},
 '16': {'kz:region:turkistan', 'kz:city:shymkent'}}
check('prefix-blocks-16', block == exp,
      str({k: sorted(v) for k, v in block.items() if exp.get(k) != v}))
split = {('04', 'kz:region:jetisu'): 115, ('04', 'kz:region:almaty'): 135,
 ('07', 'kz:region:abai'): 81, ('07', 'kz:region:east-kazakhstan'): 91,
 ('10', 'kz:region:karaganda'): 210, ('10', 'kz:region:ulytau'): 3,
 ('16', 'kz:region:turkistan'): 189, ('16', 'kz:city:shymkent'): 1}
shave = Counter((l['postcode'][:2], l['area_source_id']) for l in links
                if l['postcode'][:2] in ('04', '07', '10', '16'))
for k, n in split.items():
    check(f'split-{k[0]}-{k[1].split(":")[-1]}-{n}', shave[k] == n,
          str(shave[k]))
bycode = {l['postcode']: l['area_source_id'] for l in links}
# UPU KAZ anchors: 010013 + 010000 Astana.
check('upu-010013-astana', bycode.get('010013') == 'kz:city:astana')
check('upu-010000-astana', bycode.get('010000') == 'kz:city:astana')
# Build corrections hold (WPC carries the stale 1218xx/131309).
check('corr-0218xx-akmola',
      all(bycode.get(f'0218{i:02d}') == 'kz:region:akmola'
          for i in range(13)))
check('corr-151309-nkz', bycode.get('151309')
      == 'kz:region:north-kazakhstan')
check('no-stale-1218xx', not any(c['code'].startswith('1218')
                                 for c in codes))
check('no-stale-131309', '131309' not in bycode)
# Nominatim adjudications (Marinogorka wording fixed to Abai).
check('adj-040200-jetisu', bycode.get('040200') == 'kz:region:jetisu')
check('adj-040313-almaty', bycode.get('040313') == 'kz:region:almaty')
check('adj-040213-jetisu', bycode.get('040213') == 'kz:region:jetisu')
check('adj-071005-abai', bycode.get('071005') == 'kz:region:abai')
check('adj-160818-shymkent', bycode.get('160818') == 'kz:city:shymkent')
# M6 flip: 070209 Tarbagatai joins its Ayagoz block in Abai.
check('flip-070209-abai', bycode.get('070209') == 'kz:region:abai')
# Samar split of 0710 (WPC kokpekti page overreaches into Samar).
check('samar-0710-ekz',
      all(bycode.get(c) == 'kz:region:east-kazakhstan' for c in
          ['071006', '071007', '071008', '071010', '071012']))
check('kokpekti-0710-abai',
      all(bycode.get(c) == 'kz:region:abai' for c in
          ['071000', '071001', '071002', '071003', '071004', '071005',
           '071009', '071011', '071013', '071014']))
# Thin spots stay thin (documented gaps, not rot).
check('ulytau-3', sorted(c for c, s in bycode.items()
      if s == 'kz:region:ulytau') == ['100700', '100701', '100702'])
check('shymkent-single', [c for c, s in bycode.items()
      if s == 'kz:city:shymkent'] == ['160818'])
# Artefact series stay out.
check('no-7xxxxx', not any(c['code'][0] == '7' for c in codes))
# --- per-L1 counts ---
lexp = {'kz:region:jambyl': 227, 'kz:region:akmola': 222,
 'kz:region:karaganda': 210, 'kz:region:north-kazakhstan': 203,
 'kz:region:turkistan': 189, 'kz:region:kostanay': 182,
 'kz:region:aktobe': 168, 'kz:region:pavlodar': 159,
 'kz:region:west-kazakhstan': 156, 'kz:region:kyzylorda': 141,
 'kz:region:almaty': 135, 'kz:region:jetisu': 115,
 'kz:region:atyrau': 105, 'kz:region:east-kazakhstan': 91,
 'kz:region:abai': 81, 'kz:city:almaty': 67,
 'kz:region:mangystau': 48, 'kz:city:astana': 22,
 'kz:region:ulytau': 3, 'kz:city:shymkent': 1}
lhave = Counter(l['area_source_id'] for l in links)
for sid, n in lexp.items():
    check(f'count-{sid.split(":")[-1]}-{n}', lhave[sid] == n,
          str(lhave[sid]))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

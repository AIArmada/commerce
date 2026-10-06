import csv, os, sys
# Saint-Vincent-and-the-Grenadines gate. B2 revisit: moved
# VC0360 Layou saint-patrick -> saint-andrew (Layou infobox parish
# + St Andrew capital claim + GeoNames PPLA/02 + Statoids chief
# town + Mapanet coded filing vs OSM boundary + St Patrick list
# error); zero code/link count changes (56/56). VC0100 held out
# (box-only, Andorra-precedent exclusion) and VC0292 Mesopotamia
# held out (disputed: Photon St George vs GeoNames Charlotte).
# Run from repo root: python3 docs/agents/audit/gate_vc.py
A = './packages/addressing/resources/geography/saint-vincent-and-the-grenadines-address-areas.csv'
C = './packages/addressing/resources/geography/saint-vincent-and-the-grenadines-postal-codes.csv'
L = './packages/addressing/resources/geography/saint-vincent-and-the-grenadines-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A, newline='')))
byid = {r['source_id']: r for r in rows}
check('areas-6', len(rows) == 6, str(len(rows)))
# ISO 3166-2:VC: six parishes, exact code set.
iso = {'vc:parish:charlotte': ('Charlotte', '01'),
       'vc:parish:saint-andrew': ('Saint Andrew', '02'),
       'vc:parish:saint-david': ('Saint David', '03'),
       'vc:parish:saint-george': ('Saint George', '04'),
       'vc:parish:saint-patrick': ('Saint Patrick', '05'),
       'vc:parish:grenadines': ('Grenadines', '06')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['type'] == 'parish' and r['level'] == '1'
          and r['parent_source_id'] == '', str(r))
codes = [r['code'] for r in csv.DictReader(open(C, newline=''))]
links = list(csv.DictReader(open(L, newline='')))
# Code->locality per the SVG official table (zirinsky copy via
# Wayback); locality->parish per Photon OSM counties + GeoNames
# admin1 + parish/village articles + Nominatim, with the two B2
# adjudications (VC0170 keep St Andrew: delivery point is the
# leeward Edinboro/Ottley Hall area, St George mirror filings are
# noise; VC0360 move St Andrew: Layou seat evidence).
SG = 'vc:parish:saint-george'
CH = 'vc:parish:charlotte'
AN = 'vc:parish:saint-andrew'
DA = 'vc:parish:saint-david'
PA = 'vc:parish:saint-patrick'
GR = 'vc:parish:grenadines'
table = {'VC0110': SG, 'VC0120': SG, 'VC0130': SG, 'VC0150': SG,
         'VC0160': SG, 'VC0170': AN, 'VC0200': CH, 'VC0202': CH,
         'VC0204': CH, 'VC0206': CH, 'VC0210': CH, 'VC0212': CH,
         'VC0214': CH, 'VC0216': CH, 'VC0218': CH, 'VC0220': CH,
         'VC0250': CH, 'VC0252': CH, 'VC0254': CH, 'VC0256': CH,
         'VC0258': CH, 'VC0260': CH, 'VC0262': CH, 'VC0264': SG,
         'VC0266': SG, 'VC0270': SG, 'VC0272': SG, 'VC0274': SG,
         'VC0280': SG, 'VC0282': SG, 'VC0284': SG, 'VC0290': SG,
         'VC0294': CH, 'VC0300': DA, 'VC0302': DA, 'VC0310': DA,
         'VC0312': DA, 'VC0314': DA, 'VC0316': DA, 'VC0318': PA,
         'VC0350': PA, 'VC0360': AN, 'VC0370': AN, 'VC0372': AN,
         'VC0374': AN, 'VC0376': AN, 'VC0378': AN, 'VC0380': AN,
         'VC0390': AN, 'VC0400': GR, 'VC0402': GR, 'VC0410': GR,
         'VC0450': GR, 'VC0460': GR, 'VC0470': GR, 'VC0472': GR}
check('codes-56', sorted(codes) == sorted(table), str(sorted(set(codes) ^ set(table))))
check('links-56', len(links) == 56, str(len(links)))
bycode = {r['postcode']: r for r in links}
for pc, sid in table.items():
    r = bycode.get(pc)
    check(f'link-{pc}', bool(r) and r['area_source_id'] == sid
          and r['relationship_type'] == 'served_by'
          and r['is_primary'] == 'true', str(r))
check('held-out-absent', not (set(codes) & {'VC0100', 'VC0292'}),
      str(sorted(set(codes) & {'VC0100', 'VC0292'})))
check('layou-moved', bycode.get('VC0360', {}).get('area_source_id') == AN)
from collections import Counter
counts = Counter(r['area_source_id'] for r in links)
check('per-parish-counts', dict(counts) == {SG: 14, CH: 18, AN: 9, DA: 6, PA: 2, GR: 7}, str(dict(counts)))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

import csv
import sys

# Kiribati gate. B6: 1-cell fix (Canton -> Kanton display name,
# source_id stable) + full 25/25 link verification, zero link changes.
# Oracles: MICTTD official 37-code table via Wayback (Oct-2020 + Jan-2022
# snapshots byte-identical; page 404 since May-2022, live site dead),
# UPU kirEn addressing profile 10/2020, 2020 census Table G-1, ISO
# 3166-2:KI (KI-G/L/P), CLGF 2017-18 Table 20.1a (MISA-sourced).
# Holds: short group names (ISO "Islands" forms are the sole rename
# signal; MICTTD has no group labels, census splits "Gilbert Group" vs
# "Line Islands"); 12 uninhabited-island codes excluded (each uninhabited
# per en-wiki + absent from census G-1; GeoNames has no KI postal export,
# KI.zip 404); CLGF 23+3 = dual-role double count (TUC is the Island
# Council of South Tarawa per 2024 MFMRD usage; Kiritimati single per
# en-wiki/Factbook) -> 24 distinct; census "Teeraina" + CLGF
# "Tabuaran"/"Butariti" variants held out (MICTTD/UPU agree Teraina/
# Tabuaeran/Butaritari). Run from repo root:
# python3 docs/agents/audit/gate_ki.py
A = './packages/addressing/resources/geography/kiribati-address-areas.csv'
C = './packages/addressing/resources/geography/kiribati-postal-codes.csv'
L = './packages/addressing/resources/geography/kiribati-postal-code-areas.csv'
GILBERT, LINE, PHOENIX = ('ki:island:gilbert', 'ki:island:line',
                          'ki:island:phoenix')
K = 'ki:council:'
EXP = {
    'KI0101': K + 'makin', 'KI0102': K + 'butaritari',
    'KI0103': K + 'marakei', 'KI0104': K + 'abaiang',
    'KI0105': K + 'north-tarawa', 'KI0106': K + 'south-tarawa',
    'KI0107': K + 'south-tarawa', 'KI0108': K + 'betio',
    'KI0109': K + 'maiana', 'KI0110': K + 'kuria',
    'KI0111': K + 'aranuka', 'KI0112': K + 'abemama',
    'KI0113': K + 'nonouti', 'KI0114': K + 'north-tabiteuea',
    'KI0115': K + 'south-tabiteuea', 'KI0116': K + 'onotoa',
    'KI0117': K + 'beru', 'KI0118': K + 'nikunau',
    'KI0119': K + 'tamana', 'KI0120': K + 'arorae',
    'KI0121': K + 'banaba', 'KI0201': K + 'canton',
    'KI0301': K + 'teraina', 'KI0302': K + 'tabuaeran',
    'KI0303': K + 'kiritimati',
}
EXCLUDED = (['KI020%d' % n for n in range(2, 9)]
            + ['KI030%d' % n for n in range(4, 9)])
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


areas = list(csv.DictReader(open(A, newline='')))
byid = {r['source_id']: r for r in areas}
l1 = [r for r in areas if r['level'] == '1']
l2 = [r for r in areas if r['level'] == '2']
check('areas-27', len(areas) == 27, str(len(areas)))
check('l1-3', len(l1) == 3)
check('l2-24', len(l2) == 24, str(len(l2)))
check('groups-pinned', [(byid[GILBERT]['name'], byid[GILBERT]['code']),
                        (byid[LINE]['name'], byid[LINE]['code']),
                        (byid[PHOENIX]['name'], byid[PHOENIX]['code'])]
      == [('Gilbert', 'G'), ('Line', 'L'), ('Phoenix', 'P')])
check('islands-hold', all('Islands' not in byid[g]['name']
                          for g in (GILBERT, LINE, PHOENIX)),
      'ISO Islands-forms single-signal; MICTTD has no group labels')
check('gilbert-20', sum(1 for r in l2
                        if r['parent_source_id'] == GILBERT) == 20)
check('line-3', sum(1 for r in l2 if r['parent_source_id'] == LINE) == 3)
check('phoenix-1', sum(1 for r in l2
                       if r['parent_source_id'] == PHOENIX) == 1)
check('banaba-gilbert', byid[K + 'banaba']['parent_source_id'] == GILBERT)
check('tarawa-trio', all(byid[K + c]['parent_source_id'] == GILBERT
                         for c in ('betio', 'north-tarawa', 'south-tarawa')))
check('line-trio', all(byid[K + c]['parent_source_id'] == LINE
                       for c in ('kiritimati', 'tabuaeran', 'teraina')))
check('canton-phoenix', byid[K + 'canton']['parent_source_id'] == PHOENIX)
check('kanton-name', byid[K + 'canton']['name'] == 'Kanton',
      byid[K + 'canton']['name'])
check('no-canton-name', all(r['name'] != 'Canton' for r in areas))
check('orphans-0', all(r['parent_source_id'] in byid for r in l2))
codes = [r['code'] for r in csv.DictReader(open(C, newline=''))]
check('codes-25', len(codes) == 25 and set(codes) == set(EXP),
      str(len(codes)))
check('code-format', all(len(c) == 6 and c.startswith('KI')
                         and c[2:].isdigit() for c in codes))
check('excluded-12-absent', all(c not in codes for c in EXCLUDED),
      'KI0202-0208 + KI0304-0308 uninhabited, no fill case')
links = list(csv.DictReader(open(L, newline='')))
check('links-25', len(links) == 25, str(len(links)))
check('primaries-25', sum(1 for r in links
                          if r['is_primary'] == 'true') == 25)
check('no-dangling', all(r['area_source_id'] in byid for r in links))
got = {}
for r in links:
    got.setdefault(r['postcode'], []).append(
        (r['area_source_id'], r['is_primary']))
bad = [c for c, a in EXP.items() if got.get(c) != [(a, 'true')]]
check('per-code-mapping', not bad and set(got) == set(EXP),
      '25/25' if not bad else str(bad))
check('south-tarawa-dual', sum(1 for r in links if r['area_source_id']
                               == K + 'south-tarawa') == 2)
check('betio-ki0108', got.get('KI0108') == [(K + 'betio', 'true')])
check('canton-ki0201', got.get('KI0201') == [(K + 'canton', 'true')])
check('tabiteuea-pair', got.get('KI0114') == [(K + 'north-tabiteuea', 'true')]
      and got.get('KI0115') == [(K + 'south-tabiteuea', 'true')])
check('christmas-kiritimati', got.get('KI0303')
      == [(K + 'kiritimati', 'true')])
check('coverage-24', {r['area_source_id'] for r in links}
      == {r['source_id'] for r in l2})
print('ALL PASS' if not fails else f'{len(fails)} FAILURES: {fails}')
sys.exit(1 if fails else 0)

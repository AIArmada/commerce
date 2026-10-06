import csv, os, sys
# Tuvalu gate. B2 revisit: kept Nanumanga (GeoNames + en-wiki
# article agree; Nanumaga is the ISO/UPU official form and ships
# as an areaNames alias, QA pattern); zero CSV changes. TUV+3
# postcode system (UPU tuvEn 08/2023) recorded as a gap: no
# complete public directory, so no codes file is bundled.
# Run from repo root: python3 docs/agents/audit/gate_tv.py
A = './packages/addressing/resources/geography/tuvalu-address-areas.csv'
C = './packages/addressing/resources/geography/tuvalu-postal-codes.csv'
L = './packages/addressing/resources/geography/tuvalu-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A, newline='')))
byid = {r['source_id']: r for r in rows}
check('areas-8', len(rows) == 8, str(len(rows)))
# ISO 3166-2:TV: eight councils; Niulakita has no code and is
# administered as part of Niutao (no ninth row).
iso = {'tv:town_council:funafuti': ('Funafuti', 'FUN'),
       'tv:island_council:nanumanga': ('Nanumanga', 'NMG'),
       'tv:island_council:nanumea': ('Nanumea', 'NMA'),
       'tv:island_council:niutao': ('Niutao', 'NIT'),
       'tv:island_council:nui': ('Nui', 'NUI'),
       'tv:island_council:nukufetau': ('Nukufetau', 'NKF'),
       'tv:island_council:nukulaelae': ('Nukulaelae', 'NKL'),
       'tv:island_council:vaitupu': ('Vaitupu', 'VAI')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1' and r['parent_source_id'] == '', str(r))
check('no-niulakita', not [r for r in rows if 'niulakita' in r['source_id']])
# Gap (not verdict none): UPU tuvEn (08/2023) specifies a live
# TUV+3-digit system (TUV150 Vaiaku, TUV120 Fakaifou, TUV710
# Lolua), but no complete public directory exists to bundle.
check('no-codes-file-yet', not os.path.exists(C))
check('no-links-file-yet', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

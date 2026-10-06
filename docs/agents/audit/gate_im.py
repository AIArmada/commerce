import csv, sys
# Isle of Man gate. B6 revisit: verify-only, zero data changes.
# Tree 27/27 vs Local government in the Isle of Man (6 sheadings +
# 21 L2 with types/parents incl. the Garff + Arbory-and-Rushen
# mergers). Overlay 9/27 exact vs the WP IM postcode-area coverage
# + GeoNames IM dump + Photon hamlet checks (IM4 7 areas incl.
# Marown via Braaid/Crosby; IM7 Ballasalla GN row is centroid
# noise — Ballasalla is IM9/Malew, WP IM7 is north-only;
# Stuggadhoo unresolved micro-ambiguity, kept as built on a single
# weak GN row). IM86/87/99 PO-box/large-user excluded (UPU imn
# example uses IM99). Run from repo root:
# python3 docs/agents/audit/gate_im.py
A = './packages/addressing/resources/geography/isle-of-man-address-areas.csv'
C = './packages/addressing/resources/geography/isle-of-man-postal-codes.csv'
L = './packages/addressing/resources/geography/isle-of-man-postal-code-areas.csv'
LINKS = {'IM1': ('im:town:douglas', []), 'IM2': ('im:town:douglas', []),
         'IM3': ('im:district:onchan', []),
         'IM4': ('im:parish:braddan', ['im:parish:marown', 'im:parish:german',
                                       'im:parish:patrick', 'im:parish:garff',
                                       'im:parish:santon',
                                       'im:district:onchan']),
         'IM5': ('im:parish:patrick', ['im:town:peel', 'im:parish:german']),
         'IM6': ('im:district:michael', ['im:parish:german']),
         'IM7': ('im:parish:garff', ['im:parish:andreas',
                                     'im:parish:ballaugh', 'im:parish:bride',
                                     'im:parish:jurby', 'im:parish:lezayre']),
         'IM8': ('im:town:ramsey', []),
         'IM9': ('im:parish:arbory-and-rushen', ['im:parish:malew',
                                                 'im:town:castletown',
                                                 'im:village:port-st-mary',
                                                 'im:village:port-erin'])}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


areas = list(csv.DictReader(open(A, newline='')))
check('areas-27', len(areas) == 27, str(len(areas)))
check('sheadings-6', sum(1 for r in areas if r['type'] == 'sheading') == 6)
check('l2-21', sum(1 for r in areas if r['level'] == '2') == 21)
codes = [r['code'] for r in csv.DictReader(open(C, newline=''))]
check('codes-9', set(codes) == set(LINKS), str(set(codes) ^ set(LINKS)))
check('box-held-out', not any(c in codes for c in ('IM86', 'IM87', 'IM99')))
links = list(csv.DictReader(open(L, newline='')))
check('links-27', len(links) == 27, str(len(links)))
got = {}
for r in links:
    got.setdefault(r['postcode'], []).append((r['area_source_id'], r['is_primary']))
bad = [c for c, (p, s) in LINKS.items()
       if got.get(c) != [(p, 'true')] + [(x, 'false') for x in s]]
check('mapping-9', not bad, str(bad))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES: {fails}')
sys.exit(1 if fails else 0)

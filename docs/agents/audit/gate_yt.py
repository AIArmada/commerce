import csv, sys
# Mayotte gate. B5 revisit: verify-only, zero data changes.
# Tree 17/17 vs Hexasmal INSEE 97601-97617 + GeoNames YT admin2
# (01-17 synthetic; no ISO 3166-2:YT codes). Overlay 11/18 exact
# vs Hexasmal (both sides of all 7 duals; primaries keep bundled
# orientation — Hexasmal lists both communes as acheminement so
# no signal contradicts) + GeoNames YT (modulo 2 Mamoudzou
# centroid-dup rows on 97650/97680, correctly excluded). UPU myt
# confirms 976 department digit. Run from repo root:
# python3 docs/agents/audit/gate_yt.py
A = './packages/addressing/resources/geography/mayotte-address-areas.csv'
C = './packages/addressing/resources/geography/mayotte-postal-codes.csv'
L = './packages/addressing/resources/geography/mayotte-postal-code-areas.csv'
Y = 'yt:commune:'
LINKS = {'97600': (Y + 'mamoudzou', [Y + 'koungou']),
         '97605': (Y + 'mamoudzou', []),
         '97615': (Y + 'dzaoudzi', [Y + 'pamandzi']),
         '97620': (Y + 'chirongui', [Y + 'boueni']),
         '97625': (Y + 'kani-keli', []),
         '97630': (Y + 'mtsamboro', [Y + 'acoua']),
         '97640': (Y + 'sada', []),
         '97650': (Y + 'bandraboua', [Y + 'mtsangamouji']),
         '97660': (Y + 'dembeni', [Y + 'bandrele']),
         '97670': (Y + 'ouangani', [Y + 'chiconi']),
         '97680': (Y + 'tsingoni', [])}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


areas = list(csv.DictReader(open(A, newline='')))
check('areas-17', len(areas) == 17, str(len(areas)))
check('communes-17', all(r['type'] == 'commune' and r['level'] == '1' for r in areas))
codes = [r['code'] for r in csv.DictReader(open(C, newline=''))]
check('codes-11', set(codes) == set(LINKS), str(set(codes) ^ set(LINKS)))
links = list(csv.DictReader(open(L, newline='')))
check('links-18', len(links) == 18, str(len(links)))
got = {}
for r in links:
    got.setdefault(r['postcode'], []).append((r['area_source_id'], r['is_primary']))
bad = [c for c, (p, s) in LINKS.items()
       if sorted(got.get(c, [])) != sorted([(p, 'true')] + [(x, 'false') for x in s])]
check('mapping-11', not bad, str(bad))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES: {fails}')
sys.exit(1 if fails else 0)

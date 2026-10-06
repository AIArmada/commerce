#!/usr/bin/env python3
"""B21 RO gate. Pins the pass (3228 areas: 42 L1 + 3186 L2; 37914 codes /
37914 legs; all LF): tree L1 42/42 ISO codes, L2 3180/3180 UATs vs
SIRUTA-2022, 0 type/parent/county errors, comma-below diacritics; 17
name fixes (SIRUTA + GN-PPLA2/village + ro.wiki triangulated, ADM2==
SIRUTA never double-counted); 15 vindications incl. Petreu Dec-2022
rename; Bucharest exonym HELD (L1 State-row migration needs states.json
+ prune — maintainer decision); postal 0 fixes: bundle == GN export
exactly (same lineage, not proof); mirror partial (Covasna 186/186 +
Ilfov 105/105 set-equal; Arad/Calarasi/Alba shortfalls unresolved);
integrator H3 exhaustive prefix-join 37914/37914 clean; H4 sample held."""
import csv
import sys

GEO = 'packages/addressing/resources/geography'
A = f'{GEO}/romania-address-areas.csv'
C = f'{GEO}/romania-postal-codes.csv'
L = f'{GEO}/romania-postal-code-areas.csv'

fails = []


def check(name, ok, extra=''):
    print(('PASS' if ok else 'FAIL'), name, extra)
    if not ok:
        fails.append(name)


raw_a = open(A, 'rb').read()
raw_c = open(C, 'rb').read()
raw_l = open(L, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-lines-3229', raw_a.count(b'\n') == 3229, str(raw_a.count(b'\n')))
check('codes-LF-only', b'\r' not in raw_c)
check('codes-lines-37915', raw_c.count(b'\n') == 37915, str(raw_c.count(b'\n')))
check('legs-LF-only', b'\r' not in raw_l)
check('legs-lines-37915', raw_l.count(b'\n') == 37915, str(raw_l.count(b'\n')))
check('trailing-LF', raw_a.endswith(b'\n') and raw_c.endswith(b'\n') and raw_l.endswith(b'\n'))

rows = list(csv.DictReader(open(A, newline='', encoding='utf-8-sig')))
codes = list(csv.DictReader(open(C, newline='', encoding='utf-8-sig')))
legs = list(csv.DictReader(open(L, newline='', encoding='utf-8-sig')))
check('areas-3228', len(rows) == 3228, str(len(rows)))
check('source-ids-unique', len({r['source_id'] for r in rows}) == len(rows))
check('L1-42', sum(1 for r in rows if r['level'] == '1') == 42)
check('L2-3186', sum(1 for r in rows if r['level'] == '2') == 3186)
byid = {r['source_id']: r for r in rows}
check('parents-resolve', all(not r['parent_source_id'] or r['parent_source_id'] in byid for r in rows))
check('bucharest-held', byid.get('ro:municipality:bucharest', {}).get('name') == 'Bucharest')

for sid, nm in [
        ('ro:commune:daia-romana', 'Daia Română'),
        ('ro:commune:darmanesti', 'Dârmănești'),
        ('ro:commune:cluj:sanmartin', 'Sânmărtin'),
        ('ro:commune:galautas', 'Gălăuțaș'),
        ('ro:municipality:piatra-neamt', 'Piatra-Neamț'),
        ('ro:town:piatra-olt', 'Piatra-Olt'),
        ('ro:commune:boldesti-gradistea', 'Boldești-Gradiștea'),
        ('ro:commune:sarmasag', 'Șărmășag'),
        ('ro:commune:tulcea:c-a-rosetti', 'C.A. Rosetti'),
        ('ro:commune:i-c-bratianu', 'I.C. Brătianu'),
        ('ro:commune:tulcea:smardan', 'Smârdan'),
        ('ro:commune:selaru-dambovita', 'Șelaru'),
        ('ro:commune:alexandru-ioan-cuza', 'Alexandru I. Cuza'),
        ('ro:commune:padina-mare', 'Pădina'),
        ('ro:municipality:rosiorii-de-vede', 'Roșiori de Vede'),
        ('ro:commune:baidaud', 'Beidaud'),
        ('ro:commune:mihai-kogalniceanu', 'Mihail Kogălniceanu')]:
    check(f'f-{sid.split(":")[-1][:14]}', byid.get(sid, {}).get('name') == nm, sid)
# vindication spot: Petreu Dec-2022 rename adopted
check('petreu-kept', byid.get('ro:commune:petreu', {}).get('name') == 'Petreu')

check('codes-37914', len(codes) == 37914, str(len(codes)))
check('codes-unique', len({r['code'] for r in codes}) == len(codes))
check('legs-37914', len(legs) == 37914, str(len(legs)))
sids = set(byid)
check('legs-resolve', all(r['area_source_id'] in sids for r in legs))
bycode = {}
for r in legs:
    bycode.setdefault(r['postcode'], []).append(r)
check('every-code-linked', set(bycode) == {r['code'] for r in codes})
check('one-primary-each', all(sum(1 for r in v if r['is_primary'] == 'true') == 1 for v in bycode.values()))
check('single-leg-each', all(len(v) == 1 for v in bycode.values()))
check('legs-all-L1', all(byid[r['area_source_id']]['level'] == '1' for r in legs))

print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

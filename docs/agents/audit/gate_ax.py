import csv, sys
# Aland gate. B5 revisit: verify-only, zero data changes.
# Tree 16/16 vs Municipalities of Aland + GeoNames AX admin2 (01-16
# synthetic; AX- prefix on codes per the UPU ala profile: Finland
# requests AX before the postcode). Overlay 33/33 exact vs the
# GeoNames AX dump + Aland Post postcode directory (postboxar labels
# confirm 22101/22111/22411 are PO Box codes; UPU fin rule "home
# delivery ends in 0, PO Box in 1" also excludes GN-only 22151 and
# PostNord-only 22271). Run from repo root:
# python3 docs/agents/audit/gate_ax.py
A = './packages/addressing/resources/geography/aland-address-areas.csv'
C = './packages/addressing/resources/geography/aland-postal-codes.csv'
L = './packages/addressing/resources/geography/aland-postal-code-areas.csv'
MUN = ['Brändö', 'Eckerö', 'Finström', 'Föglö', 'Geta', 'Hammarland',
       'Jomala', 'Kökar', 'Kumlinge', 'Lemland', 'Lumparland',
       'Mariehamn', 'Saltvik', 'Sottunga', 'Sund', 'Vårdö']
LINKS = {'AX-22100': 'ax:municipality:mariehamn',
         'AX-22110': 'ax:municipality:jomala',
         'AX-22120': 'ax:municipality:jomala',
         'AX-22130': 'ax:municipality:jomala',
         'AX-22140': 'ax:municipality:jomala',
         'AX-22150': 'ax:municipality:jomala',
         'AX-22160': 'ax:municipality:lemland',
         'AX-22220': 'ax:municipality:finstrom',
         'AX-22240': 'ax:municipality:hammarland',
         'AX-22270': 'ax:municipality:eckero',
         'AX-22310': 'ax:municipality:finstrom',
         'AX-22320': 'ax:municipality:saltvik',
         'AX-22330': 'ax:municipality:finstrom',
         'AX-22340': 'ax:municipality:geta',
         'AX-22410': 'ax:municipality:finstrom',
         'AX-22430': 'ax:municipality:saltvik',
         'AX-22520': 'ax:municipality:sund',
         'AX-22530': 'ax:municipality:sund',
         'AX-22550': 'ax:municipality:vardo',
         'AX-22610': 'ax:municipality:lemland',
         'AX-22630': 'ax:municipality:lumparland',
         'AX-22710': 'ax:municipality:foglo',
         'AX-22720': 'ax:municipality:sottunga',
         'AX-22730': 'ax:municipality:kokar',
         'AX-22810': 'ax:municipality:kumlinge',
         'AX-22820': 'ax:municipality:kumlinge',
         'AX-22830': 'ax:municipality:kumlinge',
         'AX-22840': 'ax:municipality:brando',
         'AX-22910': 'ax:municipality:brando',
         'AX-22920': 'ax:municipality:brando',
         'AX-22930': 'ax:municipality:brando',
         'AX-22940': 'ax:municipality:brando',
         'AX-22950': 'ax:municipality:brando'}
BOX = ['AX-22101', 'AX-22111', 'AX-22151', 'AX-22271', 'AX-22411']
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


areas = list(csv.DictReader(open(A, newline='')))
check('areas-16', len(areas) == 16, str(len(areas)))
check('names-16', sorted(r['name'] for r in areas) == sorted(MUN))
codes = [r['code'] for r in csv.DictReader(open(C, newline=''))]
check('codes-33', set(codes) == set(LINKS), str(set(codes) ^ set(LINKS)))
check('ax-prefix', all(c.startswith('AX-') for c in codes))
check('end-in-0', all(c.endswith('0') for c in codes))
check('box-held-out', not any(c in codes for c in BOX), str(BOX))
links = list(csv.DictReader(open(L, newline='')))
check('links-33', len(links) == 33, str(len(links)))
ok = all(r['postcode'] in LINKS and r['area_source_id'] == LINKS[r['postcode']]
         and r['is_primary'] == 'true' for r in links)
check('mapping-33', ok)
print('ALL PASS' if not fails else f'{len(fails)} FAILURES: {fails}')
sys.exit(1 if fails else 0)

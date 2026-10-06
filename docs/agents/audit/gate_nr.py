import csv, sys
# Nauru gate. B4 revisit: verify-only, zero data changes.
# Tree 14/14 vs ISO codes + Statoids names. NR-05 stays "Baiti":
# ISO wiki labels it Baitsi but notes Baiti as the local variant,
# Statoids uses Baiti, and the UPU district list is OCR-corrupted
# (Anabare/Denig), so Baitsi has no solid second signal.
# Single code NRU68 vs the UPU nru profile 8.2026 + GeoNames NR
# dump; zero links (district-only addressing). Run from repo root:
# python3 docs/agents/audit/gate_nr.py
A = './packages/addressing/resources/geography/nauru-address-areas.csv'
C = './packages/addressing/resources/geography/nauru-postal-codes.csv'
L = './packages/addressing/resources/geography/nauru-postal-code-areas.csv'
ISO = {'01': 'Aiwo', '02': 'Anabar', '03': 'Anetan', '04': 'Anibare',
       '05': 'Baiti', '06': 'Boe', '07': 'Buada', '08': 'Denigomodu',
       '09': 'Ewa', '10': 'Ijuw', '11': 'Meneng', '12': 'Nibok',
       '13': 'Uaboe', '14': 'Yaren'}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


areas = list(csv.DictReader(open(A, newline='')))
check('areas-14', len(areas) == 14, str(len(areas)))
got = {r['code']: r['name'] for r in areas}
check('iso-14', got == ISO, str({k for k in ISO if got.get(k) != ISO[k]}))
check('baiti-nr05', got.get('05') == 'Baiti', 'Baitsi held out, see header')
check('districts-14', all(r['type'] == 'district' and r['level'] == '1' for r in areas))
codes = [r['code'] for r in csv.DictReader(open(C, newline=''))]
check('codes-nru68', codes == ['NRU68'], str(codes))
links = list(csv.DictReader(open(L, newline='')))
check('links-0', len(links) == 0, str(len(links)))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES: {fails}')
sys.exit(1 if fails else 0)

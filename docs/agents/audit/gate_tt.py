import csv, os, sys
# Trinidad and Tobago gate. B5 revisit: verify-only, zero data
# changes. Tree 15/15 vs ISO 3166-2:TT incl. types (borough/city/
# region/ward). No postal files ship: TT runs a 6-digit postcode
# system (UPU tto 05/2014; 72 postal districts, first-2 = delivery
# office) but no public district directory exists (TTPost finder is
# per-address only; mirrors carry fragments), so no overlay can be
# built (TV TUV-gap precedent). GeoNames has no TT postal export.
# Run from repo root: python3 docs/agents/audit/gate_tt.py
A = './packages/addressing/resources/geography/trinidad-and-tobago-address-areas.csv'
C = './packages/addressing/resources/geography/trinidad-and-tobago-postal-codes.csv'
L = './packages/addressing/resources/geography/trinidad-and-tobago-postal-code-areas.csv'
ISO = {'ARI': ('Arima', 'borough'), 'CHA': ('Chaguanas', 'borough'),
       'CTT': ('Couva-Tabaquite-Talparo', 'region'),
       'DMN': ('Diego Martin', 'borough'),
       'MRC': ('Mayaro-Rio Claro', 'region'), 'PED': ('Penal-Debe', 'region'),
       'PTF': ('Point Fortin', 'borough'),
       'POS': ('Port of Spain', 'city'), 'PRT': ('Princes Town', 'region'),
       'SFO': ('San Fernando', 'city'),
       'SJL': ('San Juan-Laventille', 'region'),
       'SGE': ('Sangre Grande', 'region'), 'SIP': ('Siparia', 'borough'),
       'TOB': ('Tobago', 'ward'), 'TUP': ('Tunapuna-Piarco', 'region')}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


areas = list(csv.DictReader(open(A, newline='')))
check('areas-15', len(areas) == 15, str(len(areas)))
got = {r['code']: (r['name'], r['type']) for r in areas}
check('iso-15', got == ISO, str({k for k in ISO if got.get(k) != ISO[k]}))
check('level-1', all(r['level'] == '1' for r in areas))
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES: {fails}')
sys.exit(1 if fails else 0)

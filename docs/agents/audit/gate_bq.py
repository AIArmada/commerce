import csv, os, sys
# Caribbean-Netherlands (BQ) gate. B1 revisit: verify-only, zero
# data changes. Run from repo root: python3 docs/agents/audit/gate_bq.py
A = './packages/addressing/resources/geography/caribbean-netherlands-address-areas.csv'
C = './packages/addressing/resources/geography/caribbean-netherlands-postal-codes.csv'
L = './packages/addressing/resources/geography/caribbean-netherlands-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A, newline='')))
byid = {r['source_id']: r for r in rows}
check('areas-3', len(rows) == 3, str(len(rows)))
# ISO 3166-2:BQ: three special municipalities.
iso = {'bq:special_municipality:bonaire': ('Bonaire', 'BO'),
       'bq:special_municipality:saba': ('Saba', 'SA'),
       'bq:special_municipality:sint-eustatius': ('Sint Eustatius', 'SE')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['type'] == 'special_municipality' and r['level'] == '1'
          and r['parent_source_id'] == '', str(r))
# No postcode system: UPU besEn (05/2014) carries operator info only
# with no postcode section, and the en-wiki Netherlands postcodes
# article states the three islands "do not as yet have postal codes".
# Watch: Dutch government plans to introduce postcodes by end 2026.
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

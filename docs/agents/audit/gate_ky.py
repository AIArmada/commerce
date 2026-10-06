import csv, os, sys
# Cayman-Islands gate. B3 revisit: verify-only, zero data changes.
# Run from repo root: python3 docs/agents/audit/gate_ky.py
A = './packages/addressing/resources/geography/cayman-islands-address-areas.csv'
C = './packages/addressing/resources/geography/cayman-islands-postal-codes.csv'
L = './packages/addressing/resources/geography/cayman-islands-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A, newline='')))
byid = {r['source_id']: r for r in rows}
check('areas-10', len(rows) == 10, str(len(rows)))
# Three islands (no ISO 3166-2:KY codes; 01-03 synthetic) + seven
# districts per the district table (prose merges the Sister
# Islands into one "sixth" district).
check('islands-3', sum(1 for r in rows if r['type'] == 'island') == 3)
check('districts-7', sum(1 for r in rows if r['type'] == 'district') == 7)
gc = 'ky:island:grand-cayman'
check('grand-cayman-5', sorted(r['name'] for r in rows if r['parent_source_id'] == gc)
      == ['Bodden Town', 'East End', 'George Town', 'North Side', 'West Bay'])
check('brac-self', byid.get('ky:district:cayman-brac', {}).get('parent_source_id')
      == 'ky:island:cayman-brac')
check('little-self', byid.get('ky:district:little-cayman', {}).get('parent_source_id')
      == 'ky:island:little-cayman')
# UPU cymEn: box-only system (street address alone undeliverable,
# PO boxes only) -> deliberate no-import, codes pass through.
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

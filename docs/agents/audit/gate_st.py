import csv, os, sys
# Sao-Tome-and-Principe gate. B2 revisit: fixed Lemba -> Lembá
# (1-cell; ISO 3166-2 ST-04 + district article + the file's own
# accent convention: Água Grande, Caué, Mé-Zóchi, Príncipe).
# Run from repo root: python3 docs/agents/audit/gate_st.py
A = './packages/addressing/resources/geography/sao-tome-and-principe-address-areas.csv'
C = './packages/addressing/resources/geography/sao-tome-and-principe-postal-codes.csv'
L = './packages/addressing/resources/geography/sao-tome-and-principe-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A, newline='')))
byid = {r['source_id']: r for r in rows}
check('areas-7', len(rows) == 7, str(len(rows)))
# ISO 3166-2:ST: six districts + ST-P Príncipe autonomous region.
iso = {'st:district:agua-grande': ('Água Grande', '01'),
       'st:district:cantagalo': ('Cantagalo', '02'),
       'st:district:caue': ('Caué', '03'),
       'st:district:lemba': ('Lembá', '04'),
       'st:district:lobata': ('Lobata', '05'),
       'st:district:me-zochi': ('Mé-Zóchi', '06'),
       'st:autonomous_region:principe': ('Príncipe', 'P')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1' and r['parent_source_id'] == '', str(r))
check('no-ascii-lemba', all(r['name'] != 'Lemba' for r in rows))
# UPU stpEn (09/2004): address format with no postcode section.
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

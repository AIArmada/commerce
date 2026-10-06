import csv, sys
# Monaco gate. B5 revisit: verify-only, zero data changes.
# Tree 17/17 vs ISO 3166-2:MC (2013-ordinance wards stay out).
# Sole code 98000 vs the UPU mco profile (00 = delivery to
# addressee; 01-99 special delivery types incl. CEDEX, held out)
# + GeoNames MC dump (29 rows, all 98000); zero links. Run from
# repo root: python3 docs/agents/audit/gate_mc.py
A = './packages/addressing/resources/geography/monaco-address-areas.csv'
C = './packages/addressing/resources/geography/monaco-postal-codes.csv'
L = './packages/addressing/resources/geography/monaco-postal-code-areas.csv'
ISO = {'FO': 'Fontvieille', 'JE': 'Jardin Exotique', 'CL': 'La Colle',
       'CO': 'La Condamine', 'GA': 'La Gare', 'SO': 'La Source',
       'LA': 'Larvotto', 'MA': 'Malbousquet', 'MO': 'Monaco-Ville',
       'MG': 'Moneghetti', 'MC': 'Monte-Carlo', 'MU': 'Moulins',
       'PH': 'Port-Hercule', 'SR': 'Saint-Roman',
       'SD': 'Sainte-Dévote', 'SP': 'Spélugues',
       'VR': 'Vallon de la Rousse'}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


areas = list(csv.DictReader(open(A, newline='')))
check('areas-17', len(areas) == 17, str(len(areas)))
got = {r['code']: r['name'] for r in areas}
check('iso-17', got == ISO, str({k for k in ISO if got.get(k) != ISO[k]}))
codes = [r['code'] for r in csv.DictReader(open(C, newline=''))]
check('codes-98000', codes == ['98000'], str(codes))
links = list(csv.DictReader(open(L, newline='')))
check('links-0', len(links) == 0, str(len(links)))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES: {fails}')
sys.exit(1 if fails else 0)

import csv, os, sys
# Comoros gate. No postcode system (B.P. delivery). M1 revisit:
# verify-only, zero data changes. Run from repo root:
# python3 docs/agents/audit/gate_km.py
A = './packages/addressing/resources/geography/comoros-address-areas.csv'
C = './packages/addressing/resources/geography/comoros-postal-codes.csv'
L = './packages/addressing/resources/geography/comoros-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
byid = {r['source_id']: r for r in rows}
check('areas-19', len(rows) == 19, str(len(rows)))
check('islands-3', sum(1 for r in rows if r['type'] == 'island') == 3)
check('prefectures-16', sum(1 for r in rows if r['type'] == 'prefecture') == 16)
# ISO 3166-2:KM island codes.
for sid, name, code in [('km:island:anjouan', 'Anjouan', 'A'),
        ('km:island:grande-comore', 'Grande Comore', 'G'),
        ('km:island:moheli', 'Mohéli', 'M')]:
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1', str(r))
# Full prefecture table: Loi N°11-006/AU Art. 7 (Mwali 3, Ngazidja 8,
# Ndzuwani 5; French names match the CSV rows).
table = {'km:island:moheli': ['Fomboni', 'Nioumachioi', 'Djando'],
 'km:island:grande-comore': ['Moroni-Bambao', 'Hambou', 'Mbadjini-Ouest',
    'Mbadjini-Est', 'Oichili-Dimani', 'Hamahamet-Mboinkou',
    'Mitsamiouli-Mboudé', 'Itsandra-Hamanvou'],
 'km:island:anjouan': ['Mutsamudu', 'Ouani', 'Domoni', 'Mrémani', 'Sima']}
ok = True
for isl, names in table.items():
    have = sorted(r['name'] for r in rows if r['parent_source_id'] == isl)
    if have != sorted(names):
        print('FAIL island', isl, have); fails.append(f'island {isl}'); ok = False
if ok: print('PASS all 3 island mappings (16 prefectures)')
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# Verdict none: no postal overlay files. UPU KM profile 07/2002 shows
# B.P.-only addressing; GeoNames has no KM postal dump (404);
# directories list no codes (00000 placeholders only). Note: UPU's
# require/do-not-require lists contradict on Comoros (Aug-2026 vs
# Sep-2025) — resolved by profile + dumps + live B.P. usage.
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

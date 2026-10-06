import csv, re, sys
from collections import Counter
# Portugal gate. Pins the B18 worker pass (328 areas: 20 L1 +
# 308 municipalities; 197,772 CP7 codes / 197,772 1:1 links):
# tree oracle-exact (DGT-scheme codes + pt/en wiki + mirror
# tags); postal stratified audit 25/25 vs CTT-structured mirror
# codigo-postal.pt (urban Lisboa/Porto, rural Beja/north,
# Madeira, Azores incl. Corvo 9980); format NNNN-NNN pure,
# 750 prefixes 1000-9980. TWO fixes: district+municipality
# Lisbon->Lisboa (source_id unchanged); postal pair CRLF->LF
# (was 100% CRLF vs LF areas; KN precedent; content+order
# identical). EOL: all LF now.
# Asserts verified state; run from repo root:
# python3 docs/agents/audit/gate_pt.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
G = f'{ROOT}/packages/addressing/resources/geography'
A = f'{G}/portugal-address-areas.csv'
C = f'{G}/portugal-postal-codes.csv'
L = f'{G}/portugal-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
for tag, p in [('areas', A), ('codes', C), ('legs', L)]:
    raw = open(p, 'rb').read()
    check(f'{tag}-LF-only', b'\r' not in raw)
    check(f'{tag}-trailing-LF', raw.endswith(b'\n'))
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
check('areas-328', len(rows) == 328, str(len(rows)))
check('source-ids-unique', len(byid) == len(rows))
check('L1-20', sum(1 for r in rows if r['level'] == '1') == 20)
check('municipalities-308', sum(1 for r in rows if r['level'] == '2') == 308)
iso = {'aveiro': '01', 'beja': '02', 'braga': '03', 'braganca': '04',
       'castelo-branco': '05', 'coimbra': '06', 'evora': '07', 'faro': '08',
       'guarda': '09', 'leiria': '10', 'lisbon': '11', 'portalegre': '12',
       'porto': '13', 'santarem': '14', 'setubal': '15',
       'viana-do-castelo': '16', 'vila-real': '17', 'viseu': '18'}
ok = all(byid.get(f'pt:district:{s}', {}).get('code') == c for s, c in iso.items())
check('district-codes-18', ok and len(iso) == 18)
check('acores-20', byid.get('pt:autonomous_region:acores', {}).get('code') == '20')
check('madeira-30', byid.get('pt:autonomous_region:madeira', {}).get('code') == '30')
check('parents-resolve', all(r['parent_source_id'] in byid for r in rows if r['level'] == '2'))
check('parents-are-L1', all(byid[r['parent_source_id']]['level'] == '1' for r in rows if r['level'] == '2'))
check('fix-lisboa-district', byid.get('pt:district:lisbon', {}).get('name') == 'Lisboa')
check('fix-lisboa-municipality', byid.get('pt:municipality:lisbon', {}).get('name') == 'Lisboa')
check('fix-lisbon-gone', all(r['name'] != 'Lisbon' for r in rows))
codes = [r['code'] for r in csv.DictReader(open(C, newline='', encoding='utf-8'))]
legs = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
check('codes-197772', len(codes) == 197772, str(len(codes)))
check('codes-unique', len(set(codes)) == len(codes))
check('legs-197772', len(legs) == 197772, str(len(legs)))
check('format-NNNN-NNN', all(re.fullmatch(r'\d{4}-\d{3}', c) for c in codes))
prefix = {c[:4] for c in codes}
check('prefixes-750', len(prefix) == 750, str(len(prefix)))
check('prefix-range', min(prefix) == '1000' and max(prefix) == '9980', f'{min(prefix)}-{max(prefix)}')
check('legs-resolve', all(r['area_source_id'] in byid for r in legs))
check('all-primary', all(r['is_primary'] == 'true' for r in legs))
check('one-link-each', Counter(r['postcode'] for r in legs).most_common(1)[0][1] == 1)
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

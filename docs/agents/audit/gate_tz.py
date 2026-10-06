import csv, re, sys
from collections import Counter
# Tanzania gate. Pins the B16 worker pass as integrated (tree:
# +Mlimba/+Mtama/+Kibiti, Mpanda->Tanganyika rename,
# -Kilombero/-Lindi; postal: 124 fills + 1 swap-add - 63
# removes + 59 leg moves; 225 areas / 4096 codes / 4096 legs
# / 0 multis): 31 regions ISO 3166-2:TZ exact + 194 districts
# (NBS 2022 mainland 184 exact + Zanzibar 10). NBS-backed
# tree: Kilombero DC dissolved (16 wards->Mlimba DC + 2
# Mang'ula->Ifakara TC), Lindi DC dissolved (20->Mtama DC +
# 11->Lindi MC with 65121-31 codes), Mpanda DC renamed
# Tanganyika DC (Tanganyika-16 ward-identical incl. 502
# block), Kibiti DC added (618x15 + Bungu 61617). Postal:
# Katavi 502 block filled (omar+H+live+NBS), Zanzibar
# rebuilt (91 TCRA-2012 fills incl. 10-district live sample;
# 60 sequential-extension phantoms removed, all absent
# omar+H+live), 53733->73733 Njisi swap (TCRA Mbeya PDF +
# H + live), 57731 Lituta fill (H + live + NBS Madaba-8),
# 57231/54118 phantoms removed, 9 Mlele->Mpimbwe fixes
# (NBS Mpimbwe-9), town-council attributions (Geita/Kibaha/
# Newala/Bagamoyo/Ifakara/Lindi City 31). Holds: Magharibi
# A/B split (NBS-confirmed, no shehia->code map; legs stay
# lumped), 65121-31 numbers bundle-single-signal, H1-H8
# worker holds (~20 retired codes kept, Kigoma extensions
# kept). EOL: areas LF-only, postal files CRLF (kept).
# Asserts POST-fix state; run from repo root:
# python3 docs/agents/audit/gate_tz.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/tanzania-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/tanzania-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/tanzania-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
raw_a = open(A, 'rb').read()
raw_c = open(C, 'rb').read()
raw_l = open(L, 'rb').read()
def is_crlf(raw):
    return b'\r\n' in raw and b'\r' not in raw.replace(b'\r\n', b'') and b'\n' not in raw.replace(b'\r\n', b'')
check('areas-LF-only', b'\r' not in raw_a)
check('areas-trailing-LF', raw_a.endswith(b'\n'))
check('codes-CRLF', is_crlf(raw_c))
check('codes-trailing-CRLF', raw_c.endswith(b'\r\n'))
check('links-CRLF', is_crlf(raw_l))
check('links-trailing-CRLF', raw_l.endswith(b'\r\n'))
try:
    rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
    codes = list(csv.DictReader(open(C, newline='', encoding='utf-8')))
    links = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
except FileNotFoundError as e:
    check('files-present', False, str(e))
    print(f'{len(fails)} FAILURES')
    sys.exit(1)
byid = {r['source_id']: r for r in rows}
check('areas-225', len(rows) == 225, str(len(rows)))
check('region-31', sum(1 for r in rows if r['type'] == 'region') == 31)
check('district-194', sum(1 for r in rows if r['type'] == 'district') == 194)
spots = {'tz:region:lindi': ('Lindi', '12'), 'tz:region:morogoro': ('Morogoro', '16'),
         'tz:region:pwani': ('Pwani', '19'), 'tz:region:katavi': ('Katavi', '28'),
         'tz:district:mlimba': ('Mlimba', 'tz:region:morogoro'),
         'tz:district:mtama': ('Mtama', 'tz:region:lindi'),
         'tz:district:kibiti': ('Kibiti', 'tz:region:pwani'),
         'tz:district:tanganyika': ('Tanganyika', 'tz:region:katavi'),
         'tz:district:lindi-city': ('Lindi City', 'tz:region:lindi')}
for sid, (nm, par) in spots.items():
    r = byid.get(sid, {})
    ok = r.get('name') == nm and (par.isdigit() and r.get('code') == par or r.get('parent_source_id') == par)
    check(f'spot-{sid}', ok, str((r.get('name'), r.get('code'), r.get('parent_source_id'))))
for gone in ['tz:district:kilombero', 'tz:district:lindi', 'tz:district:mpanda']:
    check(f'gone-{gone}', gone not in byid)
check('codes-4096', len(codes) == 4096, str(len(codes)))
check('links-4096', len(links) == 4096, str(len(links)))
check('codes-TZ', all(c['country_code'] == 'TZ' for c in codes))
clist = [c['code'] for c in codes]
check('codes-5digit', all(re.match(r'^\d{5}$', c) for c in clist))
check('codes-sorted', clist == sorted(clist))
check('all-served-by', all(l['relationship_type'] == 'served_by' for l in links))
have = Counter(l['postcode'] for l in links)
check('multis-0', all(v == 1 for v in have.values()))
check('legs-resolve', all(l['area_source_id'] in byid for l in links))
prims = {}
for l in links:
    if l['is_primary'] == 'true':
        prims.setdefault(l['postcode'], []).append(l['area_source_id'])
check('one-primary-per-code', all(len(v) == 1 for v in prims.values()) and len(prims) == 4096)
per = Counter(l['area_source_id'] for l in links)
for sid, n in [('tz:district:mpimbwe', 9), ('tz:district:tanganyika', 16),
               ('tz:district:kibiti', 16), ('tz:district:mlimba', 16),
               ('tz:district:mtama', 20), ('tz:district:madaba', 8),
               ('tz:district:lindi-city', 31)]:
    check(f'count-{sid}', per.get(sid, 0) == n, str(per.get(sid, 0)))
for c in ['57231', '54118', '53733', '71125', '71224', '71314', '72113', '72215', '73117',
          '73216', '74123', '74221', '75121', '75213']:
    check(f'excluded-{c}', c not in clist)
keeps = {'50201': 'tz:district:tanganyika', '50216': 'tz:district:tanganyika',
         '61801': 'tz:district:kibiti', '61617': 'tz:district:kibiti',
         '57731': 'tz:district:madaba', '73733': 'tz:district:kyela',
         '50315': 'tz:district:mpimbwe', '50326': 'tz:district:mpimbwe',
         '65110': 'tz:district:lindi-city', '65124': 'tz:district:lindi-city',
         '65201': 'tz:district:mtama', '67510': 'tz:district:mlimba',
         '67505': 'tz:district:ifakara-urban', '30129': 'tz:district:geita-urban',
         '61102': 'tz:district:kibaha-urban', '61305': 'tz:district:bagamoyo',
         '63435': 'tz:district:newala-urban', '71101': 'tz:district:zanzibar-city',
         '71215': 'tz:district:zanzibar-west', '72111': 'tz:district:kusini',
         '72211': 'tz:district:kati', '73111': 'tz:district:kaskazini-a',
         '73210': 'tz:district:kaskazini-b', '74118': 'tz:district:mkoani',
         '74217': 'tz:district:chake-chake', '75117': 'tz:district:wete',
         '75208': 'tz:district:micheweni', '71120': 'tz:district:zanzibar-city'}
for pc, sid in keeps.items():
    check(f'keep-{pc}', prims.get(pc) == [sid], str(prims.get(pc)))
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

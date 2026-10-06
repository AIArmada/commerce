import csv, sys
from collections import Counter
# Norway gate. Pins the B19 worker pass (374 areas: 17 L1 + 357
# L2; 5110 codes / 5110 1:1 links, all LF): tree verify-only —
# 15 post-2024 counties + 2 arctic regions exact vs SSB
# Klass-104, 357 municipalities exact vs SSB Klass-131 (9999
# Uoppgitt correctly excluded), 2024 splits (30->31/32/33,
# 38->39/40, 54->55/56) per Bring kommuneendringer; ISO
# 3166-2:NO is STALE (11 counties, 2020-24 scheme) and loses to
# operator+catalogue. Postal: 14 fills (8 logg-nye 2024/25/26 +
# 6 pre-1999 gaps in both Bring vintages) + 40 drops (39 dated
# opphør->9999 + 8128->8120 redirect); 5136+14-40=5110, +2 held
# S-codes = 5112 = operator current. GN is stale for NO (has
# all 40 retired, lacks all 16 additions) — link-second-signal
# only. Holds: 0046/0047 S out, 0018/0045 S kept (no churn).
# Codes file keeps blank line after every data row except the
# hand-added arctic block (8099/9170/9171/9173-9176/9178).
# Asserts verified state; run from repo root:
# python3 docs/agents/audit/gate_no.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
G = f'{ROOT}/packages/addressing/resources/geography'
A = f'{G}/norway-address-areas.csv'
C = f'{G}/norway-postal-codes.csv'
L = f'{G}/norway-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
raw_a = open(A, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-trailing-LF', raw_a.endswith(b'\n'))
raw_c = open(C, 'rb').read()
check('codes-LF-only', b'\r' not in raw_c)
check('codes-trailing-blank', raw_c.endswith(b'\n\n'))
raw_l = open(L, 'rb').read()
check('legs-LF-only', b'\r' not in raw_l)
check('legs-no-blanks', b'\n\n' not in raw_l)
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
check('areas-374', len(rows) == 374, str(len(rows)))
check('source-ids-unique', len(byid) == len(rows))
check('L1-17', sum(1 for r in rows if r['level'] == '1') == 17)
check('L2-357', sum(1 for r in rows if r['level'] == '2') == 357)
iso = {'oslo': '03', 'rogaland': '11', 'mre-og-romsdal': '15',
       'nordland': '18', 'stfold': '31', 'akershus': '32',
       'buskerud': '33', 'innlandet': '34', 'vestfold': '39',
       'telemark': '40', 'agder': '42', 'vestland': '46',
       'trndelag': '50', 'troms': '55', 'finnmark': '56'}
ok = all(byid.get(f'no:county:{s}', {}).get('code') == c for s, c in iso.items())
check('county-codes-15', ok and len(iso) == 15)
check('svalbard-21', byid.get('no:arctic_region:svalbard', {}).get('code') == '21')
check('jan-mayen-22', byid.get('no:arctic_region:jan-mayen', {}).get('code') == '22')
check('no-viken-30', all(r['code'] != '30' for r in rows if r['level'] == '1'))
check('parents-resolve', all(r['parent_source_id'] in byid for r in rows if r['level'] == '2'))
check('parents-are-L1', all(byid[r['parent_source_id']]['level'] == '1' for r in rows if r['level'] == '2'))
codes = [r['code'] for r in csv.DictReader(open(C, newline='', encoding='utf-8'))]
legs = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
check('codes-5110', len(codes) == 5110, str(len(codes)))
check('codes-unique', len(set(codes)) == len(codes))
check('legs-5110', len(legs) == 5110, str(len(legs)))
check('legs-resolve', all(r['area_source_id'] in byid for r in legs))
check('all-primary', all(r['is_primary'] == 'true' for r in legs))
check('every-code-linked', set(codes) == {r['postcode'] for r in legs})
bycode = {r['postcode']: r['area_source_id'] for r in legs}
fills = {'0040': 'no:municipality:oslo', '0540': 'no:municipality:oslo',
         '1426': 'no:municipality:as', '4075': 'no:municipality:stavanger',
         '4238': 'no:municipality:suldal', '5245': 'no:municipality:bergen',
         '7061': 'no:municipality:trondheim',
         '8845': 'no:municipality:nordland:heroy',
         '8866': 'no:municipality:alstahaug',
         '9653': 'no:municipality:hammerfest',
         '9173': 'no:arctic_region:svalbard', '9174': 'no:arctic_region:svalbard',
         '9175': 'no:arctic_region:svalbard', '9176': 'no:arctic_region:svalbard'}
check('fills-14', all(bycode.get(c) == a for c, a in fills.items()) and len(fills) == 14)
drops = ['0031', '0128', '2404', '2513', '2620', '2645', '2648', '2679',
         '2681', '2809', '3168', '3197', '3245', '3527', '3575', '3787',
         '4672', '5213', '5214', '5982', '6079', '6134', '6331', '6435',
         '6506', '6549', '6623', '6703', '6845', '6997', '7151', '7333',
         '7426', '7459', '8128', '8374', '8419', '9290', '9391', '9456']
check('drops-40', all(c not in bycode and c not in codes for c in drops) and len(drops) == 40)
check('hold-0046-out', '0046' not in codes)
check('hold-0047-out', '0047' not in codes)
check('hold-0018-kept', bycode.get('0018') == 'no:municipality:oslo')
check('hold-0045-kept', bycode.get('0045') == 'no:municipality:oslo')
check('haram-1580', byid.get('no:municipality:haram', {}).get('code') == '1580')
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

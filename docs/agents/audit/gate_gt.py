import csv, sys
from collections import Counter
# Guatemala gate. Pins the B19 worker pass (362 areas: 22 L1 + 340
# L2; 549 codes / 549 L1 links, all LF): L1 22/22 ISO, L2
# membership 340/340 (annex + GN-ADM2 + per-dept counts +
# IDH-INE + Correos), parents clean, every code prefix = dept
# code, 548/548 L1 links correct (prefix rule + GN-admin1 +
# Correos codeset 21/22 + UPU + Petén directory 29/29). THREE
# renames: Antigua->Antigua Guatemala (Correos operator +
# IDH-INE + muni self-name + annex + GN), San Bartolo->San
# Bartolo Aguas Calientes (muni PDF + MINFIN + IDH; Correos
# short discounted as proven shorthand), Quetzaltepeque->
# Quezaltepeque + slug (IDH-INE + Correos + GN; bundle+annex
# wiki-family outvoted). FILL 01025 Zona 25 (directory + legal
# notice + manifests + listings; Correos/GN predate it). Holds:
# H1-H4 article case, H5/H6 accents, H7 01000 single-signal,
# H8 Petén PDF 404 (verified via GN+directory), H9 walled
# officials. 05008/01020 gaps real.
# Asserts verified state; run from repo root:
# python3 docs/agents/audit/gate_gt.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
G = f'{ROOT}/packages/addressing/resources/geography'
A = f'{G}/guatemala-address-areas.csv'
C = f'{G}/guatemala-postal-codes.csv'
L = f'{G}/guatemala-postal-code-areas.csv'
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
check('areas-362', len(rows) == 362, str(len(rows)))
check('source-ids-unique', len(byid) == len(rows))
check('L1-22', sum(1 for r in rows if r['level'] == '1') == 22)
check('L2-340', sum(1 for r in rows if r['level'] == '2') == 340)
iso = {'guatemala': '01', 'el-progreso': '02', 'sacatepequez': '03',
       'chimaltenango': '04', 'escuintla': '05', 'santa-rosa': '06',
       'solola': '07', 'totonicapan': '08', 'quetzaltenango': '09',
       'suchitepequez': '10', 'retalhuleu': '11', 'san-marcos': '12',
       'huehuetenango': '13', 'quiche': '14', 'baja-verapaz': '15',
       'alta-verapaz': '16', 'peten': '17', 'izabal': '18',
       'zacapa': '19', 'chiquimula': '20', 'jalapa': '21', 'jutiapa': '22'}
ok = all(byid.get(f'gt:department:{s}', {}).get('code') == c for s, c in iso.items())
check('iso-22', ok and len(iso) == 22)
check('parents-resolve', all(r['parent_source_id'] in byid for r in rows if r['level'] == '2'))
check('parents-are-L1', all(byid[r['parent_source_id']]['level'] == '1' for r in rows if r['level'] == '2'))
check('fix-antigua', byid.get('gt:municipality:antigua', {}).get('name') == 'Antigua Guatemala')
check('fix-san-bartolo', byid.get('gt:municipality:san-bartolo', {}).get('name') == 'San Bartolo Aguas Calientes')
check('fix-quezaltepeque', byid.get('gt:municipality:quezaltepeque', {}).get('name') == 'Quezaltepeque')
check('fix-quetzaltepeque-gone', 'gt:municipality:quetzaltepeque' not in byid)
check('keep-ciudad-guatemala', byid.get('gt:municipality:guatemala', {}).get('name') == 'Ciudad de Guatemala' or byid.get('gt:municipality:ciudad-de-guatemala', {}).get('name') == 'Ciudad de Guatemala')
codes = [r['code'] for r in csv.DictReader(open(C, newline='', encoding='utf-8'))]
legs = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
check('codes-549', len(codes) == 549, str(len(codes)))
check('codes-unique', len(set(codes)) == len(codes))
check('legs-549', len(legs) == 549, str(len(legs)))
check('legs-resolve', all(r['area_source_id'] in byid for r in legs))
check('all-L1', all(r['area_source_id'].startswith('gt:department:') for r in legs))
check('all-primary', all(r['is_primary'] == 'true' for r in legs))
check('every-code-linked', set(codes) == {r['postcode'] for r in legs})
bycode = {r['postcode']: r['area_source_id'] for r in legs}
check('fill-01025', bycode.get('01025') == 'gt:department:guatemala')
check('hold-01000-out', '01000' not in codes)
check('gap-05008-real', '05008' not in codes)
check('gap-01020-real', '01020' not in codes)
check('peten-17001', bycode.get('17001') == 'gt:department:peten')
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

import csv, sys
# Papua New Guinea gate. Pins the B13 fix-and-fill pass: Bulolo rename
# (Bulolo_District -> Bulolo, slug pg:district:bulolo) plus 5 postal fills
# (135/332/512/613/635) -> 67 codes / 76 links. Tree: 22 L1 + 96 L2,
# Morobe 10 incl. Bulolo. Oracles: archived Post PNG office list (39 entries),
# Mapanet 219-row scrape (62/62 code-set exact), PNGEC 2022 polling schedule,
# WP district/LLG pages, addressed-usage proofs. Holds: 541 Lorengau
# (contradicted: Mapanet 641 + 3 addressed 641 usages), 417 Gusap
# (office confirmed, district unattributable). Asserts POST-fix state;
# run from repo root: python3 docs/agents/audit/gate_pg.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/papua-new-guinea-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/papua-new-guinea-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/papua-new-guinea-postal-code-areas.csv'
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
check('areas-trailing-newline', raw_a.endswith(b'\n'))
check('codes-CRLF', is_crlf(raw_c))
check('codes-trailing-CRLF', raw_c.endswith(b'\r\n'))
check('links-CRLF', is_crlf(raw_l))
check('links-trailing-CRLF', raw_l.endswith(b'\r\n'))
try:
    rows = list(csv.DictReader(open(A, newline='', encoding='utf-8-sig')))
    codes = list(csv.DictReader(open(C, newline='', encoding='utf-8-sig')))
    links = list(csv.DictReader(open(L, newline='', encoding='utf-8-sig')))
except FileNotFoundError as e:
    check('files-present', False, str(e))
    print(f'{len(fails)} FAILURES')
    sys.exit(1)
byid = {r['source_id']: r for r in rows}
# --- tree: 22 L1 + 96 L2 ---
check('areas-118', len(rows) == 118, str(len(rows)))
check('l1-22', sum(1 for r in rows if r['level'] == '1') == 22)
check('l2-96', sum(1 for r in rows if r['level'] == '2') == 96)
check('no-l3', all(r['level'] in ('1', '2') for r in rows))
l2prov = {'Bougainville': 3, 'Central': 5, 'Chimbu': 6, 'East New Britain': 4,
          'East Sepik': 6, 'Eastern Highlands': 8, 'Enga': 6, 'Gulf': 2,
          'Hela': 4, 'Jiwaka': 3, 'Madang': 6, 'Manus': 1, 'Milne Bay': 4,
          'Morobe': 10, 'New Ireland': 2, 'Oro': 3, 'Port Moresby': 3,
          'Sandaun': 4, 'Southern Highlands': 5, 'West New Britain': 3,
          'Western': 4, 'Western Highlands': 4}
l1name = {r['source_id']: r['name'] for r in rows if r['level'] == '1'}
for prov, n in l2prov.items():
    got = sum(1 for r in rows if r['level'] == '2'
              and l1name.get(r['parent_source_id']) == prov)
    check(f'l2-{prov}', got == n, f'{got} != {n}')
# --- Bulolo rename ---
b = byid.get('pg:district:bulolo')
check('bulolo-slug', bool(b), str(b))
check('bulolo-name', bool(b) and b['name'] == 'Bulolo', str(b))
check('bulolo-parent', bool(b) and b['parent_source_id'] == 'pg:province:morobe', str(b))
check('bulolo-old-gone', 'pg:district:bulolo-district' not in byid)
check('bulolo-underscore-gone', all('Bulolo_District' != r['name'] for r in rows))
# --- postal totals: 67 codes / 76 links ---
check('codes-67', len(codes) == 67, str(len(codes)))
check('links-76', len(links) == 76, str(len(links)))
check('codes-PG', all(c['country_code'] == 'PG' for c in codes))
codelegs = {}
for l in links:
    codelegs.setdefault(l['postcode'], []).append(l)
check('multis-4', sum(1 for v in codelegs.values() if len(v) > 1) == 4)
check('136-x2', sorted(x['area_source_id'] for x in codelegs.get('136', []))
      == ['pg:district:goilala', 'pg:district:port-moresby-north-west'])
check('293-x2', sorted(x['area_source_id'] for x in codelegs.get('293', []))
      == ['pg:district:kandep', 'pg:district:wapenamanda'])
check('355-x3', sorted(x['area_source_id'] for x in codelegs.get('355', []))
      == ['pg:district:central-bougainville', 'pg:district:north-bougainville',
          'pg:district:south-bougainville'])
check('461-x6', len(codelegs.get('461', [])) == 6, str(len(codelegs.get('461', []))))
prim = {c: [x['area_source_id'] for x in v if x['is_primary'] == 'true']
        for c, v in codelegs.items()}
check('136-primary-nw', prim.get('136') == ['pg:district:port-moresby-north-west'], str(prim.get('136')))
check('293-primary-wapenamanda', prim.get('293') == ['pg:district:wapenamanda'], str(prim.get('293')))
check('355-primary-north', prim.get('355') == ['pg:district:north-bougainville'], str(prim.get('355')))
check('461-primary-kundiawa', prim.get('461') == ['pg:district:kundiawa-gembogl'], str(prim.get('461')))
check('one-primary-each', all(len(v) == 1 for v in prim.values()))
fills = {'135': 'pg:district:port-moresby-north-east',
         '332': 'pg:district:north-fly',
         '512': 'pg:district:madang',
         '613': 'pg:district:kokopo',
         '635': 'pg:district:namatanai'}
for code, sid in fills.items():
    v = codelegs.get(code, [])
    check(f'fill-{code}', len(v) == 1 and v[0]['area_source_id'] == sid
          and v[0]['is_primary'] == 'true' and v[0]['relationship_type'] == 'served_by', str(v))
anchors = {'111': 'pg:district:port-moresby-north-east',
           '131': 'pg:district:port-moresby-north-west',
           '281': 'pg:district:mount-hagen',
           '291': 'pg:district:wabag',
           '411': 'pg:district:lae',
           '423': 'pg:district:bulolo',
           '511': 'pg:district:madang',
           '531': 'pg:district:wewak',
           '611': 'pg:district:rabaul',
           '631': 'pg:district:kavieng',
           '641': 'pg:district:manus'}
for code, sid in anchors.items():
    v = codelegs.get(code, [])
    check(f'anchor-{code}', len(v) == 1 and v[0]['area_source_id'] == sid
          and v[0]['is_primary'] == 'true', str(v))
legs_by_prov = {}
for l in links:
    a = byid.get(l['area_source_id'])
    pn = l1name.get(a['parent_source_id']) if a else None
    legs_by_prov[pn] = legs_by_prov.get(pn, 0) + 1
exp_legs = {'Bougainville': 4, 'Central': 2, 'Chimbu': 6, 'East New Britain': 2,
            'East Sepik': 4, 'Eastern Highlands': 2, 'Enga': 4, 'Gulf': 4,
            'Hela': 1, 'Jiwaka': 1, 'Madang': 6, 'Manus': 1, 'Milne Bay': 2,
            'Morobe': 7, 'New Ireland': 3, 'Oro': 2, 'Port Moresby': 10,
            'Sandaun': 4, 'Southern Highlands': 2, 'West New Britain': 4,
            'Western': 4, 'Western Highlands': 1}
for prov, n in exp_legs.items():
    check(f'legs-{prov}', legs_by_prov.get(prov) == n,
          f"{legs_by_prov.get(prov)} != {n}")
check('541-held-out', '541' not in codelegs)
check('417-held-out', '417' not in codelegs)
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)

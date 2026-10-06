import csv, os, re, sys
# Burkina Faso geography gate. Pins the CORRECTED state from the M3 revisit:
# 64 areas (17 regions + 47 provinces), Boulkiemde->Boulkiemde accent fix,
# 467-code La Poste BF finder fill (47/47 provinces). Run from repo root:
# python3 docs/agents/audit/gate_bf.py
A = './packages/addressing/resources/geography/burkina-faso-address-areas.csv'
C = './packages/addressing/resources/geography/burkina-faso-postal-codes.csv'
L = './packages/addressing/resources/geography/burkina-faso-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
byid = {r['source_id']: r for r in rows}
check('areas-64', len(rows) == 64, str(len(rows)))
check('regions-17', sum(1 for r in rows if r['type'] == 'region') == 17)
check('provinces-47', sum(1 for r in rows if r['type'] == 'province') == 47)
# --- post-reform region table: Presidency 02/07/2025 toponym list (17 names).
want_regions = ['Bankui', 'Djôrô', 'Goulmou', 'Guiriko', 'Kadiogo',
    'Kuilsé', 'Liptako', 'Nakambé', 'Nando', 'Nazinon', 'Oubri',
    'Sirba', 'Soum', 'Sourou', 'Tannounyan', 'Tapoa', 'Yaadga']
have_regions = sorted(r['name'] for r in rows if r['type'] == 'region')
check('region-names-17', have_regions == sorted(want_regions),
      str(sorted(set(have_regions) ^ set(want_regions))))
# --- region codes: succession scheme (PCGN May-2026 factfile carries BF-01..BF-13
# onto the renamed regions; new regions uncoded there, provisional 14-17 here).
# Deliberately NOT the Presidency-table alphabetical ordinals (proven row numbers
# by the restarting province-table N) nor fr.wiki's unsourced renumbering.
succession = {'Bankui': '01', 'Tannounyan': '02', 'Kadiogo': '03',
    'Nakambé': '04', 'Kuilsé': '05', 'Nando': '06', 'Nazinon': '07',
    'Goulmou': '08', 'Guiriko': '09', 'Yaadga': '10', 'Oubri': '11',
    'Liptako': '12', 'Djôrô': '13', 'Sirba': '14', 'Soum': '15',
    'Sourou': '16', 'Tapoa': '17'}
ok = True
for r in rows:
    if r['type'] == 'region' and succession.get(r['name']) != r['code']:
        print('FAIL region code', r['name'], r['code']); fails.append('region code ' + r['name']); ok = False
if ok: print('PASS succession-codes-17')
# --- province codes: exact ISO 3166-2:BF 45-set + KAR/DYA provisional.
iso = ('BAL BAM BAN BAZ BGR BLG BLK COM GAN GNA GOU HOU IOB KAD KEN KMD '
       'KMP KOS KOP KOT KOW LER LOR MOU NAO NAM NAY NOU OUB OUD PAS PON SNG '
       'SMT SEN SIS SOM SOR TAP TUI YAG YAT ZIR ZON ZOU').split()
have_codes = sorted(r['code'] for r in rows if r['type'] == 'province')
check('province-codes-45+2', have_codes == sorted(iso + ['KAR', 'DYA']),
      str(sorted(set(have_codes) ^ set(iso + ['KAR', 'DYA']))))
# Renamed provinces keep predecessor codes (decree rename, not split).
for code, name in [('KOS', 'Koosin'), ('OUB', 'Bassitenga'),
        ('SMT', 'Sandbondtenga'), ('SOM', 'Djelgodji'), ('TAP', 'Gobnangou')]:
    got = [r['name'] for r in rows if r['code'] == code]
    check(f'rename-{code}-{name}', got == [name], str(got))
check('new-KAR', [r['name'] for r in rows if r['code'] == 'KAR'] == ['Karo-Peli'])
check('new-DYA', [r['name'] for r in rows if r['code'] == 'DYA'] == ['Dyamongou'])
# --- accent fix (M3): ISO + enwiki article + COD-AB + fr.wiki all Boulkiemde.
check('accent-boulkiemde',
      byid['bf:province:boulkiemde']['name'] == 'Boulkiemdé',
      byid['bf:province:boulkiemde']['name'])
# --- composition: COD-AB Apr-2026 admin1/admin2 table, all 47.
table = {
 'bf:region:bankui': ['Balé', 'Banwa', 'Mouhoun'],
 'bf:region:djoro': ['Bougouriba', 'Ioba', 'Noumbiel', 'Poni'],
 'bf:region:goulmou': ['Gourma', 'Kompienga'],
 'bf:region:guiriko': ['Houet', 'Kénédougou', 'Tuy'],
 'bf:region:kadiogo': ['Kadiogo'],
 'bf:region:kuilse': ['Bam', 'Namentenga', 'Sandbondtenga'],
 'bf:region:liptako': ['Oudalan', 'Séno', 'Yagha'],
 'bf:region:nakambe': ['Boulgou', 'Koulpélogo', 'Kouritenga'],
 'bf:region:nando': ['Boulkiemdé', 'Sanguié', 'Sissili', 'Ziro'],
 'bf:region:nazinon': ['Bazèga', 'Nahouri', 'Zoundwéogo'],
 'bf:region:oubri': ['Bassitenga', 'Ganzourgou', 'Kourwéogo'],
 'bf:region:sirba': ['Gnagna', 'Komondjari'],
 'bf:region:soum': ['Djelgodji', 'Karo-Peli'],
 'bf:region:sourou': ['Koosin', 'Nayala', 'Sourou'],
 'bf:region:tannounyan': ['Comoé', 'Léraba'],
 'bf:region:tapoa': ['Dyamongou', 'Gobnangou'],
 'bf:region:yaadga': ['Loroum', 'Passoré', 'Yatenga', 'Zondoma'],
}
ok = True
for reg, names in table.items():
    have = sorted(r['name'] for r in rows if r['parent_source_id'] == reg)
    if have != sorted(names):
        print('FAIL composition', reg, have); fails.append(f'composition {reg}'); ok = False
if ok: print('PASS all 17 compositions (47 provinces)')
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# --- recorded non-changes (deliberate deviations, see verdict.json).
# Kuilse (not Koulse): Presidency table + SIG decree text + enwiki primary;
#   PCGN/COD-AB/SIG-headline prefer Koulse. Unblock: JO decree text.
check('keep-kuilse', byid['bf:region:kuilse']['name'] == 'Kuilsé')
# Koosin (not Kossin): decree table; prose/operational sources use Kossin.
check('keep-koosin', byid['bf:province:koosin']['name'] == 'Koosin')
# Kouritenga single-t (ISO + finder KOURITENGA); COD-AB Kourittenga is a typo.
check('keep-kouritenga', byid['bf:province:kouritenga']['name'] == 'Kouritenga')
# Komondjari (ISO + enwiki); Komandjari is the recognized variant.
check('keep-komondjari', byid['bf:province:komondjari']['name'] == 'Komondjari')
# --- fill: 467 La Poste finder codes, 467 single-primary links, 47/47.
check('codes-file-exists', os.path.exists(C), C)
check('links-file-exists', os.path.exists(L), L)
if not (os.path.exists(C) and os.path.exists(L)):
    print(f'{len(fails)} FAILURES')
    sys.exit(1)
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
check('codes-467', len(codes) == 467, str(len(codes)))
check('links-467', len(links) == 467, str(len(links)))
bad = [c['code'] for c in codes if not re.match(r'^\d{5}$', c['code'])]
check('code-format-5d', not bad, str(bad[:3]))
check('codes-sorted', [c['code'] for c in codes] == sorted(c['code'] for c in codes))
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
from collections import Counter
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', len(prim) == 467 and not multi,
      f'primaries={len(prim)} multi={multi[:3]}')
check('all-primary', all(l['is_primary'] == 'true' for l in links))
check('codeset-match', sorted(c['code'] for c in codes) == sorted(prim))
# --- per-province primary counts (finder enumeration).
expect = {'bale': 12, 'bam': 11, 'banwa': 8, 'bassitenga': 8, 'bazega': 9,
    'bougouriba': 6, 'boulgou': 21, 'boulkiemde': 18, 'comoe': 13,
    'djelgodji': 8, 'dyamongou': 3, 'ganzourgou': 9, 'gnagna': 9,
    'gobnangou': 8, 'gourma': 7, 'houet': 25, 'ioba': 11, 'kadiogo': 37,
    'karo-peli': 3, 'kenedougou': 15, 'komondjari': 4, 'kompienga': 4,
    'koosin': 12, 'koulpelogo': 9, 'kouritenga': 11, 'kourweogo': 7,
    'leraba': 9, 'loroum': 5, 'mouhoun': 10, 'nahouri': 6, 'namentenga': 9,
    'nayala': 8, 'noumbiel': 6, 'oudalan': 6, 'passore': 10, 'poni': 11,
    'sandbondtenga': 14, 'sanguie': 11, 'seno': 7, 'sissili': 7,
    'sourou': 10, 'tuy': 8, 'yagha': 7, 'yatenga': 15, 'ziro': 6,
    'zondoma': 6, 'zoundweogo': 8}
have = Counter()
for l in links:
    if l['is_primary'] == 'true':
        have[l['area_source_id'].split(':')[-1]] += 1
for slug, n in expect.items():
    check(f'count-{slug}-{n}', have[slug] == n, str(have[slug]))
# --- prefix blocks: one 2-digit block per province; split pairs share 62/79.
blocks = {'10': ['kadiogo'], '20': ['bassitenga'], '21': ['ganzourgou'],
    '22': ['kourweogo'], '30': ['zoundweogo'], '31': ['bazega'],
    '32': ['nahouri'], '40': ['boulkiemde'], '41': ['sanguie'],
    '42': ['sissili'], '43': ['ziro'], '50': ['sandbondtenga'], '51': ['bam'],
    '52': ['namentenga'], '55': ['yatenga'], '56': ['loroum'],
    '57': ['passore'], '58': ['zondoma'], '60': ['seno'], '61': ['oudalan'],
    '62': ['djelgodji', 'karo-peli'], '63': ['yagha'], '70': ['boulgou'],
    '71': ['koulpelogo'], '72': ['kouritenga'], '75': ['gourma'],
    '76': ['gnagna'], '77': ['komondjari'], '78': ['kompienga'],
    '79': ['dyamongou', 'gobnangou'], '80': ['mouhoun'], '81': ['bale'],
    '82': ['banwa'], '83': ['koosin'], '84': ['nayala'], '85': ['sourou'],
    '90': ['houet'], '91': ['kenedougou'], '92': ['tuy'], '94': ['comoe'],
    '95': ['leraba'], '96': ['poni'], '97': ['bougouriba'], '98': ['ioba'],
    '99': ['noumbiel']}
plink = {l['postcode']: l['area_source_id'] for l in links
         if l['is_primary'] == 'true'}
ok = True
seen = set()
for pc, sid in plink.items():
    pfx, slug = pc[:2], sid.split(':')[-1]
    seen.add(pfx)
    if slug not in blocks.get(pfx, []):
        print('FAIL block', pc, sid); fails.append(f'block {pc}'); ok = False
if ok: print('PASS all 467 codes in-block')
check('blocks-45', seen == set(blocks), str(sorted(seen ^ set(blocks))))
# --- anchors: UPU examples + finder-verified splits, homonyms, gaps.
anchors = {'10000': 'kadiogo', '10010': 'kadiogo', '10250': 'kadiogo',
    '70000': 'boulgou', '70550': 'boulgou', '70551': 'boulgou',
    '70500': 'boulgou', '50250': 'sandbondtenga', '55450': 'yatenga',
    '50450': 'sandbondtenga', '96300': 'poni', '91250': 'kenedougou',
    '90000': 'houet', '90200': 'houet', '90201': 'houet',
    '91001': 'kenedougou', '31000': 'bazega', '50000': 'sandbondtenga',
    '62000': 'djelgodji', '62200': 'karo-peli', '62201': 'karo-peli',
    '62400': 'karo-peli', '79200': 'dyamongou', '79250': 'dyamongou',
    '79000': 'gobnangou', '42350': 'sissili', '42450': 'sissili'}
for pc, slug in anchors.items():
    want = f'bf:province:{slug}'
    check(f'anchor-{pc}-{slug}', plink.get(pc) == want, str(plink.get(pc)))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
